<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerDedicatedAccount;
use App\Models\WalletTransaction;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    /**
     * The authoritative way wallets get credited. Paystack calls this
     * server-to-server the moment money actually moves — for both:
     * (a) a popup card payment against a reference we generated, and
     * (b) a transfer straight into a customer's dedicated account number,
     *     which happens whenever they want and has NO reference we created
     *     up front.
     *
     * This does not depend on the customer's browser staying open, unlike
     * the frontend's /verify call — that one is still there as a fast-path
     * so the UI updates instantly, but this webhook is what guarantees the
     * wallet gets credited even if the tab was closed mid-payment.
     */
    public function handle(Request $request, PaystackService $paystack)
    {
        $signature = $request->header('x-paystack-signature');

        if (! $paystack->verifyWebhookSignature($request->getContent(), $signature)) {
            Log::warning('Paystack webhook: invalid signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.success') {
            $this->handleChargeSuccess($data);
        }

        // Always 200 quickly so Paystack doesn't retry unnecessarily —
        // unhandled event types are simply ignored.
        return response()->json(['status' => 'received']);
    }

    protected function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;
        if (! $reference) {
            return;
        }

        // Case 1: this reference matches a wallet funding we already created
        // a pending row for (popup flow). Credit it if not already done —
        // idempotent, so a duplicate webhook delivery is harmless.
        $transaction = WalletTransaction::where('reference', $reference)->first();

        if ($transaction) {
            if ($transaction->status === 'successful') {
                return;
            }

            DB::transaction(function () use ($transaction) {
                $customer = $transaction->customer()->lockForUpdate()->first();
                $newBalance = $customer->wallet_balance + $transaction->amount;

                $transaction->update([
                    'status' => 'successful',
                    'balance_before' => $customer->wallet_balance,
                    'balance_after' => $newBalance,
                ]);

                $customer->update(['wallet_balance' => $newBalance]);
            });

            return;
        }

        // Case 2: no matching row — this is a direct transfer into a
        // customer's dedicated virtual account, which happens outside any
        // flow we initiated. Identify the customer via Paystack's numeric
        // customer id (NOT customer_code — the same customer has both, but
        // this app stores and matches on the plain numeric id throughout,
        // matching what's actually in customer_dedicated_accounts).
        $paystackNumericId = $data['customer']['id'] ?? null;
        if (! $paystackNumericId) {
            Log::warning('Paystack webhook: charge.success with no matching reference and no customer id', ['reference' => $reference]);
            return;
        }

        $dedicatedAccount = CustomerDedicatedAccount::where('paystack_customer_id', $paystackNumericId)->first();
        if (! $dedicatedAccount) {
            Log::warning('Paystack webhook: charge.success for unknown dedicated account customer', ['paystack_customer_id' => $paystackNumericId]);
            return;
        }

        $amountNaira = ($data['amount'] ?? 0) / 100;

        DB::transaction(function () use ($dedicatedAccount, $reference, $amountNaira) {
            // firstOrCreate on the unique reference makes this safe against
            // Paystack's automatic webhook retries.
            $existing = WalletTransaction::where('reference', $reference)->exists();
            if ($existing) {
                return;
            }

            $customer = $dedicatedAccount->customer()->lockForUpdate()->first();
            $newBalance = $customer->wallet_balance + $amountNaira;

            WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'credit',
                'amount' => $amountNaira,
                'balance_before' => $customer->wallet_balance,
                'balance_after' => $newBalance,
                'source' => 'dedicated_account_transfer',
                'reference' => $reference,
                'status' => 'successful',
                'description' => 'Bank transfer to dedicated account',
            ]);

            $customer->update(['wallet_balance' => $newBalance]);
        });
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerDedicatedAccount;
use App\Models\WalletTransaction;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        $customer = $request->user();
        $perPage = min((int) $request->query('per_page', 5), 50);

        return response()->json([
            'wallet_balance' => $customer->wallet_balance,
            'transactions' => $customer->walletTransactions()->latest()->paginate($perPage),
        ]);
    }

    /**
     * Step 1 (popup flow): create a pending wallet_transactions row and hand
     * back the reference + public key so the frontend can open Paystack's
     * inline popup directly — no hosted-page redirect involved, so there's
     * no "callback URL" to misconfigure.
     */
    /**
     * Manual bank transfer: customer uploads proof of payment + notes,
     * lands as a PENDING wallet_transactions row for an admin to review and
     * approve/reject (see AdminBookingController::approveWalletFunding /
     * rejectWalletFunding). Nothing is credited until an admin acts on it.
     */
    public function manualFunding(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'notes' => 'nullable|string|max:500',
            'proof_of_payment' => 'required|image|max:5120', // 5MB
        ]);

        $customer = $request->user();
        $path = $request->file('proof_of_payment')->store('proof-of-payment', 'public');

        $transaction = WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => $request->amount,
            'balance_before' => $customer->wallet_balance,
            'balance_after' => $customer->wallet_balance, // updated only on admin approval
            'source' => 'bank_transfer',
            'reference' => 'TRANSFER-' . Str::upper(Str::random(12)),
            'status' => 'pending',
            'proof_of_payment' => $path,
            'description' => $request->notes,
        ]);

        try {
            app(\App\Services\PushNotificationService::class)->notifyAllAdmins(
                'New funding request',
                "{$customer->firstname} {$customer->lastname} submitted ₦" . number_format($request->amount) . " for review",
                '/admin?tab=fundings'
            );
        } catch (\Throwable $e) {
            \Log::warning('Admin push notification failed to send: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Submitted — an admin will review your proof of payment and credit your wallet shortly.',
            'transaction' => $transaction,
        ], 201);
    }

    /**
     * Static company bank details for manual transfers.
     */
    public function bankDetails()
    {
        return response()->json([
            'bank_name' => 'Eco Bank Nigeria Plc',
            'account_name' => 'Cyberhive Innovation & Incubation hub ng',
            'account_number' => '3980078059',
        ]);
    }

    public function initializeFunding(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:100']);

        $customer = $request->user();
        $reference = 'WALLET-' . Str::upper(Str::random(12));

        WalletTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'credit',
            'amount' => $request->amount,
            'balance_before' => $customer->wallet_balance,
            'balance_after' => $customer->wallet_balance, // updated on verify
            'source' => 'paystack_funding',
            'reference' => $reference,
            'status' => 'pending',
        ]);

        return response()->json([
            'reference' => $reference,
            'amount' => $request->amount,
            'email' => $customer->email,
            'public_key' => config('services.paystack.mode') === 'live'
                ? config('services.paystack.live_public')
                : config('services.paystack.test_public'),
        ]);
    }

    /**
     * Step 2: called after Paystack redirect (or polled by the frontend) to
     * confirm payment and credit the wallet. The webhook (separate endpoint)
     * does the same thing idempotently in case the user closes the tab.
     */
    public function verifyFunding(Request $request, PaystackService $paystack, string $reference)
    {
        $transaction = WalletTransaction::where('reference', $reference)->firstOrFail();

        if ($transaction->status === 'successful') {
            return response()->json(['message' => 'Already credited', 'transaction' => $transaction]);
        }

        $data = $paystack->verifyTransaction($reference);

        if ($data['status'] !== 'success') {
            $transaction->update(['status' => 'failed']);
            return response()->json(['message' => 'Payment not successful'], 422);
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

        return response()->json([
            'message' => 'Wallet funded',
            'transaction' => $transaction->fresh(),
            'wallet_balance' => $transaction->customer->fresh()->wallet_balance,
        ]);
    }

    /**
     * Returns the customer's dedicated bank account if they already have one.
     */
    public function dedicatedAccount(Request $request)
    {
        $account = $request->user()->dedicatedAccount;

        return response()->json($account);
    }

    /**
     * Creates a permanent bank account number for this customer to transfer
     * money into directly. Safe to call more than once — returns the existing
     * account instead of creating a duplicate.
     */
    public function createDedicatedAccount(Request $request, PaystackService $paystack)
    {
        $customer = $request->user();

        if ($existing = $customer->dedicatedAccount) {
            return response()->json($existing);
        }

        $paystackCustomer = $paystack->createCustomer(
            $customer->email,
            $customer->firstname,
            $customer->lastname,
            $customer->phone
        );

        $dva = $paystack->createDedicatedAccount($paystackCustomer['customer_code']);

        $account = CustomerDedicatedAccount::create([
            'customer_id' => $customer->id,
            'paystack_customer_id' => $paystackCustomer['customer_code'],
            'bank_name' => $dva['bank']['name'] ?? null,
            'account_name' => $dva['account_name'] ?? null,
            'account_number' => $dva['account_number'] ?? null,
            'currency' => $dva['currency'] ?? 'NGN',
            'paystack_account_id' => $dva['id'] ?? null,
        ]);

        return response()->json($account, 201);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;

/**
 * One-time fix for the fallout of a real bug: `wallet_balance` was missing
 * from Customer's $fillable array, so every Eloquent ->update() touching it
 * silently did nothing — bookings, refunds, admin adjustments, Paystack
 * funding, all of it. The wallet_transactions rows were still created
 * correctly (that table's own $fillable was fine), so the true history is
 * intact — customers.wallet_balance itself is just frozen at whatever it
 * was before the bug started (your original customer import, since that
 * went in via a raw DB insert, not Eloquent).
 *
 * This recalculates the correct balance for every customer as:
 *   (their current, frozen wallet_balance) + net of every successful
 *   wallet_transaction on their account, and corrects the column to match.
 *
 * Safe to run once now (right after upgrading), and safe to re-run — running
 * it again after the fix is deployed and working normally is a no-op, since
 * by then wallet_balance updates are actually persisting and already match.
 *
 * Usage: php artisan wallet:reconcile
 *        php artisan wallet:reconcile --dry-run
 */
class ReconcileWalletBalances extends Command
{
    protected $signature = 'wallet:reconcile {--dry-run}';
    protected $description = 'Recalculate customer wallet balances from their true transaction history (fixes the frozen-balance bug)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $corrected = 0;

        Customer::chunkById(100, function ($customers) use (&$corrected, $dryRun) {
            foreach ($customers as $customer) {
                $netMovement = WalletTransaction::where('customer_id', $customer->id)
                    ->where('status', 'successful')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) as net")
                    ->value('net');

                $correctBalance = round((float) $customer->wallet_balance + (float) $netMovement, 2);

                if (abs($correctBalance - (float) $customer->wallet_balance) < 0.01) {
                    continue; // already correct — nothing to fix for this customer
                }

                $this->line("Customer #{$customer->id} ({$customer->email}): {$customer->wallet_balance} -> {$correctBalance}");
                $corrected++;

                if (! $dryRun) {
                    $customer->update(['wallet_balance' => $correctBalance]);
                }
            }
        });

        if ($corrected === 0) {
            $this->info('No customers needed correction — all balances already match their transaction history.');
        } else {
            $this->info(($dryRun ? 'Would correct ' : 'Corrected ') . "{$corrected} customer(s).");
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes were made. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}

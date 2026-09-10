<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports historical wallet funding records from the legacy `fundwalletdb`
 * table, for display in "Funding History" only — this does NOT touch
 * customers.wallet_balance. That was already set correctly to each
 * customer's final legacy balance by `legacy:sync-customers --sync-wallet`;
 * these rows are purely historical context underneath that number, not a
 * replay that changes it.
 *
 * Idempotent: keyed on the legacy reference_number, which becomes the new
 * row's `reference` — unique in this app's schema, so re-running just
 * updates existing rows rather than duplicating them.
 *
 * Note: `proof_payment` file paths are copied as text for record-keeping,
 * but the actual image files themselves were never transferred between
 * servers — clicking through to view them won't work unless those files
 * are separately copied to this app's storage.
 */
class SyncLegacyWalletHistory extends Command
{
    protected $signature = 'legacy:sync-wallet-history {--dry-run}';
    protected $description = 'Import historical wallet funding records from the legacy database (display only, does not affect current balances)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Could not connect to the legacy database: ' . $e->getMessage());
            return self::FAILURE;
        }

        $legacyEmails = DB::connection('legacy')->table('customerdb')->pluck('email', 'id');
        $legacyFundings = DB::connection('legacy')->table('fundwalletdb')->orderBy('id')->get();

        $this->info("Found {$legacyFundings->count()} wallet funding records in the legacy database.");

        $imported = 0;
        $skipped = 0;

        foreach ($legacyFundings as $row) {
            $email = $row->user_id ? ($legacyEmails[$row->user_id] ?? null) : null;
            $customer = $email ? Customer::where('email', $email)->first() : null;

            if (! $customer) {
                $this->line("- Skipped funding #{$row->id}: no matching customer for legacy user_id {$row->user_id}");
                $skipped++;
                continue;
            }

            if (! $row->reference_number) {
                $this->line("- Skipped funding #{$row->id}: no reference number to key on");
                $skipped++;
                continue;
            }

            $this->line("+ {$customer->email}: ₦{$row->amount} ({$row->status})");
            $imported++;

            if (! $dryRun) {
                WalletTransaction::updateOrCreate(
                    ['reference' => $row->reference_number],
                    [
                        'customer_id' => $customer->id,
                        'type' => 'credit',
                        'amount' => $row->amount,
                        'balance_before' => $row->initial_balance,
                        'balance_after' => $row->final_balance,
                        'source' => $this->mapSource($row->payment_method),
                        'status' => $this->mapStatus($row->status),
                        'proof_of_payment' => $row->proof_payment,
                        'description' => 'Imported from legacy site',
                        'created_at' => $this->sanitizeDatetime($row->date) ?? now(),
                    ]
                );
            }
        }

        $this->info(($dryRun ? 'Would import ' : 'Imported ') . "{$imported}, skipped {$skipped}.");
        $this->warn('Note: this only imports the record for display — it does not change any customer\'s current wallet_balance.');

        if ($dryRun) {
            $this->warn('Dry run — no changes were made.');
        }

        return self::SUCCESS;
    }

    protected function mapSource(?string $legacyMethod): string
    {
        return match (strtolower((string) $legacyMethod)) {
            'paystack' => 'paystack_funding',
            'bank transfer', 'direct bank transfer' => 'bank_transfer',
            default => 'legacy_import',
        };
    }

    protected function mapStatus(?string $legacyStatus): string
    {
        // Legacy enum ('pending','successful','failed') already matches
        // this app's WalletTransaction status enum exactly.
        return in_array($legacyStatus, ['pending', 'successful', 'failed']) ? $legacyStatus : 'successful';
    }

    protected function sanitizeDatetime(?string $value): ?string
    {
        if (! $value || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

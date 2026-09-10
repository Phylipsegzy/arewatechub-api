<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerDedicatedAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports each customer's dedicated Paystack virtual account number from the
 * legacy database. Matched by email (legacy customer_id doesn't correspond
 * to this app's customer IDs, since those were reassigned on import), so
 * this must run AFTER `legacy:sync-customers`. Safe to re-run — updates
 * existing rows rather than duplicating them.
 */
class SyncLegacyDedicatedAccounts extends Command
{
    protected $signature = 'legacy:sync-dedicated-accounts {--dry-run}';
    protected $description = 'Import customer dedicated virtual account numbers from the legacy database';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Could not connect to the legacy database: ' . $e->getMessage());
            return self::FAILURE;
        }

        // One query to map legacy customer_id -> email, so we can match to
        // this app's customers without relying on IDs lining up.
        $legacyEmails = DB::connection('legacy')->table('customerdb')->pluck('email', 'id');

        $legacyAccounts = DB::connection('legacy')->table('customer_dedicated_accounts')->get();

        $this->info("Found {$legacyAccounts->count()} dedicated accounts in the legacy database.");

        $imported = 0;
        $skipped = 0;

        foreach ($legacyAccounts as $row) {
            $email = $legacyEmails[$row->customer_id] ?? null;

            if (! $email) {
                $this->line("- Skipped: no matching legacy customer for customer_id {$row->customer_id}");
                $skipped++;
                continue;
            }

            $customer = Customer::where('email', $email)->first();

            if (! $customer) {
                $this->line("- Skipped: {$email} not found in this app (run legacy:sync-customers first?)");
                $skipped++;
                continue;
            }

            $this->line("+ {$email}: {$row->account_number} ({$row->bank_name})");
            $imported++;

            if (! $dryRun) {
                CustomerDedicatedAccount::updateOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'paystack_customer_id' => $row->paystack_customer_id,
                        'bank_name' => $row->bank_name,
                        'account_name' => $row->account_name,
                        'account_number' => $row->account_number,
                        'currency' => $row->currency,
                        'paystack_account_id' => $row->paystack_account_id,
                    ]
                );
            }
        }

        $this->info(($dryRun ? 'Would import ' : 'Imported ') . "{$imported}, skipped {$skipped}.");

        if ($dryRun) {
            $this->warn('Dry run — no changes were made.');
        }

        return self::SUCCESS;
    }
}

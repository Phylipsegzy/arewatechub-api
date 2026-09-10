<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Live sync from the old site's database (customerdb table) into the new
 * app's customers table — read-only against the legacy DB, never writes to
 * it. Matched and updated by email, so it's safe to run repeatedly while
 * both sites are live during the transition: it picks up brand-new
 * customerdb signups AND price/name edits on existing ones, without ever
 * touching a customer who already exists here with a different wallet
 * balance than what's in the legacy row (see the --sync-wallet flag).
 *
 * Password hashes are copied as-is — the legacy site's bcrypt hashes work
 * natively with Laravel's Hash::check(), so customers log in with their
 * existing password immediately, no reset needed.
 *
 * Usage:
 *   php artisan legacy:sync-customers                 (imports new customers only)
 *   php artisan legacy:sync-customers --dry-run        (preview, no changes)
 *   php artisan legacy:sync-customers --sync-wallet     (also overwrites wallet_balance
 *                                                        from the legacy row — only use
 *                                                        this if the old site is still
 *                                                        the source of truth for money;
 *                                                        skip it once the new app is live
 *                                                        and customers are spending here)
 */
class SyncLegacyCustomers extends Command
{
    protected $signature = 'legacy:sync-customers {--dry-run} {--sync-wallet}';
    protected $description = 'Import/update customers from the legacy site\'s database (read-only, safe to re-run)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $syncWallet = $this->option('sync-wallet');

        if (! $this->testLegacyConnection()) {
            return self::FAILURE;
        }

        $legacyCustomers = DB::connection('legacy')->table('customerdb')->orderBy('id')->get();

        $this->info("Found {$legacyCustomers->count()} customers in the legacy database.");

        $created = 0;
        $updated = 0;

        foreach ($legacyCustomers as $row) {
            if (! $row->email) {
                continue; // skip anything with no email — can't match/log in without one
            }

            $existing = Customer::where('email', $row->email)->first();

            $attributes = [
                'firstname' => $row->firstname,
                'lastname' => $row->lastname,
                'middlename' => $row->middlename,
                'email' => $row->email,
                'phone' => $row->phone,
                'nin' => $row->nin ?? null,
                'picture_url' => $row->picture_url ?? null,
                'state' => $row->state,
                'city' => $row->city,
                'gender' => $row->gender,
                'address' => $row->address,
                'dob' => $this->sanitizeDate($row->dob),
                'education' => $row->education,
                'password' => $row->password, // already bcrypt — works natively with Hash::check()
            ];

            if (! $existing) {
                $attributes['wallet_balance'] = $row->wallet ?? 0;
                $attributes['created_at'] = $this->sanitizeDate($row->created_at) ?? now();

                $this->line("+ New: {$row->email}");
                $created++;

                if (! $dryRun) {
                    Customer::create($attributes);
                }
            } else {
                if ($syncWallet) {
                    $attributes['wallet_balance'] = $row->wallet ?? $existing->wallet_balance;
                }

                $this->line("~ Update: {$row->email}");
                $updated++;

                if (! $dryRun) {
                    $existing->update($attributes);
                }
            }
        }

        $this->info(($dryRun ? 'Would create ' : 'Created ') . "{$created}, " . ($dryRun ? 'would update ' : 'updated ') . "{$updated}.");

        if ($dryRun) {
            $this->warn('Dry run — no changes were made.');
        }

        return self::SUCCESS;
    }

    protected function testLegacyConnection(): bool
    {
        try {
            DB::connection('legacy')->getPdo();
            return true;
        } catch (\Throwable $e) {
            $this->error('Could not connect to the legacy database: ' . $e->getMessage());
            $this->line('Check LEGACY_DB_* values in your .env — see .env.example.additions.');
            return false;
        }
    }

    /**
     * The legacy DB has some rows with MySQL's old-style zero-dates
     * ('0000-00-00', '0000-00-00 00:00:00') — valid under lenient SQL modes
     * decades ago, rejected outright by any modern strict-mode connection
     * (exactly the error that surfaced here). Treat anything that isn't a
     * real, parseable date as simply "unknown" (null) instead.
     */
    protected function sanitizeDate(?string $value): ?string
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

<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\Room;
use App\Models\WorkspaceSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Imports historical bookings from the legacy database, for record-keeping
 * and display only — this does NOT recreate the Order/WalletTransaction
 * rows or re-trigger WiFi assignment for them, since:
 *   - Customer wallet balances were already set to their correct final
 *     value by `legacy:sync-customers --sync-wallet`; replaying historical
 *     payments here would double-count nothing (we don't touch balances at
 *     all in this command) but would clutter wallet history with entries
 *     that don't reconcile against anything.
 *   - WiFi credential assignment only makes sense for upcoming/active
 *     bookings, not history.
 *
 * The tricky part: legacy plan_id/room_id/session_id/duration_id are
 * foreign keys into the LEGACY plans/rooms/sessions/plan_durations tables,
 * which use completely different auto-increment IDs than this app's tables
 * (rebuilt from scratch, not a raw copy). So every legacy booking is
 * resolved by NAME instead — legacy plan_id 7 might be "Basic Package",
 * looked up by name against this app's Plan table. If a plan/room/session/
 * duration was renamed or removed since, that booking is skipped with a
 * clear reason rather than guessed at.
 *
 * Idempotent: keyed on legacy booking_id via a note in the row itself isn't
 * stored, so re-running matches on (customer_id, room_id, seat_number,
 * start_datetime) instead — safe to run more than once without duplicating.
 */
class SyncLegacyBookings extends Command
{
    protected $signature = 'legacy:sync-bookings {--dry-run}';
    protected $description = 'Import historical bookings from the legacy database (record-keeping only)';

    protected array $requiredLegacyTables = ['bookings', 'plans', 'rooms', 'sessions', 'plan_durations', 'customerdb'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Could not connect to the legacy database: ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach ($this->requiredLegacyTables as $table) {
            if (! Schema::connection('legacy')->hasTable($table)) {
                $this->error("Legacy table `{$table}` not found — check your legacy database has this table before running this command.");
                return self::FAILURE;
            }
        }

        // Build id -> name lookup maps from the legacy DB once, rather than
        // querying per-row.
        $legacyEmails = DB::connection('legacy')->table('customerdb')->pluck('email', 'id');
        $legacyPlanNames = DB::connection('legacy')->table('plans')->pluck('plan_name', 'plan_id');
        $legacyRoomNames = DB::connection('legacy')->table('rooms')->pluck('room_name', 'room_id');
        $legacySessionNames = DB::connection('legacy')->table('sessions')->pluck('session_name', 'session_id');
        $legacyDurations = DB::connection('legacy')->table('plan_durations')->get()->keyBy('duration_id');

        // This app's current plans/rooms/sessions/durations, keyed by name
        // (or plan+room+name for durations, since duration names repeat).
        $newPlans = Plan::pluck('id', 'name');
        $newRooms = Room::pluck('id', 'name');
        $newSessions = WorkspaceSession::pluck('id', 'name');
        $newDurations = PlanDuration::get()->keyBy(fn ($d) => "{$d->plan_id}-{$d->room_id}-{$d->name}");

        $legacyBookings = DB::connection('legacy')->table('bookings')->orderBy('booking_id')->get();
        $this->info("Found {$legacyBookings->count()} bookings in the legacy database.");

        $imported = 0;
        $skipped = 0;
        $alreadyExists = 0;

        foreach ($legacyBookings as $row) {
            $email = $legacyEmails[$row->user_id] ?? null;
            $customer = $email ? Customer::where('email', $email)->first() : null;

            if (! $customer) {
                $this->line("- Skipped booking #{$row->booking_id}: no matching customer for legacy user_id {$row->user_id}");
                $skipped++;
                continue;
            }

            $planName = $legacyPlanNames[$row->plan_id] ?? null;
            $roomName = $legacyRoomNames[$row->room_id] ?? null;
            $sessionName = $row->session_id ? ($legacySessionNames[$row->session_id] ?? null) : null;
            $legacyDuration = $row->duration_id ? ($legacyDurations[$row->duration_id] ?? null) : null;

            $newPlanId = $planName ? ($newPlans[$planName] ?? null) : null;
            $newRoomId = $roomName ? ($newRooms[$roomName] ?? null) : null;
            $newSessionId = $sessionName ? ($newSessions[$sessionName] ?? null) : null;
            $newDurationId = ($legacyDuration && $newPlanId && $newRoomId)
                ? ($newDurations["{$newPlanId}-{$newRoomId}-{$legacyDuration->duration_name}"]->id ?? null)
                : null;

            if (! $newPlanId || ! $newRoomId) {
                $this->line("- Skipped booking #{$row->booking_id}: plan \"{$planName}\" or room \"{$roomName}\" no longer exists under that name");
                $skipped++;
                continue;
            }

            $startDatetime = $this->sanitizeDatetime($row->start_datetime) ?? $this->sanitizeDatetime($row->start_date);
            $endDatetime = $this->sanitizeDatetime($row->end_datetime) ?? $this->sanitizeDatetime($row->end_date);

            $existing = Booking::where('customer_id', $customer->id)
                ->where('room_id', $newRoomId)
                ->where('seat_number', $row->seat_number)
                ->where('start_datetime', $startDatetime)
                ->first();

            if ($existing) {
                $alreadyExists++;
                continue;
            }

            $this->line("+ Booking #{$row->booking_id} -> {$customer->email}: {$roomName}, seat {$row->seat_number}");
            $imported++;

            if (! $dryRun) {
                Booking::create([
                    'customer_id' => $customer->id,
                    'plan_id' => $newPlanId,
                    'plan_duration_id' => $newDurationId,
                    'workspace_session_id' => $newSessionId,
                    'room_id' => $newRoomId,
                    'seat_number' => $row->seat_number,
                    'price' => $row->price,
                    'status' => $row->status ?? 'completed',
                    'start_date' => $this->sanitizeDate($row->start_date),
                    'end_date' => $this->sanitizeDate($row->end_date),
                    'start_datetime' => $startDatetime,
                    'end_datetime' => $endDatetime,
                    'created_at' => $this->sanitizeDatetime($row->created_at) ?? now(),
                ]);
            }
        }

        $this->info(
            ($dryRun ? 'Would import ' : 'Imported ') . "{$imported}, " .
            "already existed {$alreadyExists}, skipped (no plan/room/customer match) {$skipped}."
        );

        if ($skipped > 0) {
            $this->warn("Bookings referencing a plan/room/customer that no longer exists were skipped — this is expected for old, discontinued plans/rooms.");
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes were made.');
        }

        return self::SUCCESS;
    }

    protected function sanitizeDate(?string $value): ?string
    {
        if (! $value || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
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

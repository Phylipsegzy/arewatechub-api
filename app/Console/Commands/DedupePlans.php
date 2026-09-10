<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Plan;
use App\Models\PlanDuration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time cleanup for duplicate plans created by re-running the old
 * (non-idempotent) WorkspaceSeeder — e.g. "Basic Package" or "September
 * 2-in-1 Promo" appearing twice in the booking flow. Safe to run more than
 * once: once there are no duplicates left, it does nothing.
 *
 * Usage: php artisan plans:dedupe
 *        php artisan plans:dedupe --dry-run   (shows what it would do, changes nothing)
 */
class DedupePlans extends Command
{
    protected $signature = 'plans:dedupe {--dry-run}';
    protected $description = 'Merge duplicate plans (same name) into the earliest one, keeping all bookings intact';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $duplicateGroups = Plan::select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate plans found — nothing to do.');
            return self::SUCCESS;
        }

        foreach ($duplicateGroups as $name) {
            $plans = Plan::where('name', $name)->orderBy('id')->get();
            $canonical = $plans->first();
            $duplicates = $plans->skip(1);

            $this->info("\"{$name}\": keeping plan #{$canonical->id}, merging " . $duplicates->count() . ' duplicate(s).');

            foreach ($duplicates as $duplicate) {
                $this->mergeDuplicate($canonical, $duplicate, $dryRun);
            }
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes were made. Re-run without --dry-run to apply.');
        } else {
            $this->info('Done. Consider running "php artisan migrate" next if a unique-name migration is pending.');
        }

        return self::SUCCESS;
    }

    protected function mergeDuplicate(Plan $canonical, Plan $duplicate, bool $dryRun): void
    {
        // Move rooms/sessions the canonical doesn't already have, instead of
        // just deleting them, in case the canonical is somehow incomplete.
        $roomIds = $duplicate->rooms()->pluck('rooms.id');
        $sessionIds = $duplicate->sessions()->pluck('workspace_sessions.id');

        if (! $dryRun) {
            $canonical->rooms()->syncWithoutDetaching($roomIds->all());
            $canonical->sessions()->syncWithoutDetaching($sessionIds->all());
        }

        // For every duration under the duplicate, find (or create) the
        // matching duration under the canonical plan (same room + name),
        // then repoint any bookings from the duplicate's duration to it.
        foreach ($duplicate->durations as $duplicateDuration) {
            $canonicalDuration = PlanDuration::firstOrCreate(
                [
                    'plan_id' => $canonical->id,
                    'room_id' => $duplicateDuration->room_id,
                    'name' => $duplicateDuration->name,
                ],
                [
                    'workspace_session_id' => $duplicateDuration->workspace_session_id,
                    'days' => $duplicateDuration->days,
                    'price' => $duplicateDuration->price,
                    'internet_price' => $duplicateDuration->internet_price,
                ]
            );

            $affectedBookings = Booking::where('plan_duration_id', $duplicateDuration->id)->count();

            if ($affectedBookings > 0) {
                $this->line("  - Re-pointing {$affectedBookings} booking(s) from duration #{$duplicateDuration->id} to #{$canonicalDuration->id}");
            }

            if (! $dryRun) {
                DB::table('bookings')
                    ->where('plan_duration_id', $duplicateDuration->id)
                    ->update(['plan_id' => $canonical->id, 'plan_duration_id' => $canonicalDuration->id]);
            }
        }

        // Any booking pointing at the duplicate plan directly (shouldn't
        // normally happen since plan_duration_id is more specific, but be safe).
        if (! $dryRun) {
            Booking::where('plan_id', $duplicate->id)->update(['plan_id' => $canonical->id]);
            $duplicate->delete(); // cascades: its now-orphaned plan_durations, plan_rooms, plan_sessions
        }

        $this->line("  - Merged plan #{$duplicate->id} into #{$canonical->id}" . ($dryRun ? ' (dry run)' : ''));
    }
}

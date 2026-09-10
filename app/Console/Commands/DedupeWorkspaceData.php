<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\Room;
use App\Models\WorkspaceSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time cleanup for ALL duplicates created by re-running the old
 * (non-idempotent) WorkspaceSeeder — not just plans, but rooms and sessions
 * too, since the same seeder created all three every time it ran. This is
 * why "Choose a workspace" was showing rooms twice: two separate Room rows
 * both named e.g. "Conference Room", genuinely different database rows, so
 * the query correctly returned both — the data itself was duplicated.
 *
 * Supersedes `plans:dedupe` (still works, but this covers everything in one
 * pass, in the right order: rooms and sessions first, since plans reference
 * them). Safe to run more than once.
 *
 * Usage: php artisan workspace:dedupe
 *        php artisan workspace:dedupe --dry-run
 */
class DedupeWorkspaceData extends Command
{
    protected $signature = 'workspace:dedupe {--dry-run}';
    protected $description = 'Merge duplicate rooms, sessions, and plans (same name) left over from the old seeder, keeping all bookings intact';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->dedupeRooms($dryRun);
        $this->dedupeSessions($dryRun);
        $this->dedupePlans($dryRun);

        if ($dryRun) {
            $this->warn('Dry run — no changes were made. Re-run without --dry-run to apply.');
        } else {
            $this->info('Done. Run "php artisan migrate" next if a unique-name migration is pending.');
        }

        return self::SUCCESS;
    }

    protected function dedupeRooms(bool $dryRun): void
    {
        $names = Room::select('name')->groupBy('name')->havingRaw('COUNT(*) > 1')->pluck('name');

        if ($names->isEmpty()) {
            $this->info('Rooms: no duplicates found.');
            return;
        }

        foreach ($names as $name) {
            $rooms = Room::where('name', $name)->orderBy('id')->get();
            $canonical = $rooms->first();
            $this->info("Room \"{$name}\": keeping #{$canonical->id}, merging " . $rooms->count() - 1 . ' duplicate(s).');

            foreach ($rooms->skip(1) as $duplicate) {
                $bookingCount = Booking::where('room_id', $duplicate->id)->count();
                $durationCount = PlanDuration::where('room_id', $duplicate->id)->count();

                if ($bookingCount > 0) {
                    $this->line("  - Re-pointing {$bookingCount} booking(s) from room #{$duplicate->id} to #{$canonical->id}");
                }
                if ($durationCount > 0) {
                    $this->line("  - Re-pointing {$durationCount} plan_duration(s) from room #{$duplicate->id} to #{$canonical->id}");
                }

                if (! $dryRun) {
                    DB::table('bookings')->where('room_id', $duplicate->id)->update(['room_id' => $canonical->id]);
                    DB::table('plan_durations')->where('room_id', $duplicate->id)->update(['room_id' => $canonical->id]);
                    DB::table('plan_rooms')->where('room_id', $duplicate->id)->delete(); // canonical's own plan_rooms rows already cover the same plans
                    $duplicate->delete();
                }

                $this->line("  - Merged room #{$duplicate->id} into #{$canonical->id}" . ($dryRun ? ' (dry run)' : ''));
            }
        }
    }

    protected function dedupeSessions(bool $dryRun): void
    {
        $names = WorkspaceSession::select('name')->groupBy('name')->havingRaw('COUNT(*) > 1')->pluck('name');

        if ($names->isEmpty()) {
            $this->info('Sessions: no duplicates found.');
            return;
        }

        foreach ($names as $name) {
            $sessions = WorkspaceSession::where('name', $name)->orderBy('id')->get();
            $canonical = $sessions->first();
            $this->info("Session \"{$name}\": keeping #{$canonical->id}, merging " . $sessions->count() - 1 . ' duplicate(s).');

            foreach ($sessions->skip(1) as $duplicate) {
                $bookingCount = Booking::where('workspace_session_id', $duplicate->id)->count();

                if ($bookingCount > 0) {
                    $this->line("  - Re-pointing {$bookingCount} booking(s) from session #{$duplicate->id} to #{$canonical->id}");
                }

                if (! $dryRun) {
                    DB::table('bookings')->where('workspace_session_id', $duplicate->id)->update(['workspace_session_id' => $canonical->id]);
                    DB::table('plan_durations')->where('workspace_session_id', $duplicate->id)->update(['workspace_session_id' => $canonical->id]);
                    DB::table('plan_sessions')->where('workspace_session_id', $duplicate->id)->delete();
                    $duplicate->delete();
                }

                $this->line("  - Merged session #{$duplicate->id} into #{$canonical->id}" . ($dryRun ? ' (dry run)' : ''));
            }
        }
    }

    protected function dedupePlans(bool $dryRun): void
    {
        $names = Plan::select('name')->groupBy('name')->havingRaw('COUNT(*) > 1')->pluck('name');

        if ($names->isEmpty()) {
            $this->info('Plans: no duplicates found.');
            return;
        }

        foreach ($names as $name) {
            $plans = Plan::where('name', $name)->orderBy('id')->get();
            $canonical = $plans->first();
            $this->info("Plan \"{$name}\": keeping #{$canonical->id}, merging " . $plans->count() - 1 . ' duplicate(s).');

            foreach ($plans->skip(1) as $duplicate) {
                $roomIds = $duplicate->rooms()->pluck('rooms.id');
                $sessionIds = $duplicate->sessions()->pluck('workspace_sessions.id');

                if (! $dryRun) {
                    $canonical->rooms()->syncWithoutDetaching($roomIds->all());
                    $canonical->sessions()->syncWithoutDetaching($sessionIds->all());
                }

                foreach ($duplicate->durations as $duplicateDuration) {
                    $canonicalDuration = PlanDuration::firstOrCreate(
                        ['plan_id' => $canonical->id, 'room_id' => $duplicateDuration->room_id, 'name' => $duplicateDuration->name],
                        [
                            'workspace_session_id' => $duplicateDuration->workspace_session_id,
                            'days' => $duplicateDuration->days,
                            'price' => $duplicateDuration->price,
                            'internet_price' => $duplicateDuration->internet_price,
                        ]
                    );

                    $affected = Booking::where('plan_duration_id', $duplicateDuration->id)->count();
                    if ($affected > 0) {
                        $this->line("  - Re-pointing {$affected} booking(s) from duration #{$duplicateDuration->id} to #{$canonicalDuration->id}");
                    }

                    if (! $dryRun) {
                        DB::table('bookings')
                            ->where('plan_duration_id', $duplicateDuration->id)
                            ->update(['plan_id' => $canonical->id, 'plan_duration_id' => $canonicalDuration->id]);
                    }
                }

                if (! $dryRun) {
                    Booking::where('plan_id', $duplicate->id)->update(['plan_id' => $canonical->id]);
                    $duplicate->delete();
                }

                $this->line("  - Merged plan #{$duplicate->id} into #{$canonical->id}" . ($dryRun ? ' (dry run)' : ''));
            }
        }
    }
}

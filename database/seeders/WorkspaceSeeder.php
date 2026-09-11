<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\Room;
use App\Models\WorkspaceSession;
use Illuminate\Database\Seeder;

// Seeded to match your live business rules: 3 active plans (September 2-in-1
// Promo, Basic Package, Free Access Wednesday), ONE customer-selectable
// session (8am-6pm), and real per-room pricing including the VIP room.
//
// IDEMPOTENT: every create() below is firstOrCreate()/updateOrCreate() keyed
// on the natural identity of the row (name, or plan+room+duration name), so
// re-running this on production safely UPDATES existing rows instead of
// duplicating them — this is how the Sept 2026 pricing/room/session
// restructure gets applied to live data, not just fresh installs.
class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        // Rename the legacy "Dedicated Space" to "Dedicated Space (VIP)"
        // in place, rather than creating a new room under the new name —
        // that would leave the old row as an orphaned duplicate (the exact
        // bug `workspace:dedupe` was built to fix). Only runs once; after
        // the rename, the row is found by its new name on every future run.
        if (Room::where('name', 'Dedicated Space')->exists() && ! Room::where('name', 'Dedicated Space (VIP)')->exists()) {
            Room::where('name', 'Dedicated Space')->update(['name' => 'Dedicated Space (VIP)']);
        }

        $rooms = [
            1 => Room::firstOrCreate(['name' => 'Conference Room'], ['seat_start' => 1, 'seat_end' => 20]),
            2 => Room::firstOrCreate(['name' => 'Workspace 1'], ['seat_start' => 21, 'seat_end' => 30]),
            3 => Room::firstOrCreate(['name' => 'Private Space'], ['seat_start' => 31, 'seat_end' => 40]),
            4 => Room::firstOrCreate(['name' => 'Dedicated Space (VIP)'], ['seat_start' => 41, 'seat_end' => 50]),
            6 => Room::firstOrCreate(['name' => 'Phone Workspace'], ['seat_start' => 61, 'seat_end' => 70]),
        ];

        // Consolidated down to a single daily session. The old three
        // (9am-3pm, 3:30pm-9pm, 10pm-7am) are intentionally left in the
        // `workspace_sessions` table untouched — deleting them would null
        // out the session reference on every historical booking that used
        // one. They're just no longer offered as choices (see the
        // ->sync() calls below, which replace each plan's allowed sessions
        // entirely rather than adding to them).
        $sessionAllDay = WorkspaceSession::firstOrCreate(['name' => '8am - 6pm'], ['start_time' => '08:00:00', 'end_time' => '18:00:00']);

        $basic = Plan::firstOrCreate(
            ['name' => 'Basic Package'],
            ['requires_seat_selection' => true, 'is_automatic_daily' => false, 'promo_fixed_end_date' => null, 'restricted_weekday' => null]
        );

        $promo = Plan::firstOrCreate(
            ['name' => 'September 2-in-1 Promo'],
            [
                'requires_seat_selection' => true,
                'is_automatic_daily' => false,
                // Every booking under this promo ends here, whatever duration
                // (1 Week or 1 Month) or date in September it was booked on —
                // that's what actually delivers the "2-in-1" bonus time, not
                // a separate multiplier on the duration itself.
                'promo_fixed_end_date' => '2026-10-31',
                'restricted_weekday' => null,
            ]
        );

        $freeWednesday = Plan::firstOrCreate(
            ['name' => 'Free Access Wednesday'],
            [
                'requires_seat_selection' => true,
                'is_automatic_daily' => false,
                'promo_fixed_end_date' => null,
                'restricted_weekday' => 3, // Carbon: 0=Sunday ... 3=Wednesday
            ]
        );

        // Basic Package: all four rooms, including the VIP room now.
        foreach ([$rooms[1], $rooms[2], $rooms[3], $rooms[4]] as $room) {
            $basic->rooms()->syncWithoutDetaching([$room->id]);
        }

        // Promo: all four rooms too — the VIP room is now bookable under
        // the promo as well.
        foreach ([$rooms[1], $rooms[2], $rooms[3], $rooms[4]] as $room) {
            $promo->rooms()->syncWithoutDetaching([$room->id]);
        }

        // Every plan now offers only the one consolidated session — sync()
        // (not syncWithoutDetaching) deliberately replaces whatever sessions
        // were allowed before with just this one.
        foreach ([$basic, $promo, $freeWednesday] as $plan) {
            $plan->sessions()->sync([$sessionAllDay->id]);
        }

        $freeWednesday->rooms()->syncWithoutDetaching([$rooms[1]->id]);

        $basicPricing = [
            1 => ['1 Day' => ['days' => 1, 'price' => 1500], '1 Week' => ['days' => 7, 'price' => 9000], '1 Month' => ['days' => 30, 'price' => 30000]],
            2 => ['1 Day' => ['days' => 1, 'price' => 2000], '1 Week' => ['days' => 7, 'price' => 12000], '1 Month' => ['days' => 30, 'price' => 40000]],
            3 => ['1 Day' => ['days' => 1, 'price' => 2000], '1 Week' => ['days' => 7, 'price' => 12000], '1 Month' => ['days' => 30, 'price' => 40000]],
            4 => ['1 Day' => ['days' => 1, 'price' => 3000], '1 Week' => ['days' => 7, 'price' => 18000], '1 Month' => ['days' => 30, 'price' => 50000]],
        ];

        foreach ($basicPricing as $roomKey => $durations) {
            foreach ($durations as $name => $d) {
                PlanDuration::updateOrCreate(
                    ['plan_id' => $basic->id, 'room_id' => $rooms[$roomKey]->id, 'name' => $name],
                    ['workspace_session_id' => null, 'days' => $d['days'], 'price' => $d['price'], 'internet_price' => 0]
                );
            }
        }

        // Retire the old flat-rate promo duration ("September 2-in-1 Promo",
        // one price per room, 1 day) — replaced by real "1 Month" / "1 Week"
        // options below. Bookings already made against these old rows keep
        // their own stored price/dates untouched; only the FK link on
        // historical bookings is cleared (bookings.plan_duration_id ->
        // null on delete), which doesn't affect anything already booked.
        PlanDuration::where('plan_id', $promo->id)->where('name', 'September 2-in-1 Promo')->delete();

        // "Pay for 1 month / 1 week, get extra time free" — the free part
        // comes entirely from the promo_fixed_end_date override above, so
        // the weekly price here is deliberately identical to Basic
        // Package's weekly price for the same room; only the space differs.
        $promoPricing = [
            1 => ['1 Month' => ['days' => 30, 'price' => 30000], '1 Week' => ['days' => 7, 'price' => 9000]],
            2 => ['1 Month' => ['days' => 30, 'price' => 40000], '1 Week' => ['days' => 7, 'price' => 12000]],
            3 => ['1 Month' => ['days' => 30, 'price' => 40000], '1 Week' => ['days' => 7, 'price' => 12000]],
            4 => ['1 Month' => ['days' => 30, 'price' => 50000], '1 Week' => ['days' => 7, 'price' => 18000]],
        ];

        foreach ($promoPricing as $roomKey => $durations) {
            foreach ($durations as $name => $d) {
                PlanDuration::updateOrCreate(
                    ['plan_id' => $promo->id, 'room_id' => $rooms[$roomKey]->id, 'name' => $name],
                    ['workspace_session_id' => null, 'days' => $d['days'], 'price' => $d['price'], 'internet_price' => 0]
                );
            }
        }

        PlanDuration::updateOrCreate(
            ['plan_id' => $freeWednesday->id, 'room_id' => $rooms[1]->id, 'name' => 'Free Access Wednesday'],
            ['workspace_session_id' => null, 'days' => 1, 'price' => 120, 'internet_price' => 0]
        );

        // NOTE: replace with your real Paystack TEST secret/public keys before running.
        // Never commit your live secret key — set that directly in the DB or via a safer channel.
        ApiKey::firstOrCreate(
            ['provider' => 'paystack', 'mode' => 'test'],
            ['secret_key' => 'sk_test_REPLACE_ME', 'public_key' => 'pk_test_REPLACE_ME']
        );
    }
}

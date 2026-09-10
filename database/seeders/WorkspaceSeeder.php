<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\Room;
use App\Models\WorkspaceSession;
use Illuminate\Database\Seeder;

// Seeded to match your live business rules: 3 active plans (September 2-in-1
// Promo, Basic Package, Free Access Wednesday), 3 customer-selectable
// sessions per day, and real pricing.
//
// IDEMPOTENT: every create() below is firstOrCreate()/updateOrCreate() keyed
// on the natural identity of the row (name, or plan+room+duration name), and
// every pivot attach() is syncWithoutDetaching(). Running this seeder twice
// must never create duplicate plans, rooms, sessions, or durations — that's
// exactly what happened before this fix, which is why "Basic Package" and
// "September 2-in-1 Promo" were showing twice in the booking flow.
class WorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            1 => Room::firstOrCreate(['name' => 'Conference Room'], ['seat_start' => 1, 'seat_end' => 20]),
            2 => Room::firstOrCreate(['name' => 'Workspace 1'], ['seat_start' => 21, 'seat_end' => 30]),
            3 => Room::firstOrCreate(['name' => 'Private Space'], ['seat_start' => 31, 'seat_end' => 40]),
            4 => Room::firstOrCreate(['name' => 'Dedicated Space'], ['seat_start' => 41, 'seat_end' => 50]),
            6 => Room::firstOrCreate(['name' => 'Phone Workspace'], ['seat_start' => 61, 'seat_end' => 70]),
        ];

        // Three daily time-slot sessions customers choose from when booking.
        // The third is overnight — end_time earlier than start_time signals
        // that to WorkspaceScheduleService.
        $sessionMorning = WorkspaceSession::firstOrCreate(['name' => '9am - 3pm'], ['start_time' => '09:00:00', 'end_time' => '15:00:00']);
        $sessionEvening = WorkspaceSession::firstOrCreate(['name' => '3:30pm - 9pm'], ['start_time' => '15:30:00', 'end_time' => '21:00:00']);
        $sessionOvernight = WorkspaceSession::firstOrCreate(['name' => '10pm - 7am'], ['start_time' => '22:00:00', 'end_time' => '07:00:00']);

        $basic = Plan::firstOrCreate(
            ['name' => 'Basic Package'],
            ['requires_seat_selection' => true, 'is_automatic_daily' => false, 'promo_fixed_end_date' => null, 'restricted_weekday' => null]
        );

        $promo = Plan::firstOrCreate(
            ['name' => 'September 2-in-1 Promo'],
            [
                'requires_seat_selection' => true,
                'is_automatic_daily' => false,
                // Every booking under this promo ends here, whatever duration or
                // date in September it was booked on.
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

        // Basic Package and the Promo: bookable in Conference Room, Workspace 1,
        // and Private Space, any of the three sessions.
        foreach ([$rooms[1], $rooms[2], $rooms[3]] as $room) {
            $basic->rooms()->syncWithoutDetaching([$room->id]);
            $promo->rooms()->syncWithoutDetaching([$room->id]);
        }
        foreach ([$sessionMorning, $sessionEvening, $sessionOvernight] as $session) {
            $basic->sessions()->syncWithoutDetaching([$session->id]);
            $promo->sessions()->syncWithoutDetaching([$session->id]);
        }

        // Free Access Wednesday: Conference Room only, morning session only.
        $freeWednesday->rooms()->syncWithoutDetaching([$rooms[1]->id]);
        $freeWednesday->sessions()->syncWithoutDetaching([$sessionMorning->id]);

        $basicPricing = [
            1 => ['1 Day' => ['days' => 1, 'price' => 1500], '1 Week' => ['days' => 7, 'price' => 9000], '1 Month' => ['days' => 30, 'price' => 30000]],
            2 => ['1 Day' => ['days' => 1, 'price' => 2000], '1 Week' => ['days' => 7, 'price' => 12000], '1 Month' => ['days' => 30, 'price' => 40000]],
            3 => ['1 Day' => ['days' => 1, 'price' => 2000], '1 Week' => ['days' => 7, 'price' => 12000], '1 Month' => ['days' => 30, 'price' => 40000]],
        ];

        foreach ($basicPricing as $roomKey => $durations) {
            foreach ($durations as $name => $d) {
                PlanDuration::updateOrCreate(
                    ['plan_id' => $basic->id, 'room_id' => $rooms[$roomKey]->id, 'name' => $name],
                    ['workspace_session_id' => null, 'days' => $d['days'], 'price' => $d['price'], 'internet_price' => 0]
                );
            }
        }

        foreach ([1, 2, 3] as $roomKey) {
            PlanDuration::updateOrCreate(
                ['plan_id' => $promo->id, 'room_id' => $rooms[$roomKey]->id, 'name' => 'September 2-in-1 Promo'],
                ['workspace_session_id' => null, 'days' => 1, 'price' => 30000, 'internet_price' => 0]
            );
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

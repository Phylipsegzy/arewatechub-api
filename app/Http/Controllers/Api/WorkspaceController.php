<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Plan;
use App\Services\WorkspaceScheduleService;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    /**
     * Only plans currently open for booking, promo shown first.
     */
    public function plans()
    {
        $plans = Plan::whereIn('name', ['September 2-in-1 Promo', 'Basic Package', 'Free Access Wednesday'])
            ->orderByRaw("FIELD(name, 'September 2-in-1 Promo', 'Basic Package', 'Free Access Wednesday')")
            ->get(['id', 'name', 'requires_seat_selection', 'promo_fixed_end_date', 'restricted_weekday']);

        return response()->json($plans);
    }

    /**
     * Rooms actually bookable under this plan, in the fixed display order
     * your team already uses.
     */
    public function rooms(Plan $plan)
    {
        $rooms = $plan->rooms()
            ->orderByRaw("FIELD(name, 'Conference Room', 'Workspace 1', 'Private Space')")
            ->get(['rooms.id', 'rooms.name', 'rooms.seat_start', 'rooms.seat_end']);

        return response()->json($rooms);
    }

    /**
     * Sessions the customer can choose from for this plan (most plans allow
     * all three; Free Access Wednesday only allows the morning session).
     */
    public function sessions(Plan $plan)
    {
        return response()->json(
            $plan->sessions()->get(['workspace_sessions.id', 'workspace_sessions.name', 'workspace_sessions.start_time', 'workspace_sessions.end_time'])
        );
    }

    /**
     * Duration options for a plan+room.
     */
    public function durations(Request $request, Plan $plan)
    {
        $request->validate(['room_id' => 'required|exists:rooms,id']);

        $durations = $plan->durations()
            ->where('room_id', $request->room_id)
            ->select('id', 'name', 'days', 'price')
            ->groupBy('id', 'name', 'days', 'price')
            ->orderByRaw("FIELD(name, '1 Day', '1 Week', '1 Month')")
            ->get()
            ->unique('name')
            ->values();

        return response()->json($durations);
    }

    /**
     * Live seat availability for a plan+room+duration+session+date. A seat is
     * unavailable if an active (non-cancelled) booking on that room overlaps
     * the requested time window.
     */
    public function availability(Request $request, WorkspaceScheduleService $scheduler)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'room_id' => 'required|exists:rooms,id',
            'plan_duration_id' => 'required|exists:plan_durations,id',
            'workspace_session_id' => 'required|exists:workspace_sessions,id',
            'date' => 'required|date',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $duration = $plan->durations()->findOrFail($request->plan_duration_id);
        $room = $plan->rooms()->findOrFail($request->room_id);
        $session = $plan->sessions()->findOrFail($request->workspace_session_id);

        if ($weekdayError = $this->checkRestrictedWeekday($plan, $request->date)) {
            return $weekdayError;
        }

        $schedule = $scheduler->build($plan, $duration, $session, $request->date);

        $takenSeats = Booking::where('room_id', $room->id)
            ->where('status', '!=', 'cancelled')
            ->where('start_datetime', '<', $schedule['end_datetime'])
            ->where('end_datetime', '>', $schedule['start_datetime'])
            ->pluck('seat_number')
            ->all();

        $availableSeats = collect(range($room->seat_start, $room->seat_end))
            ->diff($takenSeats)
            ->values();

        return response()->json([
            'available_seats' => $availableSeats,
            'session_name' => $session->name,
            'start_datetime' => $schedule['start_datetime']->toDateTimeString(),
            'end_datetime' => $schedule['end_datetime']->toDateTimeString(),
            'end_date' => $schedule['end_date'],
        ]);
    }

    public function checkRestrictedWeekday(Plan $plan, string $date)
    {
        if ($plan->isBookableOn($date)) {
            return null;
        }

        return response()->json(['message' => "{$plan->name} can only be booked for a {$plan->restrictedDayName()}."], 422);
    }
}

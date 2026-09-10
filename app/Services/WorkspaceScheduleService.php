<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanDuration;
use App\Models\WorkspaceSession;
use Carbon\Carbon;

/**
 * Computes the actual start/end datetime for a booking, given the plan,
 * duration, the SESSION the customer picked (9am-3pm, 3:30pm-9pm, or the
 * overnight 10pm-7am), and the start date.
 *
 * A plan can still carry a `promo_fixed_end_date` (e.g. the September 2-in-1
 * Promo) that overrides whatever the duration would normally compute, so
 * every booking under that plan ends on the same calendar date — 2026-10-31 —
 * regardless of when in September it was booked or which duration was bought.
 */
class WorkspaceScheduleService
{
    public function build(Plan $plan, PlanDuration $duration, WorkspaceSession $session, string $startDate): array
    {
        $start = Carbon::parse($startDate);
        $startDatetime = $start->copy()->setTimeFromTimeString($session->start_time);

        if ($plan->promo_fixed_end_date) {
            $end = Carbon::parse($plan->promo_fixed_end_date);
        } else {
            $end = $start->copy()->addDays(max($duration->days - 1, 0));
        }

        $endDatetime = $end->copy()->setTimeFromTimeString($session->end_time);

        // Overnight session (e.g. 22:00 -> 07:00): the end time is earlier
        // in the clock than the start time, so it actually lands the next
        // calendar day.
        if ($this->isOvernight($session)) {
            $endDatetime->addDay();
        }

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
        ];
    }

    protected function isOvernight(WorkspaceSession $session): bool
    {
        return $session->end_time <= $session->start_time;
    }
}

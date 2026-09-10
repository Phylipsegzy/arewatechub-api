<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\InternetAccount;
use App\Models\UserInternetAccess;
use Illuminate\Support\Facades\DB;

class InternetAccessService
{
    /**
     * Assigns an available internet account to a newly-confirmed booking.
     * Maps the booking's duration name to daily/weekly/monthly credentials.
     * If the pool is empty, this quietly does nothing rather than blocking
     * the booking — a workspace booking without WiFi credentials yet is
     * recoverable (admin can assign one manually); a failed booking isn't.
     */
    public function assign(Booking $booking): ?UserInternetAccess
    {
        $durationType = $this->mapDurationType($booking->plan->durations()->find($booking->plan_duration_id)?->name);

        return DB::transaction(function () use ($booking, $durationType) {
            $account = InternetAccount::where('duration_type', $durationType)
                ->where('status', 'available')
                ->lockForUpdate()
                ->first();

            // Nothing free right now — reclaim any account whose previous
            // assignment has already expired instead of leaving it stuck.
            if (! $account) {
                $expiredAccountId = UserInternetAccess::where('end_date', '<', now()->toDateString())
                    ->whereHas('internetAccount', fn ($q) => $q->where('duration_type', $durationType)->where('status', 'assigned'))
                    ->orderBy('end_date')
                    ->value('internet_account_id');

                if ($expiredAccountId) {
                    $account = InternetAccount::lockForUpdate()->find($expiredAccountId);
                }
            }

            if (! $account) {
                return null;
            }

            $account->update(['status' => 'assigned', 'last_assigned' => now()->toDateString()]);

            return UserInternetAccess::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'internet_account_id' => $account->id,
                'start_date' => $booking->start_date,
                'end_date' => $booking->end_date,
            ]);
        });
    }

    protected function mapDurationType(?string $durationName): string
    {
        return match ($durationName) {
            '1 Week' => 'weekly',
            '1 Month' => 'monthly',
            default => 'daily', // "1 Day" and the single-day Promo duration
        };
    }
}

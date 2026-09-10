<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Only admins can cancel a booking (per your instruction — customers can't
 * cancel their own). This is the one place that logic lives, so the refund
 * and WiFi-account release always happen together, however cancellation is
 * triggered from the admin panel.
 */
class BookingRefundService
{
    public function cancelAndRefund(Booking $booking, string $reason = 'Booking cancelled by admin'): Booking
    {
        DB::transaction(function () use ($booking, $reason) {
            $customer = $booking->customer()->lockForUpdate()->first();
            $newBalance = $customer->wallet_balance + $booking->price;

            WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'credit',
                'amount' => $booking->price,
                'balance_before' => $customer->wallet_balance,
                'balance_after' => $newBalance,
                'source' => 'refund',
                'reference' => 'REFUND-' . Str::upper(Str::random(12)),
                'status' => 'successful',
                'description' => $reason . " (booking #{$booking->id})",
            ]);

            $customer->update(['wallet_balance' => $newBalance]);
            $booking->update(['status' => 'cancelled']);
        });

        if ($access = $booking->internetAccess) {
            $access->internetAccount()->update(['status' => 'available']);
        }

        return $booking->fresh();
    }
}

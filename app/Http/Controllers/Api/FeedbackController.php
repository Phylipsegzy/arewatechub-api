<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingFeedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    /**
     * Bookings that have ended, belong to the current customer, and don't
     * have feedback yet — the dashboard shows a "Rate your visit" prompt for
     * the most recent one of these.
     */
    public function pending(Request $request)
    {
        $booking = $request->user()->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('end_datetime', '<', now())
            ->whereDoesntHave('feedback')
            ->with('room:id,name')
            ->latest('end_datetime')
            ->first();

        return response()->json($booking);
    }

    public function store(Request $request, Booking $booking)
    {
        if ($booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your booking'], 403);
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $feedback = BookingFeedback::updateOrCreate(
            ['booking_id' => $booking->id],
            ['customer_id' => $request->user()->id, 'rating' => $request->rating, 'comment' => $request->comment]
        );

        return response()->json(['message' => 'Thanks for your feedback!', 'feedback' => $feedback], 201);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\WalletTransaction;
use App\Services\BookingRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBookingController extends Controller
{
    public function bookings(Request $request)
    {
        $bookings = Booking::with(['customer:id,firstname,lastname,email', 'plan:id,name', 'room:id,name', 'workspaceSession:id,name'])
            ->latest()
            ->paginate(30);

        return response()->json($bookings);
    }

    /**
     * Customers can't cancel their own bookings — only admin staff can, and
     * only cancellation triggers a refund. Every other status change (e.g.
     * marking a booking "completed") is a plain update.
     */
    public function updateBookingStatus(Request $request, Booking $booking, BookingRefundService $refundService)
    {
        $request->validate(['status' => 'required|in:pending,confirmed,cancelled,completed']);

        if ($request->status === 'cancelled' && $booking->status !== 'cancelled') {
            $updated = $refundService->cancelAndRefund($booking, 'Cancelled by admin');
            return response()->json($updated);
        }

        $wasAlreadyCompleted = $booking->status === 'completed';
        $booking->update(['status' => $request->status]);

        // First time marked completed — send the survey email. Guarded so
        // toggling status back and forth doesn't spam the customer.
        if ($request->status === 'completed' && ! $wasAlreadyCompleted) {
            $this->sendSurveyEmail($booking->fresh(['customer', 'room']));
        }

        return response()->json($booking->fresh());
    }

    protected function sendSurveyEmail(Booking $booking): void
    {
        try {
            $surveyLink = config('services.frontend_url') . '/dashboard?survey=' . $booking->id;
            \Mail::to($booking->customer->email)->send(new \App\Mail\WorkspaceSurveyMail($booking, $surveyLink));
        } catch (\Throwable $e) {
            \Log::warning('Survey email failed to send: ' . $e->getMessage());
        }
    }

    /**
     * Moves a confirmed booking to a different date — only allowed if the
     * booking's current date isn't today (once it's underway, it's too late
     * to move; cancel and rebook instead). Keeps the same plan/room/seat/
     * session and simply recomputes the schedule for the new date, checking
     * the seat is actually free then.
     */
    public function rescheduleBooking(Request $request, Booking $booking, \App\Services\WorkspaceScheduleService $scheduler)
    {
        $request->validate(['new_date' => 'required|date|after_or_equal:today']);

        if ($booking->start_date === now()->toDateString()) {
            return response()->json(['message' => "This booking starts today and can no longer be rescheduled."], 422);
        }

        if (! in_array($booking->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Only pending or confirmed bookings can be rescheduled.'], 422);
        }

        $plan = $booking->plan;
        $duration = $plan->durations()->find($booking->plan_duration_id);
        $session = $booking->workspaceSession;

        if (! $plan->isBookableOn($request->new_date)) {
            return response()->json(['message' => "{$plan->name} can only be booked for a {$plan->restrictedDayName()}."], 422);
        }

        $schedule = $scheduler->build($plan, $duration, $session, $request->new_date);

        $seatTaken = Booking::where('room_id', $booking->room_id)
            ->where('seat_number', $booking->seat_number)
            ->where('id', '!=', $booking->id)
            ->where('status', '!=', 'cancelled')
            ->where('start_datetime', '<', $schedule['end_datetime'])
            ->where('end_datetime', '>', $schedule['start_datetime'])
            ->exists();

        if ($seatTaken) {
            return response()->json(['message' => 'That seat is already booked for the new date.'], 422);
        }

        $booking->update([
            'start_date' => $schedule['start_date'],
            'end_date' => $schedule['end_date'],
            'start_datetime' => $schedule['start_datetime'],
            'end_datetime' => $schedule['end_datetime'],
        ]);

        if ($access = $booking->internetAccess) {
            $access->update(['start_date' => $schedule['start_date'], 'end_date' => $schedule['end_date']]);
        }

        return response()->json(['message' => 'Booking rescheduled', 'booking' => $booking->fresh()]);
    }

    public function customers(Request $request)
    {
        $query = Customer::select('id', 'firstname', 'lastname', 'email', 'phone', 'wallet_balance', 'created_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(30));
    }

    /**
     * Manually credit or debit a customer's wallet — the front-desk cash
     * flow. A walk-in customer pays cash, staff adds it to their wallet here,
     * and from that point on it behaves exactly like any other wallet funds
     * (bookings, courses, etc. all debit from the same balance).
     */
    public function adjustWallet(Request $request, Customer $customer)
    {
        $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
        ]);

        if ($request->type === 'debit' && $customer->wallet_balance < $request->amount) {
            return response()->json(['message' => 'Customer wallet balance is lower than the debit amount'], 422);
        }

        $transaction = DB::transaction(function () use ($request, $customer) {
            $locked = Customer::where('id', $customer->id)->lockForUpdate()->first();
            $newBalance = $request->type === 'credit'
                ? $locked->wallet_balance + $request->amount
                : $locked->wallet_balance - $request->amount;

            $transaction = WalletTransaction::create([
                'customer_id' => $locked->id,
                'type' => $request->type,
                'amount' => $request->amount,
                'balance_before' => $locked->wallet_balance,
                'balance_after' => $newBalance,
                'source' => 'admin_adjustment',
                'reference' => 'ADMIN-' . strtoupper(\Illuminate\Support\Str::random(12)),
                'status' => 'successful',
                'description' => $request->description,
            ]);

            $locked->update(['wallet_balance' => $newBalance]);

            return $transaction;
        });

        return response()->json(['message' => 'Wallet updated', 'transaction' => $transaction, 'wallet_balance' => $customer->fresh()->wallet_balance]);
    }

    /**
     * Books a workspace on behalf of any customer — for walk-ins staff are
     * handling directly. Can either charge the customer's wallet (fails if
     * insufficient, same as self-service booking) or comp it for free.
     */
    public function bookForCustomer(Request $request, \App\Services\WorkspaceScheduleService $scheduler, \App\Services\InternetAccessService $internetAccess)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'plan_id' => 'required|exists:plans,id',
            'plan_duration_id' => 'required|exists:plan_durations,id',
            'room_id' => 'required|exists:rooms,id',
            'workspace_session_id' => 'required|exists:workspace_sessions,id',
            'seat_number' => 'required|integer',
            'start_date' => 'required|date',
            'charge_wallet' => 'required|boolean',
        ]);

        $customer = Customer::findOrFail($request->customer_id);
        $plan = \App\Models\Plan::findOrFail($request->plan_id);
        $duration = $plan->durations()->where('room_id', $request->room_id)->findOrFail($request->plan_duration_id);
        $room = $plan->rooms()->findOrFail($request->room_id);
        $session = $plan->sessions()->findOrFail($request->workspace_session_id);
        $price = (float) $duration->price;

        if (! $plan->isBookableOn($request->start_date)) {
            return response()->json(['message' => "{$plan->name} can only be booked for a {$plan->restrictedDayName()}."], 422);
        }

        if ($request->charge_wallet && $customer->wallet_balance < $price) {
            return response()->json(['message' => 'Customer wallet balance is insufficient. Fund their wallet first or book without charging.'], 422);
        }

        $schedule = $scheduler->build($plan, $duration, $session, $request->start_date);

        $seatTaken = Booking::where('room_id', $room->id)
            ->where('seat_number', $request->seat_number)
            ->where('status', '!=', 'cancelled')
            ->where('start_datetime', '<', $schedule['end_datetime'])
            ->where('end_datetime', '>', $schedule['start_datetime'])
            ->exists();

        if ($seatTaken) {
            return response()->json(['message' => 'That seat is already booked for this date/session'], 422);
        }

        $booking = DB::transaction(function () use ($customer, $plan, $duration, $room, $session, $request, $schedule, $price) {
            $booking = Booking::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'plan_duration_id' => $duration->id,
                'workspace_session_id' => $session->id,
                'room_id' => $room->id,
                'seat_number' => $request->seat_number,
                'price' => $price,
                'status' => 'confirmed',
                'start_date' => $schedule['start_date'],
                'end_date' => $schedule['end_date'],
                'start_datetime' => $schedule['start_datetime'],
                'end_datetime' => $schedule['end_datetime'],
            ]);

            $reference = 'ADMINBOOK-' . strtoupper(\Illuminate\Support\Str::random(12));

            \App\Models\Order::create([
                'customer_id' => $customer->id,
                'orderable_type' => Booking::class,
                'orderable_id' => $booking->id,
                'amount' => $price,
                'payment_method' => $request->charge_wallet ? 'wallet' : 'comp',
                'reference' => $reference,
                'status' => 'success',
            ]);

            if ($request->charge_wallet) {
                $newBalance = $customer->wallet_balance - $price;

                WalletTransaction::create([
                    'customer_id' => $customer->id,
                    'type' => 'debit',
                    'amount' => $price,
                    'balance_before' => $customer->wallet_balance,
                    'balance_after' => $newBalance,
                    'source' => 'booking_payment',
                    'reference' => $reference,
                    'status' => 'successful',
                    'description' => "Admin booking #{$booking->id} (charged to wallet)",
                ]);

                $customer->update(['wallet_balance' => $newBalance]);
            }

            return $booking;
        });

        $confirmed = $booking->fresh(['customer', 'room', 'plan', 'workspaceSession']);
        $internetAccess->assign($confirmed);

        return response()->json(['message' => 'Booking created', 'booking' => $confirmed->load('internetAccess')], 201);
    }

    /**
     * Workspace catalog for the admin booking form — same data shape the
     * customer-facing booking flow uses.
     */
    public function workspaceOptions()
    {
        $plans = \App\Models\Plan::whereIn('name', ['September 2-in-1 Promo', 'Basic Package', 'Free Access Wednesday'])
            ->with(['rooms:id,name,seat_start,seat_end'])
            ->get(['id', 'name', 'promo_fixed_end_date', 'restricted_weekday']);

        return response()->json($plans);
    }

    public function workspaceDurations(Request $request, \App\Models\Plan $plan)
    {
        $request->validate(['room_id' => 'required|exists:rooms,id']);

        $durations = $plan->durations()->where('room_id', $request->room_id)->get(['id', 'name', 'days', 'price']);

        return response()->json($durations);
    }

    public function workspaceSessions(\App\Models\Plan $plan)
    {
        return response()->json(
            $plan->sessions()->get(['workspace_sessions.id', 'workspace_sessions.name', 'workspace_sessions.start_time', 'workspace_sessions.end_time'])
        );
    }

    /**
     * Same seat-availability logic the customer booking flow uses, so the
     * admin's "Book for Customer" form can show the same taken/available
     * seat grid instead of a bare number input.
     */
    public function workspaceAvailability(Request $request, \App\Services\WorkspaceScheduleService $scheduler)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'room_id' => 'required|exists:rooms,id',
            'plan_duration_id' => 'required|exists:plan_durations,id',
            'workspace_session_id' => 'required|exists:workspace_sessions,id',
            'date' => 'required|date',
        ]);

        $plan = \App\Models\Plan::findOrFail($request->plan_id);
        $duration = $plan->durations()->findOrFail($request->plan_duration_id);
        $room = $plan->rooms()->findOrFail($request->room_id);
        $session = $plan->sessions()->findOrFail($request->workspace_session_id);

        if (! $plan->isBookableOn($request->date)) {
            return response()->json(['message' => "{$plan->name} can only be booked for a {$plan->restrictedDayName()}."], 422);
        }

        $schedule = $scheduler->build($plan, $duration, $session, $request->date);

        $takenSeats = Booking::where('room_id', $room->id)
            ->where('status', '!=', 'cancelled')
            ->where('start_datetime', '<', $schedule['end_datetime'])
            ->where('end_datetime', '>', $schedule['start_datetime'])
            ->pluck('seat_number')
            ->all();

        $availableSeats = collect(range($room->seat_start, $room->seat_end))->diff($takenSeats)->values();

        return response()->json([
            'available_seats' => $availableSeats,
            'session_name' => $session->name,
            'start_datetime' => $schedule['start_datetime']->toDateTimeString(),
            'end_datetime' => $schedule['end_datetime']->toDateTimeString(),
            'end_date' => $schedule['end_date'],
        ]);
    }

    public function feedback()
    {
        $feedback = \App\Models\BookingFeedback::with(['customer:id,firstname,lastname', 'booking:id,room_id', 'booking.room:id,name'])
            ->latest()
            ->paginate(30);

        return response()->json($feedback);
    }

    public function overview()
    {
        $today = now()->toDateString();

        return response()->json([
            'total_customers' => Customer::count(),
            'total_bookings' => Booking::count(),
            'bookings_today' => Booking::whereDate('created_at', $today)->count(),
            'active_bookings_today' => Booking::whereDate('start_date', $today)->where('status', 'confirmed')->count(),
            'total_wallet_balance' => Customer::sum('wallet_balance'),
            'revenue_today' => WalletTransaction::where('source', 'booking_payment')
                ->whereDate('created_at', $today)
                ->sum('amount'),
            'pending_wallet_fundings' => WalletTransaction::where('source', 'bank_transfer')->where('status', 'pending')->count(),
            'recent_activity' => WalletTransaction::with('customer:id,firstname,lastname')
                ->latest()
                ->limit(10)
                ->get(['id', 'customer_id', 'type', 'amount', 'source', 'status', 'created_at']),
        ]);
    }

    /**
     * Pending manual bank-transfer wallet fundings that need a human to confirm
     * the money actually arrived before crediting the customer's wallet.
     */
    public function pendingWalletFundings(Request $request)
    {
        $pending = WalletTransaction::with('customer:id,firstname,lastname,email')
            ->where('source', 'bank_transfer')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json($pending);
    }

    public function approveWalletFunding(Request $request, WalletTransaction $walletTransaction)
    {
        if ($walletTransaction->status !== 'pending') {
            return response()->json(['message' => 'Already processed'], 422);
        }

        DB::transaction(function () use ($walletTransaction) {
            $customer = $walletTransaction->customer()->lockForUpdate()->first();
            $newBalance = $customer->wallet_balance + $walletTransaction->amount;

            $walletTransaction->update([
                'status' => 'successful',
                'balance_before' => $customer->wallet_balance,
                'balance_after' => $newBalance,
            ]);

            $customer->update(['wallet_balance' => $newBalance]);
        });

        $walletTransaction->refresh();

        try {
            \Mail::to($walletTransaction->customer->email)->send(new \App\Mail\WalletFundedMail($walletTransaction));
        } catch (\Throwable $e) {
            \Log::warning('Wallet-funded email failed to send: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Wallet credited',
            'transaction' => $walletTransaction,
            'wallet_balance' => $walletTransaction->customer->wallet_balance,
        ]);
    }

    public function rejectWalletFunding(Request $request, WalletTransaction $walletTransaction)
    {
        $walletTransaction->update(['status' => 'failed']);

        return response()->json(['message' => 'Rejected', 'transaction' => $walletTransaction->fresh()]);
    }
}

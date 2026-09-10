<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Plan;
use App\Models\WalletTransaction;
use App\Services\InternetAccessService;
use App\Services\WorkspaceScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 5), 50);

        return response()->json(
            $request->user()->bookings()->with(['plan', 'room', 'workspaceSession', 'internetAccess'])->latest()->paginate($perPage)
        );
    }

    /**
     * Creates and pays for a booking — wallet only (see WorkspaceController
     * for plan/room/session/duration catalog endpoints). If the wallet
     * doesn't have enough, nothing is created at all — no orphaned pending
     * rows — the customer just gets told exactly how much more they need.
     *
     * Customers cannot cancel their own bookings — only an admin can
     * (see AdminBookingController::updateBookingStatus), which is also where
     * the refund happens.
     *
     * CONCURRENCY: the seat-availability check, balance check, and booking
     * insert all happen inside one atomic cache lock keyed to this exact
     * room+seat+date, so two customers double-clicking "Book" on the same
     * seat at the same instant can't both pass the check before either row
     * exists.
     */
    public function store(Request $request, WorkspaceScheduleService $scheduler)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'plan_duration_id' => 'required|exists:plan_durations,id',
            'room_id' => 'required|exists:rooms,id',
            'workspace_session_id' => 'required|exists:workspace_sessions,id',
            'seat_number' => 'required|integer',
            'start_date' => 'required|date|after_or_equal:today',
        ]);

        $customer = $request->user();
        $plan = Plan::findOrFail($request->plan_id);
        $duration = $plan->durations()->where('room_id', $request->room_id)->findOrFail($request->plan_duration_id);
        $room = $plan->rooms()->findOrFail($request->room_id);
        $session = $plan->sessions()->findOrFail($request->workspace_session_id);

        if (! $plan->isBookableOn($request->start_date)) {
            return response()->json(['message' => "{$plan->name} can only be booked for a {$plan->restrictedDayName()}."], 422);
        }

        if ($request->seat_number < $room->seat_start || $request->seat_number > $room->seat_end) {
            return response()->json(['message' => 'Invalid seat for this room'], 422);
        }

        $schedule = $scheduler->build($plan, $duration, $session, $request->start_date);
        $price = (float) $duration->price;

        // Refresh the customer's balance right before checking — $customer
        // was loaded when the request authenticated, which could be stale
        // by the time we get here.
        $customer->refresh();

        if ($customer->wallet_balance < $price) {
            return response()->json([
                'message' => 'Insufficient wallet balance. Please fund your wallet first.',
                'wallet_balance' => $customer->wallet_balance,
                'amount_needed' => $price,
                'shortfall' => round($price - $customer->wallet_balance, 2),
            ], 422);
        }

        $lockKey = "booking-lock:{$room->id}:{$request->seat_number}:{$request->start_date}";
        $lock = Cache::lock($lockKey, 10);

        try {
            return $lock->block(5, function () use ($room, $session, $request, $schedule, $customer, $plan, $duration, $price) {
                $seatTaken = Booking::where('room_id', $room->id)
                    ->where('seat_number', $request->seat_number)
                    ->where('status', '!=', 'cancelled')
                    ->where('start_datetime', '<', $schedule['end_datetime'])
                    ->where('end_datetime', '>', $schedule['start_datetime'])
                    ->exists();

                if ($seatTaken) {
                    return response()->json(['message' => 'That seat was just taken for this date/session. Please pick another.'], 422);
                }

                // Re-check balance inside the lock too — another request for
                // a different seat could have spent the balance in between.
                $customer->refresh();
                if ($customer->wallet_balance < $price) {
                    return response()->json([
                        'message' => 'Insufficient wallet balance. Please fund your wallet first.',
                        'wallet_balance' => $customer->wallet_balance,
                        'amount_needed' => $price,
                        'shortfall' => round($price - $customer->wallet_balance, 2),
                    ], 422);
                }

                $booking = DB::transaction(function () use ($room, $session, $request, $schedule, $customer, $plan, $duration, $price) {
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

                    $reference = 'BOOKING-' . Str::upper(Str::random(12));

                    Order::create([
                        'customer_id' => $customer->id,
                        'orderable_type' => Booking::class,
                        'orderable_id' => $booking->id,
                        'amount' => $price,
                        'payment_method' => 'wallet',
                        'reference' => $reference,
                        'status' => 'success',
                    ]);

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
                        'description' => "Payment for booking #{$booking->id}",
                    ]);

                    $customer->update(['wallet_balance' => $newBalance]);

                    return $booking;
                });

                $confirmed = $booking->fresh(['customer', 'room', 'plan', 'workspaceSession']);
                app(InternetAccessService::class)->assign($confirmed);
                $this->sendConfirmationEmail($confirmed);

                return response()->json([
                    'message' => 'Booking confirmed',
                    'booking' => $confirmed->load('internetAccess'),
                    'wallet_balance' => $customer->fresh()->wallet_balance,
                ], 201);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return response()->json(['message' => 'This seat is being booked by someone else right now. Please try again in a moment.'], 429);
        }
    }

    /**
     * Printable receipt data for a booking — the frontend renders this as a
     * simple print-friendly page (browser's own print-to-PDF covers the
     * "give me a PDF" need without pulling in a PDF library for one page).
     */
    public function receipt(Request $request, Booking $booking)
    {
        if ($booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your booking'], 403);
        }

        return response()->json(
            $booking->load(['customer', 'plan', 'room', 'workspaceSession', 'internetAccess.internetAccount'])
        );
    }

    protected function sendConfirmationEmail(?Booking $booking): void
    {
        if (! $booking) {
            return;
        }

        try {
            Mail::to($booking->customer->email)->send(new BookingConfirmedMail($booking));
        } catch (\Throwable $e) {
            \Log::warning('Booking confirmation email failed to send: ' . $e->getMessage());
        }

        // Also notify the front desk so staff know a booking just came in —
        // set ADMIN_NOTIFICATION_EMAIL in .env, falls back to your seeded
        // admin login email if that's not set.
        try {
            $adminEmail = config('services.admin_notification_email') ?? \App\Models\Admin::query()->value('email');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new \App\Mail\AdminNewBookingMail($booking));
            }
        } catch (\Throwable $e) {
            \Log::warning('Admin new-booking email failed to send: ' . $e->getMessage());
        }

        try {
            app(\App\Services\PushNotificationService::class)->notifyAllAdmins(
                'New booking',
                "{$booking->customer->firstname} {$booking->customer->lastname} booked {$booking->room->name}",
                '/admin?tab=bookings'
            );
        } catch (\Throwable $e) {
            \Log::warning('Admin push notification failed to send: ' . $e->getMessage());
        }
    }
}

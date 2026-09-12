<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminNewTeenRegistrationMail;
use App\Mail\TeenProgramFeePaidMail;
use App\Mail\TeenProgramRegisteredMail;
use App\Mail\TeenProgramVipUpgradeMail;
use App\Models\TeenProgramPayment;
use App\Models\TeenProgramRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TeenProgramController extends Controller
{
    // Total slots across the whole camp. "Per category" doesn't apply
    // cleanly anymore under the current pricing model (VIP is an optional
    // add-on to a single registration, not a separate signup path), so this
    // is one shared pool — matches the clearer of the two claims on the
    // marketing copy ("Only 20 slots available. First registered, first
    // admitted."). A slot is reserved at REGISTRATION time, not payment
    // time, matching that "first registered" wording.
    const TOTAL_SLOTS = 20;

    /**
     * Public — lets the marketing/landing page show live availability
     * without requiring login.
     */
    public function slotsRemaining()
    {
        $taken = TeenProgramRegistration::count();
        $remaining = max(self::TOTAL_SLOTS - $taken, 0);

        return response()->json([
            'total' => self::TOTAL_SLOTS,
            'taken' => $taken,
            'remaining' => $remaining,
            'full' => $remaining === 0,
        ]);
    }

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->teenProgramRegistrations()->with('payments')->latest()->get()
        );
    }

    /**
     * Registers a child. Requires the parent to already be logged in (this
     * app's normal auth) — no inline account creation here, unlike the
     * legacy procedural version, since every customer already has an
     * account by the time they'd be doing this.
     *
     * CONCURRENCY: slot-count check + insert happen inside a lock, same
     * pattern as seat booking, so two last-minute registrations can't both
     * slip in past the 20-slot cap at the same instant.
     */
    public function store(Request $request)
    {
        $request->validate([
            'child_firstname' => 'required|string|max:100',
            'child_lastname' => 'required|string|max:100',
            'child_age' => 'required|integer|min:12|max:17',
            'child_gender' => 'nullable|in:Male,Female',
            'school' => 'nullable|string|max:150',
            'parent_name' => 'nullable|string|max:150',
            'parent_phone' => 'nullable|string|max:30',
            'parent_address' => 'required|string|max:255',
            'nearest_landmark' => 'required|string|max:255',
            'relationship' => 'nullable|string|max:50',
        ]);

        $customer = $request->user();

        $lock = Cache::lock('teen-program-registration-slots', 10);

        try {
            return $lock->block(5, function () use ($request, $customer) {
                if (TeenProgramRegistration::count() >= self::TOTAL_SLOTS) {
                    return response()->json([
                        'message' => 'The Future Builders Camp is fully booked — all ' . self::TOTAL_SLOTS . ' slots have been taken.',
                    ], 422);
                }

                // Reuse the parent's address/landmark from a previous
                // registration (same household) if this one didn't include it.
                $previous = TeenProgramRegistration::where('customer_id', $customer->id)->latest()->first();

                $registration = TeenProgramRegistration::create([
                    'customer_id' => $customer->id,
                    'child_firstname' => $request->child_firstname,
                    'child_lastname' => $request->child_lastname,
                    'child_age' => $request->child_age,
                    'child_gender' => $request->child_gender,
                    'school' => $request->school,
                    'parent_name' => $request->parent_name ?: trim("{$customer->firstname} {$customer->lastname}"),
                    'parent_phone' => $request->parent_phone ?: $customer->phone,
                    'parent_address' => $request->parent_address ?: $previous?->parent_address,
                    'nearest_landmark' => $request->nearest_landmark ?: $previous?->nearest_landmark,
                    'relationship' => $request->relationship,
                ]);

                $this->sendRegisteredEmails($registration);

                return response()->json([
                    'message' => "You've registered {$registration->child_firstname} for the Gen Alpha Future Builders Camp! Pay the compulsory ₦" . number_format($registration->registration_amount) . ' registration fee from your wallet to confirm the slot.',
                    'registration' => $registration,
                ], 201);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return response()->json(['message' => 'Please try again in a moment.'], 429);
        }
    }

    /**
     * Pays either the compulsory registration fee or the optional VIP
     * upgrade from the customer's wallet — same shortfall-reporting pattern
     * as workspace bookings, and the same "nothing created until payment
     * actually succeeds" guarantee.
     */
    public function pay(Request $request, TeenProgramRegistration $registration)
    {
        if ($registration->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your registration'], 403);
        }

        $request->validate(['payment_type' => 'required|in:registration,vip']);
        $type = $request->payment_type;
        $customer = $request->user()->fresh();

        if ($type === 'registration') {
            if ($registration->registration_payment_status === 'paid') {
                return response()->json(['message' => 'The registration fee has already been paid.'], 422);
            }
            $amount = (float) $registration->registration_amount;
        } else {
            if ($registration->registration_payment_status !== 'paid') {
                return response()->json(['message' => 'Please complete the compulsory registration fee before upgrading to VIP.'], 422);
            }
            if ($registration->vip_payment_status === 'paid') {
                return response()->json(['message' => 'This registration is already VIP.'], 422);
            }
            $amount = (float) $registration->vip_amount;
        }

        if ($customer->wallet_balance < $amount) {
            return response()->json([
                'message' => 'Insufficient wallet balance. Please fund your wallet first.',
                'wallet_balance' => $customer->wallet_balance,
                'amount_needed' => $amount,
                'shortfall' => round($amount - $customer->wallet_balance, 2),
            ], 422);
        }

        $reference = 'TEEN' . strtoupper($type) . '-' . Str::upper(Str::random(10));

        DB::transaction(function () use ($customer, $registration, $type, $amount, $reference) {
            $newBalance = $customer->wallet_balance - $amount;

            \App\Models\WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $customer->wallet_balance,
                'balance_after' => $newBalance,
                'source' => 'booking_payment',
                'reference' => $reference,
                'status' => 'successful',
                'description' => $type === 'registration'
                    ? "Future Builders Camp registration fee — {$registration->child_firstname} {$registration->child_lastname}"
                    : "Future Builders Camp VIP upgrade — {$registration->child_firstname} {$registration->child_lastname}",
            ]);

            $customer->update(['wallet_balance' => $newBalance]);

            if ($type === 'registration') {
                $registration->update(['registration_payment_status' => 'paid']);
            } else {
                $registration->update(['vip_payment_status' => 'paid', 'vip_requested' => true]);
            }

            TeenProgramPayment::create([
                'customer_id' => $customer->id,
                'registration_id' => $registration->id,
                'payment_type' => $type,
                'amount' => $amount,
                'reference' => $reference,
                'status' => 'Success',
            ]);
        });

        $registration = $registration->fresh();
        $this->sendPaymentEmail($registration, $type);

        $message = $type === 'registration'
            ? "Congratulations! ₦" . number_format($amount) . " has been deducted from your wallet. {$registration->child_firstname}'s slot is now confirmed."
            : "Congratulations! ₦" . number_format($amount) . " has been deducted from your wallet. {$registration->child_firstname} is now upgraded to VIP.";

        return response()->json([
            'message' => $message,
            'registration' => $registration,
            'wallet_balance' => $customer->fresh()->wallet_balance,
        ]);
    }

    public function receipt(Request $request, TeenProgramRegistration $registration)
    {
        if ($registration->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your registration'], 403);
        }
        if ($registration->registration_payment_status !== 'paid') {
            return response()->json(['message' => 'This registration has not been paid for yet.'], 422);
        }

        return response()->json($registration->load(['customer', 'payments']));
    }

    public function admissionLetter(Request $request, TeenProgramRegistration $registration)
    {
        if ($registration->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your registration'], 403);
        }
        if ($registration->registration_payment_status !== 'paid') {
            return response()->json(['message' => 'The registration fee must be paid before the admission letter is available.'], 422);
        }

        return response()->json($registration->load('customer'));
    }

    protected function sendRegisteredEmails(TeenProgramRegistration $registration): void
    {
        try {
            Mail::to($registration->customer->email)->send(new TeenProgramRegisteredMail($registration));
        } catch (\Throwable $e) {
            \Log::warning('Teen program registration email failed: ' . $e->getMessage());
        }

        try {
            $adminEmail = config('services.admin_notification_email') ?? \App\Models\Admin::query()->value('email');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new AdminNewTeenRegistrationMail($registration));
            }
        } catch (\Throwable $e) {
            \Log::warning('Admin teen registration email failed: ' . $e->getMessage());
        }
    }

    protected function sendPaymentEmail(TeenProgramRegistration $registration, string $type): void
    {
        try {
            $mailable = $type === 'registration'
                ? new TeenProgramFeePaidMail($registration)
                : new TeenProgramVipUpgradeMail($registration);

            Mail::to($registration->customer->email)->send($mailable);
        } catch (\Throwable $e) {
            \Log::warning('Teen program payment email failed: ' . $e->getMessage());
        }
    }
}

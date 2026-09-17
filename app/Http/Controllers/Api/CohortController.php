<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminNewCohortEnrollmentMail;
use App\Mail\CohortEnrolledMail;
use App\Mail\CohortFeePaidMail;
use App\Models\CohortEnrollment;
use App\Models\CohortIntake;
use App\Models\CohortProgram;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CohortController extends Controller
{
    const BOOTCAMP_FEE = 60000;
    const NON_BOOTCAMP_FEE = 40000;
    const TUITION_FULL = 250000;

    // "Register and pay before month end, 50% discount — reverts to 100%
    // from October." Taken literally: the discount is keyed to the actual
    // payment date, not the registration date and not which batch is
    // chosen — pay on Sept 30 for a November batch and the discount still
    // applies; pay on Oct 1 for a September-dated enrollment and it
    // doesn't. If you actually meant it tied to each batch's own start
    // date instead, tell me and this becomes a one-line change.
    const DISCOUNT_ENDS_AT = '2026-10-01';

    public static function currentTuitionFee(): float
    {
        return Carbon::now()->lt(Carbon::parse(self::DISCOUNT_ENDS_AT)) ? self::TUITION_FULL / 2 : self::TUITION_FULL;
    }

    public static function bootcampFeeFor(string $option): float
    {
        return $option === 'bootcamp' ? self::BOOTCAMP_FEE : self::NON_BOOTCAMP_FEE;
    }

    /**
     * Public — everything the landing page needs: available courses,
     * batches with live slot counts, and current pricing (so the discount
     * is visible before anyone logs in).
     */
    public function index()
    {
        $program = CohortProgram::where('is_active', true)->with('intakes')->first();

        $courses = [
            'Web Development', 'Data Analysis', 'Cybersecurity',
            'Artificial Intelligence & Machine Learning', 'Digital Marketing',
            'Office Automation', 'Business Branding',
            'PC Software & Hardware with Phone Repair',
            'AI automation and Prompt Engineering', 'AI Video creation and editing',
        ];

        $batches = $program ? $program->intakes->map(fn ($intake) => [
            'id' => $intake->id,
            'name' => $intake->name,
            'start_date' => $intake->start_date->toDateString(),
            'end_date' => $intake->end_date->toDateString(),
            'slots_remaining' => $intake->slotsRemaining(),
            'full' => $intake->slotsRemaining() === 0,
        ]) : [];

        return response()->json([
            'courses' => $courses,
            'batches' => $batches,
            'pricing' => [
                'bootcamp_fee' => self::BOOTCAMP_FEE,
                'non_bootcamp_fee' => self::NON_BOOTCAMP_FEE,
                'tuition_full' => self::TUITION_FULL,
                'tuition_current' => self::currentTuitionFee(),
                'discount_active' => self::currentTuitionFee() < self::TUITION_FULL,
                'discount_ends_at' => self::DISCOUNT_ENDS_AT,
            ],
        ]);
    }

    public function myEnrollment(Request $request)
    {
        // TEMPORARY — proves definitively whether editing THIS method,
        // through the NORMAL deploy pipeline, changes what this route
        // actually serves. Remove once confirmed.
        return response()->json(['UNMISTAKABLE_MARKER' => 'HELLO-WORLD-TEST-99887766']);
    }

    public function myEnrollmentReal(Request $request)
    {
        $enrollment = $request->user()->cohortEnrollments()->with('intake')->latest()->first();

        return response()->json($enrollment);
    }

    public function enroll(Request $request)
    {
        $request->validate([
            'cohort_intake_id' => 'required|exists:cohort_intakes,id',
            'programme_selected' => 'required|string',
            'track_selected' => 'required|string',
            'status_type' => 'required|in:Corp Member,Student,Working Class',
            'state_code' => 'required_if:status_type,Corp Member|nullable|string',
            'matric_number' => 'required_if:status_type,Student|nullable|string',
            'bootcamp_option' => 'required|in:bootcamp,non_bootcamp',
            'education_level' => 'nullable|string',
            'has_laptop' => 'nullable|boolean',
            'motivation' => 'nullable|string',
        ]);

        $customer = $request->user();
        $intake = CohortIntake::findOrFail($request->cohort_intake_id);

        $lock = \Illuminate\Support\Facades\Cache::lock("cohort-intake-{$intake->id}-slots", 10);

        try {
            return $lock->block(5, function () use ($request, $customer, $intake) {
                if ($intake->slotsRemaining() <= 0) {
                    return response()->json(['message' => "{$intake->name} is fully booked."], 422);
                }

                $bootcampFee = self::bootcampFeeFor($request->bootcamp_option);
                $tuitionFee = self::currentTuitionFee();

                $enrollment = CohortEnrollment::create([
                    'cohort_intake_id' => $intake->id,
                    'customer_id' => $customer->id,
                    'track_selected' => $request->track_selected,
                    'programme_selected' => $request->programme_selected,
                    'bootcamp_option' => $request->bootcamp_option,
                    'status_type' => $request->status_type,
                    'tuition_tier' => $tuitionFee < self::TUITION_FULL ? 'discounted' : 'full',
                    'bootcamp_fee' => $bootcampFee,
                    'tuition_fee' => $tuitionFee,
                    'amount_due' => $bootcampFee + $tuitionFee,
                    'state_code' => $request->state_code,
                    'matric_number' => $request->matric_number,
                    'education_level' => $request->education_level,
                    'has_laptop' => (bool) $request->has_laptop,
                    'motivation' => $request->motivation,
                ]);

                $this->sendEnrolledEmails($enrollment);

                $discountNote = $tuitionFee < self::TUITION_FULL
                    ? ' Your tuition is currently 50% off — pay before ' . Carbon::parse(self::DISCOUNT_ENDS_AT)->subDay()->format('F j') . ' to lock in this price.'
                    : '';

                return response()->json([
                    'message' => "You're enrolled in {$intake->name}! Total due: ₦" . number_format($enrollment->amount_due) . '.' . $discountNote,
                    'enrollment' => $enrollment->load('intake'),
                ], 201);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return response()->json(['message' => 'Please try again in a moment.'], 429);
        }
    }

    public function pay(Request $request, CohortEnrollment $enrollment)
    {
        if ($enrollment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your enrollment'], 403);
        }
        if ($enrollment->payment_status === 'paid') {
            return response()->json(['message' => 'This enrollment has already been paid for.'], 422);
        }

        $customer = $request->user()->fresh();

        // Re-checks the discount window at the moment of payment, not
        // whatever was quoted at registration — matches "register AND pay
        // before month end" literally: paying late means paying full price
        // even if they registered during the discount window.
        $tuitionFee = self::currentTuitionFee();
        $amountDue = $enrollment->bootcamp_fee + $tuitionFee;

        if ($amountDue !== (float) $enrollment->amount_due) {
            $enrollment->update([
                'tuition_fee' => $tuitionFee,
                'tuition_tier' => $tuitionFee < self::TUITION_FULL ? 'discounted' : 'full',
                'amount_due' => $amountDue,
            ]);
        }

        if ($customer->wallet_balance < $amountDue) {
            return response()->json([
                'message' => 'Insufficient wallet balance. Please fund your wallet first.',
                'wallet_balance' => $customer->wallet_balance,
                'amount_needed' => $amountDue,
                'shortfall' => round($amountDue - $customer->wallet_balance, 2),
            ], 422);
        }

        $reference = 'COHORT-' . Str::upper(Str::random(10));

        DB::transaction(function () use ($customer, $enrollment, $amountDue, $reference) {
            $newBalance = $customer->wallet_balance - $amountDue;

            WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'debit',
                'amount' => $amountDue,
                'balance_before' => $customer->wallet_balance,
                'balance_after' => $newBalance,
                'source' => 'booking_payment',
                'reference' => $reference,
                'status' => 'successful',
                'description' => "Cohort Programme — {$enrollment->intake->name} ({$enrollment->track_selected})",
            ]);

            $customer->update(['wallet_balance' => $newBalance]);

            $enrollment->update([
                'payment_status' => 'paid',
                'amount_paid' => $amountDue,
                'reference' => $reference,
                'application_status' => 'accepted',
            ]);
        });

        $enrollment = $enrollment->fresh('intake');
        $this->sendFeePaidEmail($enrollment);

        return response()->json([
            'message' => 'Payment successful! ₦' . number_format($amountDue) . ' deducted from your wallet.',
            'enrollment' => $enrollment,
            'wallet_balance' => $customer->fresh()->wallet_balance,
        ]);
    }

    public function receipt(Request $request, CohortEnrollment $enrollment)
    {
        if ($enrollment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your enrollment'], 403);
        }
        if ($enrollment->payment_status !== 'paid') {
            return response()->json(['message' => 'This enrollment has not been paid for yet.'], 422);
        }

        return response()->json($enrollment->load(['intake', 'customer']));
    }

    public function receiptPdf(Request $request, CohortEnrollment $enrollment, \App\Services\PdfService $pdf)
    {
        if ($enrollment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your enrollment'], 403);
        }
        if ($enrollment->payment_status !== 'paid') {
            return response()->json(['message' => 'This enrollment has not been paid for yet.'], 422);
        }

        $enrollment->load(['intake', 'customer']);
        $studentName = trim("{$enrollment->customer->firstname} {$enrollment->customer->lastname}");
        $receiptNo = $enrollment->reference ?: ('RCP-COHORT-' . str_pad((string) $enrollment->id, 5, '0', STR_PAD_LEFT));

        return $pdf->render('pdf.cohort-receipt', [
            'enrollment' => $enrollment,
            'studentName' => $studentName,
            'receiptNo' => $receiptNo,
            'logoSrc' => $pdf->logoDataUri(),
        ], "ArewaTecHub_Cohort_Receipt_{$receiptNo}.pdf");
    }

    protected function sendEnrolledEmails(CohortEnrollment $enrollment): void
    {
        try {
            Mail::to($enrollment->customer->email)->send(new CohortEnrolledMail($enrollment));
        } catch (\Throwable $e) {
            \Log::warning('Cohort enrollment email failed: ' . $e->getMessage());
        }

        try {
            $adminEmail = config('services.admin_notification_email') ?? \App\Models\Admin::query()->value('email');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new AdminNewCohortEnrollmentMail($enrollment));
            }
        } catch (\Throwable $e) {
            \Log::warning('Admin cohort enrollment email failed: ' . $e->getMessage());
        }
    }

    protected function sendFeePaidEmail(CohortEnrollment $enrollment): void
    {
        try {
            Mail::to($enrollment->customer->email)->send(new CohortFeePaidMail($enrollment));
        } catch (\Throwable $e) {
            \Log::warning('Cohort fee paid email failed: ' . $e->getMessage());
        }
    }
}

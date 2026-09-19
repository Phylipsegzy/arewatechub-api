<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminNewAcademyEnrollmentMail;
use App\Mail\CohortEnrolledMail;
use App\Mail\CohortFeePaidMail;
use App\Models\AcademyBatch;
use App\Models\AcademyEnrollment;
use App\Models\AcademyProgram;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AcademyEnrollmentController extends Controller
{
    const BOOTCAMP_FEE = 60000;
    const NON_BOOTCAMP_FEE = 40000;
    const TUITION_FULL = 250000;
    const DISCOUNT_ENDS_AT = '2026-10-01';

    public static function currentTuitionFee(): float
    {
        return Carbon::now()->lt(Carbon::parse(self::DISCOUNT_ENDS_AT)) ? self::TUITION_FULL / 2 : self::TUITION_FULL;
    }

    public static function bootcampFeeFor(string $option): float
    {
        return $option === 'bootcamp' ? self::BOOTCAMP_FEE : self::NON_BOOTCAMP_FEE;
    }

    public function options()
    {
        $program = AcademyProgram::where('is_active', true)->with('batches')->first();

        $courses = [
            'Web Development', 'Data Analysis', 'Cybersecurity',
            'Artificial Intelligence & Machine Learning', 'Digital Marketing',
            'Office Automation', 'Business Branding',
            'PC Software & Hardware with Phone Repair',
            'AI automation and Prompt Engineering', 'AI Video creation and editing',
        ];

        $batches = $program ? $program->batches->map(fn ($batch) => [
            'id' => $batch->id,
            'name' => $batch->name,
            'start_date' => $batch->start_date->toDateString(),
            'end_date' => $batch->end_date->toDateString(),
            'slots_remaining' => $batch->slotsRemaining(),
            'full' => $batch->slotsRemaining() === 0,
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

    public function status(Request $request)
    {
        $enrollment = $request->user()->academyEnrollments()->with('batch')->latest()->first();

        return response()->json($enrollment);
    }

    public function enroll(Request $request)
    {
        $request->validate([
            'academy_batch_id' => 'required|exists:academy_batches,id',
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
        $batch = AcademyBatch::findOrFail($request->academy_batch_id);

        $lock = Cache::lock("academy-batch-{$batch->id}-slots", 10);

        try {
            return $lock->block(5, function () use ($request, $customer, $batch) {
                if ($batch->slotsRemaining() <= 0) {
                    return response()->json(['message' => "{$batch->name} is fully booked."], 422);
                }

                $bootcampFee = self::bootcampFeeFor($request->bootcamp_option);
                $tuitionFee = self::currentTuitionFee();

                $enrollment = AcademyEnrollment::create([
                    'academy_batch_id' => $batch->id,
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
                    'message' => "You're enrolled in {$batch->name}! Total due: ₦" . number_format($enrollment->amount_due) . '.' . $discountNote,
                    'enrollment' => $enrollment->load('batch'),
                ], 201);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return response()->json(['message' => 'Please try again in a moment.'], 429);
        }
    }

    public function pay(Request $request, AcademyEnrollment $enrollment)
    {
        if ($enrollment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your enrollment'], 403);
        }
        if ($enrollment->payment_status === 'paid') {
            return response()->json(['message' => 'This enrollment has already been paid for.'], 422);
        }

        $customer = $request->user()->fresh();

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

        $reference = 'ACAD-' . Str::upper(Str::random(10));

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
                'description' => "Digital Academy — {$enrollment->batch->name} ({$enrollment->track_selected})",
            ]);

            $customer->update(['wallet_balance' => $newBalance]);

            $enrollment->update([
                'payment_status' => 'paid',
                'amount_paid' => $amountDue,
                'reference' => $reference,
                'application_status' => 'accepted',
            ]);
        });

        $enrollment = $enrollment->fresh('batch');
        $this->sendFeePaidEmail($enrollment);

        return response()->json([
            'message' => 'Payment successful! ₦' . number_format($amountDue) . ' deducted from your wallet.',
            'enrollment' => $enrollment,
            'wallet_balance' => $customer->fresh()->wallet_balance,
        ]);
    }

    public function receipt(Request $request, AcademyEnrollment $enrollment)
    {
        if ($enrollment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your enrollment'], 403);
        }
        if ($enrollment->payment_status !== 'paid') {
            return response()->json(['message' => 'This enrollment has not been paid for yet.'], 422);
        }

        return response()->json($enrollment->load(['batch', 'customer']));
    }

    /**
     * Customer's own download. Admin's equivalent (any enrollment, not just
     * their own) lives on AdminBookingController — see academyReceiptPdf.
     */
    public function receiptPdf(Request $request, AcademyEnrollment $enrollment, \App\Services\PdfService $pdf)
    {
        if ($enrollment->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Not your enrollment'], 403);
        }

        return $this->buildReceiptPdf($enrollment, $pdf);
    }

    public function buildReceiptPdf(AcademyEnrollment $enrollment, \App\Services\PdfService $pdf)
    {
        if ($enrollment->payment_status !== 'paid') {
            return response()->json(['message' => 'This enrollment has not been paid for yet.'], 422);
        }

        $enrollment->load(['batch', 'customer']);
        $studentName = trim("{$enrollment->customer->firstname} {$enrollment->customer->lastname}");
        $receiptNo = $enrollment->reference ?: ('RCP-ACAD-' . str_pad((string) $enrollment->id, 5, '0', STR_PAD_LEFT));

        return $pdf->render('pdf.cohort-receipt', [
            'enrollment' => $enrollment,
            'studentName' => $studentName,
            'receiptNo' => $receiptNo,
            'logoSrc' => $pdf->logoDataUri(),
        ], "ArewaTecHub_Academy_Receipt_{$receiptNo}.pdf");
    }

    protected function sendEnrolledEmails(AcademyEnrollment $enrollment): void
    {
        try {
            Mail::to($enrollment->customer->email)->send(new CohortEnrolledMail($enrollment));
        } catch (\Throwable $e) {
            \Log::warning('Academy enrollment email failed: ' . $e->getMessage());
        }

        try {
            $adminEmail = config('services.admin_notification_email') ?? \App\Models\Admin::query()->value('email');
            if ($adminEmail) {
                Mail::to($adminEmail)->send(new AdminNewAcademyEnrollmentMail($enrollment));
            }
        } catch (\Throwable $e) {
            \Log::warning('Admin academy enrollment email failed: ' . $e->getMessage());
        }
    }

    protected function sendFeePaidEmail(AcademyEnrollment $enrollment): void
    {
        try {
            Mail::to($enrollment->customer->email)->send(new CohortFeePaidMail($enrollment));
        } catch (\Throwable $e) {
            \Log::warning('Academy fee paid email failed: ' . $e->getMessage());
        }
    }
}

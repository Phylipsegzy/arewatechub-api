<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminBookingController;
use App\Http\Controllers\Api\Admin\AdminInternetAccountController;
use App\Http\Controllers\Api\Admin\AdminPushController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\TeenProgramController;
use App\Http\Controllers\Api\AcademyEnrollmentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Quick connectivity check — visit http://127.0.0.1:8000/api/health directly
// in your browser. If this doesn't return {"ok":true}, the problem is your
// Laravel server/DB, not the frontend or login logic.
Route::get('/health', fn () => response()->json(['ok' => true, 'time' => now()]));

// --- Public ---
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/password/forgot', [PasswordResetController::class, 'forgot']);
Route::post('/auth/password/reset', [PasswordResetController::class, 'reset']);
Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle']);

Route::get('/workspace/plans', [WorkspaceController::class, 'plans']);
Route::get('/workspace/plans/{plan}/rooms', [WorkspaceController::class, 'rooms']);
Route::get('/workspace/plans/{plan}/sessions', [WorkspaceController::class, 'sessions']);
Route::get('/workspace/plans/{plan}/durations', [WorkspaceController::class, 'durations']);
Route::get('/workspace/availability', [WorkspaceController::class, 'availability']);
Route::get('/teen-program/slots-remaining', [TeenProgramController::class, 'slotsRemaining']);
Route::get('/academy/options', [AcademyEnrollmentController::class, 'options']);

// --- Authenticated (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/password/update', [PasswordResetController::class, 'update']);

    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/bank-details', [WalletController::class, 'bankDetails']);
    Route::post('/wallet/fund/initialize', [WalletController::class, 'initializeFunding']);
    Route::get('/wallet/fund/verify/{reference}', [WalletController::class, 'verifyFunding']);
    Route::post('/wallet/fund/manual', [WalletController::class, 'manualFunding']);
    Route::get('/wallet/dedicated-account', [WalletController::class, 'dedicatedAccount']);
    Route::post('/wallet/dedicated-account', [WalletController::class, 'createDedicatedAccount']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}/receipt', [BookingController::class, 'receipt']);
    Route::get('/bookings/{booking}/receipt/pdf', [BookingController::class, 'receiptPdf']);
    Route::get('/wallet/fundings/{transaction}/receipt/pdf', [WalletController::class, 'fundingReceiptPdf']);

    Route::get('/feedback/pending', [FeedbackController::class, 'pending']);
    Route::post('/bookings/{booking}/feedback', [FeedbackController::class, 'store']);

    Route::get('/teen-program', [TeenProgramController::class, 'index']);
    Route::post('/teen-program', [TeenProgramController::class, 'store']);
    Route::post('/teen-program/{registration}/pay', [TeenProgramController::class, 'pay']);
    Route::get('/teen-program/{registration}/receipt', [TeenProgramController::class, 'receipt']);
    Route::get('/teen-program/{registration}/receipt/pdf', [TeenProgramController::class, 'receiptPdf']);
    Route::get('/teen-program/{registration}/admission-letter', [TeenProgramController::class, 'admissionLetter']);
    Route::get('/teen-program/{registration}/admission-letter/pdf', [TeenProgramController::class, 'admissionLetterPdf']);

    // Rebuilt entirely fresh under /academy/* — the old /cohort/* endpoint
    // stayed inexplicably broken through days of diagnosis even after the
    // route:cache issue was found and fixed. New table, new model, new
    // controller, new route paths — no possible overlap with whatever was
    // stuck before.
    Route::post('/academy/enroll', [AcademyEnrollmentController::class, 'enroll']);
    Route::post('/academy/{enrollment}/pay', [AcademyEnrollmentController::class, 'pay']);
    Route::get('/academy/{enrollment}/receipt', [AcademyEnrollmentController::class, 'receipt']);
    Route::get('/academy/{enrollment}/receipt/pdf', [AcademyEnrollmentController::class, 'receiptPdf']);
    Route::get('/academy/status', [AcademyEnrollmentController::class, 'status']);
});

// --- Admin ---
Route::post('/admin/login', [AdminAuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);

    Route::get('/bookings', [AdminBookingController::class, 'bookings']);
    Route::patch('/bookings/{booking}/status', [AdminBookingController::class, 'updateBookingStatus']);
    Route::patch('/bookings/{booking}/reschedule', [AdminBookingController::class, 'rescheduleBooking']);
    Route::post('/bookings/create-for-customer', [AdminBookingController::class, 'bookForCustomer']);

    Route::get('/workspace/plans', [AdminBookingController::class, 'workspaceOptions']);
    Route::get('/workspace/plans/{plan}/sessions', [AdminBookingController::class, 'workspaceSessions']);
    Route::get('/workspace/plans/{plan}/durations', [AdminBookingController::class, 'workspaceDurations']);
    Route::get('/workspace/availability', [AdminBookingController::class, 'workspaceAvailability']);

    Route::get('/customers', [AdminBookingController::class, 'customers']);
    Route::post('/customers/{customer}/wallet/adjust', [AdminBookingController::class, 'adjustWallet']);

    Route::get('/overview', [AdminBookingController::class, 'overview']);

    Route::get('/wallet-fundings/pending', [AdminBookingController::class, 'pendingWalletFundings']);
    Route::post('/wallet-fundings/{walletTransaction}/approve', [AdminBookingController::class, 'approveWalletFunding']);
    Route::post('/wallet-fundings/{walletTransaction}/reject', [AdminBookingController::class, 'rejectWalletFunding']);

    Route::get('/internet-accounts', [AdminInternetAccountController::class, 'index']);
    Route::post('/internet-accounts', [AdminInternetAccountController::class, 'store']);
    Route::delete('/internet-accounts/{internetAccount}', [AdminInternetAccountController::class, 'destroy']);

    Route::get('/feedback', [AdminBookingController::class, 'feedback']);
    Route::get('/teen-program', [AdminBookingController::class, 'teenProgramRegistrations']);
    Route::get('/academy', [AdminBookingController::class, 'academyEnrollments']);
    Route::get('/academy/{enrollment}/receipt/pdf', [AdminBookingController::class, 'academyReceiptPdf']);
    Route::get('/bookings/{booking}/receipt/pdf', [AdminBookingController::class, 'bookingReceiptPdf']);
    Route::get('/wallet-fundings/{transaction}/receipt/pdf', [AdminBookingController::class, 'walletFundingReceiptPdf']);

    Route::get('/push/vapid-public-key', [AdminPushController::class, 'vapidPublicKey']);
    Route::post('/push/subscribe', [AdminPushController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [AdminPushController::class, 'unsubscribe']);
});

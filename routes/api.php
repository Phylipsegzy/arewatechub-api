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

    Route::get('/feedback/pending', [FeedbackController::class, 'pending']);
    Route::post('/bookings/{booking}/feedback', [FeedbackController::class, 'store']);
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

    Route::get('/push/vapid-public-key', [AdminPushController::class, 'vapidPublicKey']);
    Route::post('/push/subscribe', [AdminPushController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [AdminPushController::class, 'unsubscribe']);
});

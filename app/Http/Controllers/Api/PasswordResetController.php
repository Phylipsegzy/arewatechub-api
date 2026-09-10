<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Step 1: customer requests a reset link by email. Always returns the
     * same generic success message whether or not the email exists, so an
     * attacker can't use this to discover which emails are registered.
     * Rate-limited to 5 requests per email per 24 hours.
     */
    public function forgot(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $generic = ['status' => 'success', 'message' => 'If this email is registered, a password reset link has been sent.'];

        $customer = Customer::where('email', $request->email)->first();
        if (! $customer) {
            return response()->json($generic);
        }

        $existing = DB::table('customer_password_resets')
            ->where('email', $customer->email)
            ->where('last_attempt', '>', now()->subDay())
            ->first();

        if ($existing && $existing->attempts >= 5) {
            return response()->json(['status' => 'error', 'message' => 'You have exceeded the limit of 5 password reset requests today.'], 429);
        }

        $token = Str::random(64);
        $hashedToken = hash('sha256', $token);
        $expiresAt = now()->addHour();

        DB::table('customer_password_resets')->updateOrInsert(
            ['email' => $customer->email],
            [
                'token' => $hashedToken,
                'expires_at' => $expiresAt,
                'attempts' => $existing ? $existing->attempts + 1 : 1,
                'last_attempt' => now(),
                'updated_at' => now(),
                'created_at' => $existing->created_at ?? now(),
            ]
        );

        $resetLink = config('services.frontend_url') . '/reset-password?token=' . $token;

        try {
            Mail::to($customer->email)->send(new PasswordResetMail($customer->firstname, $resetLink));
        } catch (\Throwable $e) {
            // Don't leak mail-server errors to the client — but don't silently
            // pretend it worked either if you're debugging locally without SMTP set up.
            \Log::warning('Password reset email failed to send: ' . $e->getMessage());
        }

        return response()->json($generic);
    }

    /**
     * Step 2: customer submits the token + new password from the link they clicked.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $hashedToken = hash('sha256', $request->token);

        $resetRequest = DB::table('customer_password_resets')
            ->where('token', $hashedToken)
            ->where('expires_at', '>', now())
            ->first();

        if (! $resetRequest) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired reset link.'], 422);
        }

        $customer = Customer::where('email', $resetRequest->email)->firstOrFail();
        $customer->update(['password' => Hash::make($request->password)]);

        DB::table('customer_password_resets')->where('token', $hashedToken)->delete();

        // Revoke all existing login sessions for safety after a password reset.
        $customer->tokens()->delete();

        return response()->json(['status' => 'success', 'message' => 'Password reset successful. Please log in with your new password.']);
    }

    /**
     * Change password while already logged in (requires the current password).
     */
    public function update(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $customer = $request->user();

        if (! Hash::check($request->current_password, $customer->password)) {
            return response()->json(['status' => 'error', 'message' => 'Current password is incorrect.'], 422);
        }

        $customer->update(['password' => Hash::make($request->password)]);

        return response()->json(['status' => 'success', 'message' => 'Password updated successfully.']);
    }
}

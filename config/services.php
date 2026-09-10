<?php

// Drop this in config/services.php (merge with what's already there if that
// file already has other entries — a fresh Laravel install ships with one
// for postmark/resend/slack, don't just overwrite it, add the 'paystack' key
// alongside the others).
return [

    'paystack' => [
        'mode' => env('PAYSTACK_MODE', 'test'),
        'test_secret' => env('PAYSTACK_TEST_SECRET_KEY'),
        'live_secret' => env('PAYSTACK_LIVE_SECRET_KEY'),
        // Public keys are safe to expose client-side (that's what they're
        // for), but the frontend gets them from this API rather than having
        // its own copy in .env, so there's one source of truth for which
        // mode (test/live) the whole app is in.
        'test_public' => env('PAYSTACK_TEST_PUBLIC_KEY'),
        'live_public' => env('PAYSTACK_LIVE_PUBLIC_KEY'),
    ],

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // Read via config(), never env() directly, in every controller/command —
    // env() calls outside config files silently return null once
    // `php artisan config:cache` has been run, which is standard practice
    // for a live deployment. This is the one place these two values live.
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL'),

];

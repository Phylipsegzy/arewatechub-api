<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends real browser push notifications to every admin who has enabled
 * them — works even if the admin panel tab isn't open, as long as the
 * browser is running (this is what makes it a "push" notification rather
 * than something that only works while the page is open).
 *
 * Requires: composer require minishlink/web-push
 * And VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY in .env (see .env.example.additions
 * in this package — real keys are already generated for you there).
 */
class PushNotificationService
{
    public function notifyAllAdmins(string $title, string $body, ?string $url = null): void
    {
        $subscriptions = PushSubscription::all();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('app.url', 'http://localhost'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                ]),
                $payload
            );
        }

        foreach ($webPush->flush() as $report) {
            // An expired/revoked subscription (customer uninstalled the app,
            // cleared browser data, etc.) fails permanently — clean it up so
            // we stop wasting a request on it every time.
            if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getRequest()->getUri())->delete();
            }
        }
    }
}

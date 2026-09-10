<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;

class AdminPushController extends Controller
{
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['admin_id' => $request->user()->id, 'endpoint' => $request->endpoint],
            ['p256dh' => $request->input('keys.p256dh'), 'auth' => $request->input('keys.auth')]
        );

        return response()->json(['message' => 'Push notifications enabled']);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required|string']);

        PushSubscription::where('admin_id', $request->user()->id)
            ->where('endpoint', $request->endpoint)
            ->delete();

        return response()->json(['message' => 'Push notifications disabled']);
    }

    public function vapidPublicKey()
    {
        return response()->json(['public_key' => config('services.vapid.public_key')]);
    }
}

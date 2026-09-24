<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;

/**
 * Stricter than EnsureAdmin — used for routes that Cashiers must NOT reach
 * (managing other staff accounts, wallet adjustments, internet accounts,
 * feedback, teen program, digital academy, push settings, overview stats,
 * modifying/rescheduling bookings). Cashiers are still Admin model
 * instances (same table, same guard), so EnsureAdmin alone isn't enough to
 * tell them apart — this checks role specifically.
 */
class EnsureFullAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user instanceof Admin || ! $user->isFullAdmin()) {
            return response()->json(['message' => 'Forbidden — this action requires a full admin account.'], 403);
        }

        return $next($request);
    }
}

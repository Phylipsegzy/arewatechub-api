<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;

/**
 * Sanctum tokens for Customer and Admin models share the same auth:sanctum
 * guard (Sanctum resolves whichever model the token belongs to). This
 * middleware stops a customer's token from being used against admin routes,
 * and vice versa.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() instanceof Admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}

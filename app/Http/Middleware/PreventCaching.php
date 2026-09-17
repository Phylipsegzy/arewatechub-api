<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Every response from this API is live, user-specific data (wallet
 * balances, booking availability, enrollment status) — it must never be
 * cached by anything sitting between PHP and the browser: LiteSpeed Cache
 * (LSCache, common on Hostinger and separate from any account-level CDN
 * toggle), a reverse proxy, or the browser itself. This is what actually
 * caused the cohort enrollment bug: PHP correctly computed `null`, but a
 * stale response from early testing had been cached at the server level
 * and kept getting served regardless of code changes or Laravel-level
 * cache clears.
 */
class PreventCaching
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}

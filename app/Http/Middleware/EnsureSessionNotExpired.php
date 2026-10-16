<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a dashboard session a fixed number of hours after sign-in (config frt.session_max_hours),
 * and refuses "remember me" cookies, so the owner always signs in again.
 */
class EnsureSessionNotExpired
{
    public const SESSION_KEY = 'signed_in_at';

    public function handle(Request $request, Closure $next): Response
    {
        $signedInAt = $request->session()->get(self::SESSION_KEY);
        $limit = now()->subHours(config('frt.session_max_hours'))->getTimestamp();

        if (Auth::guard('web')->viaRemember() || ! is_int($signedInAt) || $signedInAt < $limit) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['username' => 'Your session has ended. Please log in again.']);
        }

        return $next($request);
    }
}

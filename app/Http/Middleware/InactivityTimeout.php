<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InactivityTimeout
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('web')->check()) {
            return $next($request);
        }

        $timeout = (int) config('session.inactivity_timeout', config('session.lifetime', 120));

        if ($timeout <= 0) {
            return $next($request);
        }

        $lastActivity = (int) $request->session()->get('last_activity_at', time());
        $expired = (time() - $lastActivity) > ($timeout * 60);

        if ($expired) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired due to inactivity.',
                ], 419);
            }

            return redirect()
                ->route('login')
                ->with('session_expired', 'Your session has expired due to inactivity. Please log in again.');
        }

        $response = $next($request);

        if (Auth::guard('web')->check() && $request->hasSession()) {
            $request->session()->put('last_activity_at', time());
        }

        return $response;
    }
}

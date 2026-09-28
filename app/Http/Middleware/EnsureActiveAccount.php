<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->fresh();
        if (! $user || $user->status !== 'active' || $user->hospital?->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                abort(401, 'Your account is unavailable. Contact your hospital administrator.');
            }

            return redirect()->route('login')->withErrors(['email' => 'Your account is unavailable. Contact your hospital administrator.']);
        }
        Auth::setUser($user);

        return $next($request);
    }
}

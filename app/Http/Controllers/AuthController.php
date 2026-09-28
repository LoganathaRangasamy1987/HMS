<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function store(Request $request, TenantContext $tenant, AuditService $audit): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150'], 'password' => ['required', 'string', 'max:128'], 'remember' => ['sometimes', 'boolean']]);
        $data['email'] = Str::lower($data['email']);
        $key = 'login:'.hash('sha256', $data['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'status' => 'active'], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Unable to sign in with these credentials.']);
        }
        $membership = $request->user()->activeMemberships()->with(['hospital', 'branch', 'role'])->orderBy('id')->first();
        if (! $membership) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Unable to sign in with these credentials.']);
        }
        $request->session()->regenerate();
        $request->session()->put('membership_id', $membership->id);
        $tenant->set($membership);
        DB::transaction(function () use ($request, $audit) {
            $request->user()->update(['last_login_at' => now()]);
            $audit->record('authentication', 'signed_in', $request->user());
        });
        RateLimiter::clear($key);

        return $request->expectsJson() ? response()->json(['data' => $request->user(), 'membership_id' => $membership->id]) : redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, TenantContext $tenant, AuditService $audit): JsonResponse|RedirectResponse
    {
        $membership = $request->user()?->activeMemberships()->find($request->session()->get('membership_id'));
        if ($membership) {
            $tenant->set($membership);
            $audit->record('authentication', 'signed_out', $request->user());
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson() ? response()->json(['message' => 'Signed out.']) : redirect()->route('login');
    }
}

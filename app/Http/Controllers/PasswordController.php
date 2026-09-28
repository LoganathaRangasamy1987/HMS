<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $email = Str::lower($data['email']);
        if (User::where('email', $email)->where('status', 'active')->whereHas('hospital', fn ($q) => $q->where('status', 'active'))->exists()) {
            Password::sendResetLink(['email' => $email, 'status' => 'active']);
        }

        return back()->with('status', 'If an active account matches that email, a password reset link has been sent.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email', 'max:150'], 'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed']]);
        $data['email'] = Str::lower($data['email']);
        $status = Password::reset([...$data, 'status' => 'active'], function (User $user, string $password) use ($request): void {
            DB::transaction(function () use ($user, $password, $request): void {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                if (config('session.driver') === 'database') {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
                AuditLog::create(['hospital_id' => $user->hospital_id, 'branch_id' => null, 'user_id' => $user->id, 'module' => 'authentication', 'action' => 'password_reset', 'record_type' => User::class, 'record_id' => $user->id, 'ip_address' => $request->ip(), 'created_at' => now()]);
            });
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset ? redirect()->route('login')->with('status', 'Password reset. Sign in with your new password.') : back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or expired. Request a new link.']);
    }
}

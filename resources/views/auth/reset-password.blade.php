@extends('layouts.guest')
@section('title', 'Choose a new password')
@section('content')
    <div class="auth-form-icon"><x-icon name="lock" size="27"/></div><span class="eyebrow">ACCOUNT ACCESS</span><h2>Choose a new password</h2><p class="auth-intro">Use a strong password that you don’t use for another account.</p>
    @include('partials.messages')
    <form method="POST" action="{{ route('password.update') }}" class="auth-form">@csrf<input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}"><x-form.input name="email" label="Email address" type="email" :value="$email ?? request('email')" required autocomplete="username"/><x-form.input name="password" label="New password" type="password" required autocomplete="new-password" minlength="12" hint="Use at least 12 characters."/><x-form.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password"/><button class="btn btn-primary w-100 auth-submit" type="submit">Reset password <x-icon name="arrow" size="18"/></button></form>
    <p class="text-center mt-4"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection

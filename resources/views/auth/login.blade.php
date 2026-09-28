@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
    <div class="auth-form-icon"><x-icon name="hospital" size="27"/></div>
    <span class="eyebrow">WELCOME BACK</span><h2>Sign in to your workspace</h2><p class="auth-intro">Enter your account details to continue.</p>
    @include('partials.messages')
    <form method="POST" action="{{ route('login') }}" class="auth-form">@csrf
        <x-form.input name="email" label="Email address" type="email" required autocomplete="username" autofocus placeholder="you@hospital.com"/>
        <div class="password-field"><x-form.input name="password" label="Password" type="password" required autocomplete="current-password"/><button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><x-icon name="eye" size="19"/></button></div>
        <div class="d-flex justify-content-between gap-3 align-items-center auth-options"><div class="form-check"><input type="checkbox" class="form-check-input" id="remember" name="remember" value="1" @checked(old('remember'))><label for="remember" class="form-check-label">Remember me</label></div><a href="{{ route('password.request') }}">Forgot password?</a></div>
        <button class="btn btn-primary w-100 auth-submit" type="submit">Sign in <x-icon name="arrow" size="18"/></button>
    </form>
    <div class="auth-help"><x-icon name="info" size="17"/><span>Need an account? Contact your hospital administrator.</span></div>
@endsection

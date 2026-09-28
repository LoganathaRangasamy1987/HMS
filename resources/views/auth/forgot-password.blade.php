@extends('layouts.guest')
@section('title', 'Reset your password')
@section('content')
    <div class="auth-form-icon"><x-icon name="lock" size="27"/></div><span class="eyebrow">ACCOUNT ACCESS</span><h2>Forgot your password?</h2><p class="auth-intro">Enter the email address associated with your account. We’ll send you a link to reset your password.</p>
    @include('partials.messages')
    <form method="POST" action="{{ route('password.email') }}" class="auth-form">@csrf<x-form.input name="email" label="Email address" type="email" required autocomplete="email" autofocus placeholder="you@hospital.com"/><button class="btn btn-primary w-100 auth-submit" type="submit">Send reset link <x-icon name="arrow" size="18"/></button></form>
    <p class="text-center mt-4"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection

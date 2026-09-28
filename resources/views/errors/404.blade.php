@extends('layouts.guest')
@section('title', 'Page not found')
@section('content')
<div class="auth-form-icon"><x-icon name="search" size="27"/></div><span class="eyebrow">404 · PAGE NOT FOUND</span><h2>We couldn’t find that page.</h2><p class="auth-intro">The address may have changed, or the page may not be available in your workspace.</p><a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary">{{ auth()->check() ? 'Back to workspace' : 'Back to sign in' }} <x-icon name="arrow" size="18"/></a>
@endsection

@extends('layouts.guest')
@section('title', 'Access unavailable')
@section('content')
<div class="auth-form-icon"><x-icon name="shield" size="27"/></div><span class="eyebrow">403 · ACCESS UNAVAILABLE</span><h2>This page needs different access.</h2><p class="auth-intro">Your current account or branch role cannot open this page. Contact your hospital administrator if you need access.</p><a href="{{ route('dashboard') }}" class="btn btn-primary">Back to workspace <x-icon name="arrow" size="18"/></a><form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn btn-link p-0" type="submit">Sign out</button></form>
@endsection

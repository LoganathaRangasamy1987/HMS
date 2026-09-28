<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Welcome') · CareDesk</title><link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('css/hms.css') }}"><script src="{{ asset('js/hms.js') }}" defer></script></head>
<body class="guest-body">
<a href="#main-content" class="skip-link">Skip to content</a>
<div class="auth-layout">
    <aside class="auth-story"><a class="brand" href="{{ url('/') }}"><span class="brand-symbol"><span></span></span><span>CareDesk<small>HOSPITAL ERP</small></span></a><div class="auth-story-content"><span class="auth-kicker">A CONNECTED HOSPITAL STARTS HERE</span><h1>More connected.<br>Better organized.<br><span>Ready for care.</span></h1><p>One workspace for your people, branches, and the everyday work of running a hospital.</p><div class="auth-visual" aria-hidden="true"><div class="visual-orbit"></div><div class="visual-hospital"><x-icon name="hospital" size="68"/></div><div class="visual-pill visual-pill-one"><span class="mini-icon"><x-icon name="users" size="18"/></span>Your people, together</div><div class="visual-pill visual-pill-two"><span class="mini-icon"><x-icon name="branch" size="18"/></span>Every branch, connected</div><span class="visual-dot one"></span><span class="visual-dot two"></span></div></div><div class="auth-story-footer"><x-icon name="shield" size="17"/><span>A dedicated workspace for your hospital team</span></div></aside>
    <main id="main-content" class="auth-main" tabindex="-1"><div class="auth-mobile-brand"><span class="brand-symbol"><span></span></span><strong>CareDesk</strong></div><div class="auth-form-wrapper">@yield('content')</div><footer class="auth-footer">CareDesk Hospital ERP · {{ now()->format('Y') }}</footer></main>
</div>
</body>
</html>

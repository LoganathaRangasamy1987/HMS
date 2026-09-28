<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Overview') · CareDesk</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/hms.css') }}">
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}" defer></script>
    <script src="{{ asset('js/hms.js') }}" defer></script>
</head>
<body class="app-body">
<a href="#main-content" class="skip-link">Skip to content</a>
<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="app-sidebar" aria-labelledby="sidebar-label">
    <div class="sidebar-top"><a href="{{ route('dashboard') }}" class="brand"><span class="brand-symbol"><span></span></span><span id="sidebar-label">CareDesk<small>HOSPITAL ERP</small></span></a><button class="btn-close btn-close-white d-lg-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#app-sidebar" aria-label="Close navigation"></button></div>
    <div class="sidebar-hospital"><span class="hospital-mini"><x-icon name="hospital"/></span><div><strong>{{ $tenant->hospital()->name }}</strong><span>{{ $tenant->branch()->name }}</span></div></div>
    <nav class="sidebar-nav" aria-label="Main navigation">
        <div class="nav-caption">WORKSPACE</div>
        <a href="{{ route('dashboard') }}" @class(['nav-item', 'active' => request()->routeIs('dashboard')]) @if(request()->routeIs('dashboard'))aria-current="page"@endif><x-icon name="grid"/><span>Overview</span></a>
        <a href="{{ route('notifications.index') }}" @class(['nav-item', 'active' => request()->routeIs('notifications.*')])><x-icon name="activity"/><span>Notifications</span></a>
        @if($tenant->can('PATIENT.VIEW') || $tenant->can('INVOICE.MANAGE'))<a href="{{ route('search.index') }}" @class(['nav-item', 'active' => request()->routeIs('search.*')])><x-icon name="search"/><span>Global search</span></a>@endif
        @if($tenant->can('APPOINTMENT.VIEW'))<a href="{{ route('appointments.index') }}" @class(['nav-item', 'active' => request()->routeIs('appointments.*')]) @if(request()->routeIs('appointments.*'))aria-current="page"@endif><x-icon name="activity"/><span>Appointments</span></a>@endif
        @if($tenant->can('SERVICE.VIEW'))<a href="{{ route('services.index') }}" @class(['nav-item', 'active' => request()->routeIs('services.*')]) @if(request()->routeIs('services.*'))aria-current="page"@endif><x-icon name="layers"/><span>Services</span></a>@endif
        @if($tenant->can('MEDICINE.VIEW'))<a href="{{ route('medicines.index') }}" @class(['nav-item', 'active' => request()->routeIs('medicines.*')]) @if(request()->routeIs('medicines.*'))aria-current="page"@endif><x-icon name="layers"/><span>Medicines</span></a>@endif
        @if($tenant->can('PHARMACY_WORKSPACE.VIEW'))<a href="{{ route('pharmacy.workspace') }}" @class(['nav-item', 'active' => request()->routeIs('pharmacy.workspace')])><x-icon name="grid"/><span>Pharmacy workspace</span></a>@endif
        @if($tenant->can('PHARMACY_PURCHASE.VIEW'))<a href="{{ route('pharmacy.purchases.index') }}" @class(['nav-item', 'active' => request()->routeIs('pharmacy.purchases.*')])><x-icon name="activity"/><span>Pharmacy stock</span></a>@endif
        @if($tenant->can('PHARMACY_SALE.VIEW'))<a href="{{ route('pharmacy.sales.index') }}" @class(['nav-item', 'active' => request()->routeIs('pharmacy.sales.*')])><x-icon name="activity"/><span>Pharmacy sales</span></a>@endif
        @if($tenant->can('PHARMACY_ADJUSTMENT.VIEW'))<a href="{{ route('pharmacy.adjustments.index') }}" @class(['nav-item', 'active' => request()->routeIs('pharmacy.adjustments.*')])><x-icon name="activity"/><span>Stock adjustments</span></a>@endif
        @if($tenant->can('PHARMACY_RECONCILIATION.VIEW'))<a href="{{ route('pharmacy.reconciliation') }}" @class(['nav-item', 'active' => request()->routeIs('pharmacy.reconciliation')])><x-icon name="activity"/><span>Stock reconciliation</span></a>@endif
        @if($tenant->can('PHARMACY_CATALOG.VIEW'))<a href="{{ route('pharmacy.catalog') }}" @class(['nav-item', 'active' => request()->routeIs('pharmacy.catalog', 'pharmacy.manufacturers.*', 'pharmacy.types.*', 'pharmacy.units.*', 'pharmacy.suppliers.*')])><x-icon name="layers"/><span>Pharmacy catalog</span></a>@endif
        @if($tenant->can('LAB_WORKLIST.VIEW'))<a href="{{ route('laboratory.worklist') }}" @class(['nav-item', 'active' => request()->routeIs('laboratory.worklist')]) @if(request()->routeIs('laboratory.worklist'))aria-current="page"@endif><x-icon name="activity"/><span>Lab workspace</span></a>@endif
        @if($tenant->can('LAB_ORDER.VIEW'))<a href="{{ route('laboratory.orders.index') }}" @class(['nav-item', 'active' => request()->routeIs('laboratory.orders.*')]) @if(request()->routeIs('laboratory.orders.*'))aria-current="page"@endif><x-icon name="activity"/><span>Lab orders</span></a>@endif
        @if($tenant->can('LAB_CATALOG.VIEW'))<a href="{{ route('laboratory.catalog') }}" @class(['nav-item', 'active' => request()->routeIs('laboratory.catalog', 'laboratory.tests.*', 'laboratory.versions.*')])><x-icon name="activity"/><span>Lab catalog</span></a>@endif
        @if($tenant->can('INVOICE.MANAGE'))<a href="{{ route('invoices.index') }}" @class(['nav-item', 'active' => request()->routeIs('invoices.*')]) @if(request()->routeIs('invoices.*'))aria-current="page"@endif><x-icon name="layers"/><span>Invoices</span></a>@endif
        @if($tenant->isAdmin())<a href="{{ route('billing.reconciliation') }}" @class(['nav-item', 'active' => request()->routeIs('billing.reconciliation')]) @if(request()->routeIs('billing.reconciliation'))aria-current="page"@endif><x-icon name="activity"/><span>Reconciliation</span></a>@endif
        @if($tenant->isAdmin())<a href="{{ route('reports.operational') }}" @class(['nav-item', 'active' => request()->routeIs('reports.operational')]) @if(request()->routeIs('reports.operational'))aria-current="page"@endif><x-icon name="activity"/><span>Operational reports</span></a>@endif
        @if($tenant->can('DOCTOR.VIEW'))<a href="{{ route('doctors.index') }}" @class(['nav-item', 'active' => request()->routeIs('doctors.*')]) @if(request()->routeIs('doctors.*'))aria-current="page"@endif><x-icon name="users"/><span>Doctors</span></a>@endif
        @if($tenant->can('PATIENT.VIEW'))
            <a href="{{ route('patients.index') }}" @class(['nav-item', 'active' => request()->routeIs('patients.*')]) @if(request()->routeIs('patients.*'))aria-current="page"@endif><x-icon name="users"/><span>Patients</span></a>
        @endif
        @if($tenant->isAdmin())
            <div class="nav-caption mt-4">ADMINISTRATION</div>
            <a href="{{ route('organization.edit') }}" @class(['nav-item', 'active' => request()->routeIs('organization.*')]) @if(request()->routeIs('organization.*'))aria-current="page"@endif><x-icon name="hospital"/><span>Organization</span></a>
            <a href="{{ route('branches.index') }}" @class(['nav-item', 'active' => request()->routeIs('branches.*')]) @if(request()->routeIs('branches.*'))aria-current="page"@endif><x-icon name="branch"/><span>Branches</span></a>
            <a href="{{ route('departments.index') }}" @class(['nav-item', 'active' => request()->routeIs('departments.*')]) @if(request()->routeIs('departments.*'))aria-current="page"@endif><x-icon name="layers"/><span>Departments</span></a>
            <a href="{{ route('staff.index') }}" @class(['nav-item', 'active' => request()->routeIs('staff.*')]) @if(request()->routeIs('staff.*'))aria-current="page"@endif><x-icon name="users"/><span>Staff & access</span></a>
            <a href="{{ route('audit.index') }}" @class(['nav-item', 'active' => request()->routeIs('audit.*')]) @if(request()->routeIs('audit.*'))aria-current="page"@endif><x-icon name="activity"/><span>Activity log</span></a>
        @endif
    </nav>
    <div class="sidebar-bottom"><span class="security-icon"><x-icon name="shield" size="18"/></span><div><strong>Your hospital workspace</strong><small>Access follows your assigned role</small></div></div>
</aside>
<div class="app-shell">
    <header class="app-header">
        <div class="d-flex align-items-center gap-3"><button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#app-sidebar" aria-controls="app-sidebar" aria-label="Open navigation"><x-icon name="menu"/></button><span class="header-label">Hospital workspace</span></div>
        <div class="header-tools">
            <form method="POST" action="{{ route('context.switch') }}" class="branch-switcher">@csrf
                <x-icon name="pin" size="17"/>
                <label class="visually-hidden" for="active-membership">Active hospital and branch</label>
                <select name="membership_id" id="active-membership" class="form-select form-select-sm" data-autosubmit>
                    @foreach($availableMemberships as $membership)<option value="{{ $membership->id }}" @selected($membership->id === $tenant->membership()->id)>{{ $membership->hospital->name }} · {{ $membership->branch->name }}</option>@endforeach
                </select><noscript><button class="btn btn-sm btn-primary">Switch</button></noscript>
            </form>
            <div class="header-divider"></div>
            <div class="dropdown"><button class="profile-trigger" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="profile-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ str_replace('_', ' ', ucwords(strtolower($tenant->membership()->role->name), '_')) }}</small></span><span class="profile-chevron">⌄</span></button><div class="dropdown-menu dropdown-menu-end"><div class="dropdown-item-text small text-secondary">{{ auth()->user()->email }}</div><hr class="dropdown-divider"><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item d-flex align-items-center gap-2" type="submit"><x-icon name="logout" size="17"/> Sign out</button></form></div></div>
        </div>
    </header>
    <main id="main-content" class="main-content" tabindex="-1">
        <div class="page-heading"><div><div class="eyebrow">{{ $tenant->hospital()->name }}</div><h1>@yield('title', 'Overview')</h1><p>@yield('subtitle', 'Manage your hospital workspace.')</p></div><div class="page-actions">@yield('actions')</div></div>
        @include('partials.messages')
        @yield('content')
    </main>
    <footer class="app-footer"><span>CareDesk <span class="text-secondary">/ Hospital ERP</span></span><span>{{ $tenant->branch()->name }} · {{ now()->format('Y') }}</span></footer>
</div>
</body>
</html>

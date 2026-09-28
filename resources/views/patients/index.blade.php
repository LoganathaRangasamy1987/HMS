@extends('layouts.app')
@section('title', 'Patients')
@section('subtitle', 'Find patient records shared across your hospital branches.')
@section('actions')
    @if($tenant->can('PATIENT.MANAGE'))
        <a href="{{ route('patients.create') }}" class="btn btn-primary"><x-icon name="plus" size="18"/> Register patient</a>
    @endif
@endsection
@section('content')
<section class="panel">
    <div class="panel-heading"><div><h2>Patient directory <span class="count-badge">{{ $patients->total() }}</span></h2><p>Search before registering a patient to find an existing UHID</p></div></div>
    <form method="GET" action="{{ route('patients.index') }}" class="filter-bar">
        <div class="search-field"><x-icon name="search" size="18"/><label class="visually-hidden" for="patient-search">Search patients</label><input type="search" id="patient-search" name="q" value="{{ request('q') }}" placeholder="Search by UHID, name or mobile" maxlength="200" class="form-control"></div>
        <label class="visually-hidden" for="patient-status">Patient status</label>
        <select id="patient-status" name="status" class="form-select filter-select"><option value="">All statuses</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="inactive" @selected(request('status') === 'inactive')>Inactive</option></select>
        <button class="btn btn-outline-secondary" type="submit">Filter</button>
        @if(request()->filled('q') || request()->filled('status'))<a href="{{ route('patients.index') }}" class="filter-reset">Clear</a>@endif
    </form>
    <div class="table-responsive">
        <table class="table app-table mb-0">
            <thead><tr><th>Patient</th><th>Date of birth</th><th>Contact</th><th>Registration branch</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse($patients as $patient)
                    <tr>
                        <td><div class="person-cell"><span class="avatar">{{ mb_strtoupper(mb_substr($patient->first_name, 0, 1)) }}</span><div><strong>{{ trim($patient->first_name.' '.$patient->last_name) }}</strong><small>{{ $patient->uhid }}</small></div></div></td>
                        <td>{{ $patient->date_of_birth_unknown ? 'Unknown' : $patient->date_of_birth?->format('d M Y') }}</td>
                        <td><div class="membership-list">@if($patient->mobile)<div><strong>{{ $patient->mobile }}</strong></div>@endif @if($patient->email)<div><span class="text-break">{{ $patient->email }}</span></div>@endif @if(!$patient->mobile && !$patient->email)<div><span>Emergency: {{ $patient->emergency_contact_mobile }}</span></div>@endif</div></td>
                        <td>{{ $patient->branch->name }}</td>
                        <td><x-status :value="$patient->status"/></td>
                        <td class="text-end"><a href="{{ route('patients.show', $patient) }}" class="btn btn-sm btn-table" aria-label="View {{ trim($patient->first_name.' '.$patient->last_name) }}"><x-icon name="eye" size="16"/> View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><span class="empty-icon"><x-icon name="users" size="30"/></span><h3>{{ request()->filled('q') || request()->filled('status') ? 'No patients match your filters' : 'Your patient directory starts here' }}</h3><p>{{ request()->filled('q') || request()->filled('status') ? 'Try another UHID, name or mobile number, or clear the filters.' : 'Register a patient to create their hospital UHID.' }}</p>@if($tenant->can('PATIENT.MANAGE') && !request()->filled('q') && !request()->filled('status'))<a href="{{ route('patients.create') }}" class="btn btn-primary btn-sm">Register patient</a>@endif</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($patients->hasPages())<div class="panel-pagination">{{ $patients->withQueryString()->links() }}</div>@endif
</section>
@endsection

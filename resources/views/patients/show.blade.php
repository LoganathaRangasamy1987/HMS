@extends('layouts.app')
@section('title', 'Patient details')
@section('subtitle', 'Hospital patient identity, demographics and contact details.')
@section('actions')
    <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary">All patients</a>
    <a href="{{ route('patients.timeline', $patient) }}" class="btn btn-outline-primary">Patient 360</a>
    @if($tenant->can('PATIENT_HISTORY.VIEW'))<a href="{{ route('patients.clinical-history', $patient) }}" class="btn btn-outline-primary">Clinical history</a>@endif
    @if($tenant->can('PATIENT.MANAGE'))<a href="{{ route('patients.edit', $patient) }}" class="btn btn-primary"><x-icon name="edit" size="18"/> Edit patient</a>@endif
@endsection
@section('content')
<div class="row g-4">
    <div class="col-xl-8">
        <section class="panel">
            <div class="panel-heading"><div><h2 class="text-break">{{ trim($patient->first_name.' '.$patient->last_name) }}</h2><p class="text-break">{{ $patient->uhid }}</p></div><x-status :value="$patient->status"/></div>
            <div class="panel-body"><dl class="row g-3 mb-0">
                <div class="col-sm-6"><dt class="small text-secondary">First name</dt><dd class="mb-0 text-break">{{ $patient->first_name }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">Last name</dt><dd class="mb-0 text-break">{{ $patient->last_name ?: 'Not recorded' }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">Date of birth</dt><dd class="mb-0">{{ $patient->date_of_birth_unknown ? 'Unknown' : $patient->date_of_birth?->format('d M Y') }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">Gender</dt><dd class="mb-0">{{ ucfirst($patient->gender) }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">Blood group</dt><dd class="mb-0">{{ $patient->blood_group ?: 'Not recorded' }}</dd></div>
            </dl></div>
        </section>
        <section class="panel mt-4">
            <div class="panel-heading"><div><h2>Contact &amp; address</h2></div></div>
            <div class="panel-body"><dl class="row g-3 mb-0">
                <div class="col-sm-6"><dt class="small text-secondary">Mobile number</dt><dd class="mb-0 text-break">{{ $patient->mobile ?: 'Not recorded' }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">Email address</dt><dd class="mb-0 text-break">{{ $patient->email ?: 'Not recorded' }}</dd></div>
                <div class="col-12"><dt class="small text-secondary">Street address</dt><dd class="mb-0 text-break" style="white-space: pre-line">{{ $patient->address ?: 'Not recorded' }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">City</dt><dd class="mb-0 text-break">{{ $patient->city ?: 'Not recorded' }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">State</dt><dd class="mb-0 text-break">{{ $patient->state ?: 'Not recorded' }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">Postal code</dt><dd class="mb-0 text-break">{{ $patient->pincode ?: 'Not recorded' }}</dd></div>
            </dl></div>
        </section>
        <section class="panel mt-4">
            <div class="panel-heading"><div><h2>Emergency contact</h2></div></div>
            <div class="panel-body"><dl class="row g-3 mb-0">
                <div class="col-sm-6"><dt class="small text-secondary">Contact name</dt><dd class="mb-0 text-break">{{ $patient->emergency_contact_name ?: 'Not recorded' }}</dd></div>
                <div class="col-sm-6"><dt class="small text-secondary">Contact mobile</dt><dd class="mb-0 text-break">{{ $patient->emergency_contact_mobile ?: 'Not recorded' }}</dd></div>
            </dl></div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel">
            <div class="panel-heading"><div><h2>Registration record</h2><p>Original patient identity and registration details</p></div></div>
            <dl class="workspace-details">
                <div><dt>UHID</dt><dd class="text-break">{{ $patient->uhid }}</dd></div>
                <div><dt>Branch</dt><dd class="text-break">{{ $patient->branch->name }}</dd></div>
                <div><dt>Registered by</dt><dd class="text-break">{{ $patient->registeredBy?->name ?? 'Not recorded' }}</dd></div>
                <div><dt>Registered</dt><dd>{{ $patient->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} IST</dd></div>
                <div><dt>Last updated</dt><dd>{{ $patient->updated_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} IST</dd></div>
            </dl>
        </section>
    </div>
</div>
@endsection

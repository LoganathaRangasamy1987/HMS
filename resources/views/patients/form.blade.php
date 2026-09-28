@extends('layouts.app')
@section('title', $patient->exists ? 'Edit patient' : 'Register patient')
@section('subtitle', $patient->exists ? 'Update demographics and contact details while retaining the same UHID.' : 'Create a patient record for your hospital. Fields marked * are required.')
@section('actions')<a href="{{ route('patients.index') }}" class="text-link">All patients <x-icon name="arrow" size="16"/></a>@endsection
@section('content')
@php($unknownDateOfBirth = (bool) old('date_of_birth_unknown', $patient->date_of_birth_unknown ?? false))
<form method="POST" action="{{ $patient->exists ? route('patients.update', $patient) : route('patients.store') }}">
    @csrf
    @if($patient->exists)@method('PUT')@endif
    <div class="row g-4">
        <div class="col-xl-8">
            @if(!$patient->exists && session('duplicates'))
                <section class="panel mb-4" aria-labelledby="duplicates-heading">
                    <div class="panel-heading"><div><h2 id="duplicates-heading">Review possible duplicates</h2><p>Up to 10 possible matches are shown. Compare these records before registering a new patient. Family members may share contact details.</p></div></div>
                    <div class="panel-body">
                        <ul class="list-group mb-3">
                            @foreach(session('duplicates') as $candidate)
                                <li class="list-group-item">
                                    <a href="{{ route('patients.show', $candidate['id']) }}" target="_blank" rel="noopener" class="fw-semibold">{{ trim($candidate['first_name'].' '.($candidate['last_name'] ?? '')) }} <span class="small">(opens in a new tab)</span></a>
                                    <div class="small text-secondary text-break">{{ $candidate['uhid'] }} &middot; DOB: {{ $candidate['date_of_birth'] ? substr($candidate['date_of_birth'], 0, 10) : 'Unknown' }} &middot; {{ ucfirst($candidate['status']) }}</div>
                                    @if($candidate['mobile'])<div class="small text-break">Mobile: {{ $candidate['mobile'] }}</div>@endif
                                    @if($candidate['email'])<div class="small text-break">Email: {{ $candidate['email'] }}</div>@endif
                                    @if($candidate['emergency_contact_mobile'])<div class="small text-break">Emergency mobile: {{ $candidate['emergency_contact_mobile'] }}</div>@endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="form-check">
                            <input type="checkbox" name="duplicates_confirmed" id="duplicates_confirmed" value="1" class="form-check-input @error('duplicates_confirmed') is-invalid @enderror" aria-describedby="duplicates-help">
                            <label class="form-check-label" for="duplicates_confirmed">I reviewed these records and want to register a separate patient.</label>
                            <div id="duplicates-help" class="form-text">If a record belongs to this patient, use that record. Confirming creates a new UHID and keeps the existing records.</div>
                        </div>
                    </div>
                </section>
            @endif
            <section class="panel">
                <div class="panel-heading"><div><h2>Patient demographics</h2><p>Enter the patient's name and known demographic details</p></div><span class="section-icon"><x-icon name="users"/></span></div>
                <div class="panel-body"><div class="row g-4">
                    <div class="col-md-6"><x-form.input name="first_name" label="First name" :value="$patient->first_name" required maxlength="100" autocomplete="given-name"/></div>
                    <div class="col-md-6"><x-form.input name="last_name" label="Last name" :value="$patient->last_name" maxlength="100" autocomplete="family-name"/></div>
                    <div class="col-md-6">
                        <x-form.input name="date_of_birth" label="Date of birth" type="date" :value="$patient->date_of_birth?->format('Y-m-d')" :max="now('Asia/Kolkata')->toDateString()" hint="Enter the known date, or select Date of birth unknown. Do not estimate a date from age."/>
                        <input type="hidden" name="date_of_birth_unknown" value="0">
                        <div class="form-check mt-3">
                            <input type="checkbox" id="date_of_birth_unknown" name="date_of_birth_unknown" value="1" class="form-check-input @error('date_of_birth_unknown') is-invalid @enderror" @checked($unknownDateOfBirth) data-unknown-date="date_of_birth">
                            <label class="form-check-label" for="date_of_birth_unknown">Date of birth unknown</label>
                            @error('date_of_birth_unknown')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <noscript><div class="form-text">Leave the date empty when selecting unknown. To record a known date, clear the checkbox and enter the date.</div></noscript>
                    </div>
                    <div class="col-md-6"><x-form.select name="gender" label="Gender" :value="$patient->gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'unknown' => 'Unknown']" placeholder="Select gender" required/></div>
                    <div class="col-md-6"><x-form.select name="blood_group" label="Blood group" :value="$patient->blood_group" :options="['A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-']" placeholder="Not recorded"/></div>
                    <div class="col-md-6"><x-form.select name="status" label="Patient status" :value="$patient->status ?? 'active'" :options="['active' => 'Active', 'inactive' => 'Inactive']" required hint="Inactive records remain searchable and retain their UHID."/></div>
                </div></div>
            </section>
            <section class="panel mt-4">
                <div class="panel-heading"><div><h2>Contact &amp; address</h2><p>Provide at least one: mobile, email, or emergency contact mobile</p></div><span class="section-icon"><x-icon name="pin"/></span></div>
                <div class="panel-body"><div class="row g-4">
                    <div class="col-md-6"><x-form.input name="mobile" label="Mobile number" type="tel" :value="$patient->mobile" maxlength="30" autocomplete="tel" hint="Use 7–15 digits; a leading +, spaces, brackets, dots and hyphens are allowed. Shared family numbers are accepted."/></div>
                    <div class="col-md-6"><x-form.input name="email" label="Email address" type="email" :value="$patient->email" maxlength="255" autocomplete="email"/></div>
                    <div class="col-12"><x-form.textarea name="address" label="Street address" :value="$patient->address" maxlength="2000" autocomplete="street-address"/></div>
                    <div class="col-md-6"><x-form.input name="city" label="City" :value="$patient->city" maxlength="100" autocomplete="address-level2"/></div>
                    <div class="col-md-6"><x-form.input name="state" label="State" :value="$patient->state" maxlength="100" autocomplete="address-level1"/></div>
                    <div class="col-md-6"><x-form.input name="pincode" label="Postal code" :value="$patient->pincode" maxlength="20" autocomplete="postal-code"/></div>
                </div></div>
            </section>
            <section class="panel mt-4">
                <div class="panel-heading"><div><h2>Emergency contact</h2><p>Record a person who can be contacted when needed</p></div></div>
                <div class="panel-body"><div class="row g-4">
                    <div class="col-md-6"><x-form.input name="emergency_contact_name" label="Emergency contact name" :value="$patient->emergency_contact_name" maxlength="150"/></div>
                    <div class="col-md-6"><x-form.input name="emergency_contact_mobile" label="Emergency contact mobile" type="tel" :value="$patient->emergency_contact_mobile" maxlength="30" hint="Use the same phone format as the mobile number above."/></div>
                </div></div>
            </section>
            <div class="form-actions"><a href="{{ $patient->exists ? route('patients.show', $patient) : route('patients.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary" type="submit"><x-icon name="check" size="18"/>{{ $patient->exists ? 'Save changes' : 'Register patient' }}</button></div>
        </div>
        <div class="col-xl-4"><aside class="help-card"><span class="help-card-icon"><x-icon name="shield" size="24"/></span><h3>One hospital patient identity</h3>
            @if($patient->exists)
                <p class="text-break"><strong>UHID</strong><br>{{ $patient->uhid }}</p>
                <p><strong>Registration branch</strong><br>{{ $patient->branch->name }}</p>
                <p class="mb-0">Demographic changes keep this UHID and the original registration branch.</p>
            @else
                <p>Registration assigns a new UHID that can be found at every branch of this hospital.</p>
                <p><strong>Registration branch</strong><br>{{ $tenant->branch()->name }}</p>
                <hr><p class="mb-0">Possible matching records appear after submitting. Review them before confirming a separate registration.</p>
            @endif
        </aside></div>
    </div>
</form>
@endsection

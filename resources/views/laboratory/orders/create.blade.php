@extends('layouts.app')
@section('title', 'New laboratory order')
@section('subtitle', 'Select a patient, ordering doctor, and current active test definitions.')
@section('actions')<a class="btn btn-outline-secondary" href="{{ route('laboratory.orders.index') }}">Back to orders</a>@endsection
@section('content')
<form method="POST" action="{{ route('laboratory.orders.store') }}">@csrf
<div class="card border-0 shadow-sm"><div class="card-body row g-4">
    <div class="col-md-6"><label class="form-label" for="patient_id">Patient</label><select class="form-select" id="patient_id" name="patient_id" required><option value="">Choose patient</option>@foreach($patients as $patient)<option value="{{ $patient->id }}" @selected(old('patient_id') == $patient->id)>{{ $patient->uhid }} — {{ $patient->first_name }} {{ $patient->last_name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label" for="doctor_profile_id">Ordering doctor</label><select class="form-select" id="doctor_profile_id" name="doctor_profile_id" required><option value="">Choose doctor</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" @selected(old('doctor_profile_id') == $doctor->id)>{{ $doctor->user->name }} — {{ $doctor->specialization }}</option>@endforeach</select></div>
    <div class="col-12"><label class="form-label" for="encounter_id">Encounter (optional)</label><select class="form-select" id="encounter_id" name="encounter_id"><option value="">No linked encounter</option>@foreach($encounters as $encounter)<option value="{{ $encounter->id }}" @selected(old('encounter_id') == $encounter->id)>Encounter #{{ $encounter->id }} — {{ $encounter->opened_at->format('d M Y') }} — {{ $encounter->status }}</option>@endforeach</select><div class="form-text">If selected, its patient and doctor must match this order.</div></div>
    <div class="col-12"><fieldset><legend class="h6">Tests</legend><div class="row g-2">@forelse($tests as $test)<div class="col-md-6"><label class="border rounded p-3 w-100 d-flex gap-3"><input class="form-check-input" type="checkbox" name="test_ids[]" value="{{ $test->id }}" @checked(in_array($test->id, old('test_ids', [])))><span><strong>{{ $test->code }} — {{ $test->name }}</strong><br><small class="text-secondary">₹{{ number_format((float) $test->activeVersion->price, 2) }}</small></span></label></div>@empty<div class="text-secondary">No active tests are available.</div>@endforelse</div></fieldset></div>
    <div class="col-12"><label class="form-label" for="clinical_notes">Clinical notes (optional)</label><textarea class="form-control" id="clinical_notes" name="clinical_notes" rows="3" maxlength="2000">{{ old('clinical_notes') }}</textarea></div>
</div><div class="card-footer bg-white d-flex justify-content-end"><button class="btn btn-primary" @disabled($tests->isEmpty())>Create order and issue invoice</button></div></div>
</form>
@endsection

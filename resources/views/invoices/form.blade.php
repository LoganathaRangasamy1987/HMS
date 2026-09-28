@extends('layouts.app')
@section('title', $invoice ? 'Edit invoice draft' : 'New invoice draft')
@section('subtitle', 'Select a patient and up to 20 services. Prices are calculated on the server.')
@section('actions')<a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">All invoices</a>@endsection
@section('content')
<section class="panel p-4">
    <form method="POST" action="{{ $invoice ? route('invoices.update', $invoice) : route('invoices.store') }}">
        @csrf
        @if($invoice) @method('PUT') @endif
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label" for="patient_id">Patient</label><select required class="form-select" id="patient_id" name="patient_id"><option value="">Select patient</option>@foreach($patients as $patient)<option value="{{ $patient->id }}" @selected(old('patient_id', $invoice?->patient_id) == $patient->id)>{{ $patient->uhid }} · {{ $patient->first_name }} {{ $patient->last_name }}</option>@endforeach</select>@error('patient_id')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="appointment_id">Appointment (optional)</label><select class="form-select" id="appointment_id" name="appointment_id"><option value="">No appointment</option>@foreach($appointments as $appointment)<option value="{{ $appointment->id }}" @selected(old('appointment_id', $invoice?->appointment_id) == $appointment->id)>#{{ $appointment->id }} · {{ $appointment->appointment_date->format('d M Y') }} · token {{ $appointment->token_number }} · patient #{{ $appointment->patient_id }}</option>@endforeach</select>@error('appointment_id')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        </div>
        <h2 class="h5">Services</h2>
        <p class="text-secondary small">Choose each service once. Leave unused rows empty.</p>
        @php($rows = old('lines', $invoice?->lines->map(fn ($line) => ['service_item_id' => $line->service_item_id, 'quantity' => $line->quantity])->all() ?? [['service_item_id' => '', 'quantity' => 1]]))
        @for($i = 0; $i < max(5, count($rows)); $i++)
            <div class="row g-2 mb-2"><div class="col-md-9"><label class="visually-hidden" for="service-{{ $i }}">Service {{ $i + 1 }}</label><select class="form-select" id="service-{{ $i }}" name="lines[{{ $i }}][service_item_id]"><option value="">Select service</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(($rows[$i]['service_item_id'] ?? '') == $service->id)>{{ $service->code }} · {{ $service->name }}</option>@endforeach</select></div><div class="col-md-3"><label class="visually-hidden" for="quantity-{{ $i }}">Quantity {{ $i + 1 }}</label><input class="form-control" type="number" min="1" max="1000" id="quantity-{{ $i }}" name="lines[{{ $i }}][quantity]" value="{{ $rows[$i]['quantity'] ?? 1 }}"></div></div>
        @endfor
        @error('lines')<div class="text-danger small">{{ $message }}</div>@enderror
        @if($errors->has('lines.*'))<div class="text-danger small">Check for duplicate services and quantities from 1 to 1000.</div>@endif
        <button class="btn btn-primary mt-3" type="submit">Save draft</button>
    </form>
</section>
@endsection

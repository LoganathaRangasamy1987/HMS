@extends('layouts.app')
@section('title', $order->number)
@section('subtitle', 'Laboratory order, specimen lifecycle, and billing status.')
@section('actions')<a class="btn btn-outline-secondary" href="{{ route('laboratory.orders.index') }}">Back to orders</a>@endsection
@section('content')
<div class="row g-4"><div class="col-lg-8">
@foreach($order->items as $item)
<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white d-flex justify-content-between align-items-center"><div><h2 class="h5 mb-1">{{ $item->test_code }} — {{ $item->test_name }}</h2><small class="text-secondary">Version {{ $item->version }} · {{ $item->versionDefinition->sampleType->name }} · ₹{{ number_format((float) $item->price, 2) }}</small></div><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $item->status) }}</span></div>
<div class="card-body">
@forelse($item->specimens as $specimen)
<div class="border rounded p-3 mb-3"><div class="d-flex flex-wrap justify-content-between gap-2"><div><strong>{{ $specimen->identifier }}</strong> <span class="badge text-bg-{{ $specimen->status === 'REJECTED' ? 'danger' : 'primary' }}">{{ $specimen->status }}</span><br><small class="text-secondary">Attempt {{ $specimen->attempt }} · Collected by {{ $specimen->collectedBy->name }} at {{ $specimen->collected_at->format('d M Y, h:i A') }}</small></div><a class="btn btn-sm btn-outline-secondary" href="{{ route('laboratory.specimens.label', [$order, $specimen]) }}">Print label</a></div>
@if($specimen->rejection_reason)<div class="alert alert-danger py-2 mt-3 mb-0"><strong>Rejected:</strong> {{ $specimen->rejection_reason }}</div>@endif
<ol class="small mt-3 mb-3">@foreach($specimen->events as $event)<li>{{ str_replace('_', ' ', $event->to_status) }} — {{ $event->recorded_at->format('d M Y, h:i A') }} by {{ $event->recordedBy->name }}@if($event->reason) · {{ $event->reason }}@endif</li>@endforeach</ol>
@if($tenant->can('LAB_RESULT.VIEW') && $specimen->results->isNotEmpty())<div class="mb-3"><strong class="small">Result revisions</strong><div class="d-flex flex-wrap gap-2 mt-1">@foreach($specimen->results as $result)<a class="btn btn-sm btn-outline-primary" href="{{ route('laboratory.results.show', $result) }}">Revision {{ $result->revision }} · {{ $result->status }}</a>@endforeach</div></div>@endif
@if($tenant->can('LAB_RESULT.ENTER') && $specimen->status === 'PROCESSING' && $specimen->results->isEmpty())<form method="POST" action="{{ route('laboratory.results.start', $specimen) }}" class="mb-3">@csrf<button class="btn btn-sm btn-primary">Enter results</button></form>@endif
@if($tenant->can('LAB_SPECIMEN.MANAGE') && $specimen->status !== 'REJECTED')<div class="d-flex flex-wrap gap-2">
@if($specimen->status === 'COLLECTED')<form method="POST" action="{{ route('laboratory.specimens.receive', [$order, $specimen]) }}">@csrf @method('PUT')<button class="btn btn-sm btn-primary">Mark received</button></form>@endif
@if($specimen->status === 'RECEIVED')<form method="POST" action="{{ route('laboratory.specimens.process', [$order, $specimen]) }}">@csrf @method('PUT')<button class="btn btn-sm btn-primary">Start processing</button></form>@endif
<form method="POST" action="{{ route('laboratory.specimens.reject', [$order, $specimen]) }}" class="d-flex gap-2 flex-grow-1">@csrf @method('PUT')<label class="visually-hidden" for="reason-{{ $specimen->id }}">Rejection reason</label><input class="form-control form-control-sm" id="reason-{{ $specimen->id }}" name="reason" minlength="5" maxlength="500" placeholder="Rejection reason" required><button class="btn btn-sm btn-outline-danger text-nowrap">Reject specimen</button></form>
</div>@endif</div>
@empty<p class="text-secondary">No specimen has been collected for this test.</p>@endforelse
@php($latest = $item->specimens->last())
@if($tenant->can('LAB_SPECIMEN.MANAGE') && $order->status !== 'CANCELLED' && ($latest === null || $latest->status === 'REJECTED'))<form method="POST" action="{{ route('laboratory.specimens.collect', [$order, $item]) }}">@csrf<button class="btn btn-outline-primary">{{ $latest ? 'Collect replacement specimen' : 'Collect specimen' }}</button></form>@endif
</div></div>
@endforeach
@if($order->clinical_notes)<div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h6">Clinical notes</h2><p class="mb-0">{{ $order->clinical_notes }}</p></div></div>@endif
</div><div class="col-lg-4"><div class="card border-0 shadow-sm mb-4"><div class="card-body"><dl class="row mb-0"><dt class="col-5">Status</dt><dd class="col-7">{{ str_replace('_', ' ', $order->status) }}</dd><dt class="col-5">Patient</dt><dd class="col-7">{{ $order->patient->first_name }} {{ $order->patient->last_name }}<br><small>{{ $order->patient->uhid }}</small></dd><dt class="col-5">Doctor</dt><dd class="col-7">{{ $order->doctorProfile->user->name }}</dd><dt class="col-5">Invoice</dt><dd class="col-7">@if($tenant->can('INVOICE.MANAGE'))<a href="{{ route('invoices.show', $order->invoice) }}">{{ $order->invoice->number }}</a>@else{{ $order->invoice->number }}@endif<br>₹{{ number_format((float) $order->invoice->total, 2) }} · {{ $order->invoice->status }}</dd></dl></div></div>
@if($order->status === 'ORDERED' && $tenant->can('LAB_ORDER.MANAGE'))<div class="card border-danger"><div class="card-body"><h2 class="h6">Cancel order</h2><p class="small text-secondary">Cancellation is blocked after specimen activity or while payment remains collected.</p><form method="POST" action="{{ route('laboratory.orders.cancel', $order) }}">@csrf @method('PUT')<label class="form-label" for="reason">Reason</label><textarea class="form-control mb-3" id="reason" name="reason" required minlength="5" maxlength="500"></textarea><button class="btn btn-outline-danger">Cancel order</button></form></div></div>@endif
@if($order->status === 'CANCELLED')<div class="alert alert-secondary"><strong>Cancelled:</strong> {{ $order->cancellation_reason }}</div>@endif
</div></div>
@endsection

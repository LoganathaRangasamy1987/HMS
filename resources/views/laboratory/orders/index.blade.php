@extends('layouts.app')
@section('title', 'Laboratory orders')
@section('subtitle', 'Create and track billable laboratory requests for this branch.')
@section('actions')
    @if($tenant->can('LAB_ORDER.MANAGE'))<a class="btn btn-primary" href="{{ route('laboratory.orders.create') }}">New order</a>@endif
@endsection
@section('content')
<div class="card border-0 shadow-sm mb-4"><div class="card-body"><form class="row g-3" method="GET">
    <div class="col-md-6"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Order number, UHID, or patient"></div>
    <div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All</option>@foreach(['ORDERED', 'PARTIALLY_COLLECTED', 'COLLECTED', 'RECEIVED', 'PROCESSING', 'RECOLLECTION_REQUIRED', 'RESULT_PARTIAL', 'VERIFIED', 'CANCELLED'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', ucfirst(strtolower($status))) }}</option>@endforeach</select></div>
    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form></div></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Order</th><th>Patient</th><th>Doctor</th><th>Invoice</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><strong>{{ $order->number }}</strong><br><small class="text-secondary">{{ $order->ordered_at->format('d M Y, h:i A') }}</small></td><td>{{ $order->patient->first_name }} {{ $order->patient->last_name }}<br><small>{{ $order->patient->uhid }}</small></td><td>{{ $order->doctorProfile->user->name }}</td><td>{{ $order->invoice->number }}<br><small>₹{{ number_format((float) $order->invoice->total, 2) }}</small></td><td><span class="badge text-bg-{{ $order->status === 'ORDERED' ? 'primary' : 'secondary' }}">{{ $order->status }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('laboratory.orders.show', $order) }}">View</a></td></tr>
@empty<tr><td colspan="6" class="text-center text-secondary py-5">No laboratory orders found.</td></tr>@endforelse
</tbody></table></div>@if($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif</div>
@endsection

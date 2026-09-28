@extends('layouts.app')
@section('title', 'Services')
@section('subtitle', 'Consultation and service prices for this hospital.')
@section('actions')
@if($tenant->can('SERVICE.MANAGE'))<a href="{{ route('services.create') }}" class="btn btn-primary">Add service</a>@endif
@endsection
@section('content')
<section class="panel">
    <form method="GET" action="{{ route('services.index') }}" class="filter-bar">
        <div class="search-field"><label class="visually-hidden" for="service-search">Search services</label><input id="service-search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Code or name"></div>
        <select class="form-select filter-select" name="type" aria-label="Service type"><option value="">All types</option><option value="CONSULTATION" @selected(request('type')==='CONSULTATION')>Consultation</option><option value="SERVICE" @selected(request('type')==='SERVICE')>Service</option></select>
        @if($tenant->can('SERVICE.MANAGE'))<select class="form-select filter-select" name="status" aria-label="Service status"><option value="">All statuses</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select>@endif
        <button class="btn btn-outline-secondary">Filter</button>
    </form>
    <div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Code</th><th>Service</th><th>Type</th><th>Base price</th><th>Tax</th><th>Discount</th><th>Current branch</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($services as $item)
            @php($branchPrice = $item->branchPrices->first())
            <tr><td>{{ $item->code }}</td><td><strong>{{ $item->name }}</strong><small class="d-block">{{ $item->description }}</small></td><td>{{ ucfirst(strtolower($item->type)) }}</td><td>₹{{ $item->base_price }}</td><td>{{ $item->tax_rate_percent }}%</td><td>{{ $item->discount_type }} {{ $item->discount_value }}</td><td>{{ $branchPrice ? ($branchPrice->is_available ? 'Override set' : 'Unavailable') : 'Hospital default' }}</td><td><x-status :value="$item->status"/></td><td>@if($tenant->can('SERVICE.MANAGE'))<a class="btn btn-sm btn-table" href="{{ route('services.edit',$item) }}">Edit</a>@else<a class="btn btn-sm btn-table" href="{{ route('services.quote',$item) }}">Price</a>@endif</td></tr>
        @empty<tr><td colspan="9">No services found.</td></tr>@endforelse
    </tbody></table></div>
    @if($services->hasPages())<div class="panel-pagination">{{ $services->links() }}</div>@endif
</section>
@endsection

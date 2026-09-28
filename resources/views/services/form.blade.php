@extends('layouts.app')
@section('title', $item->exists ? 'Edit service' : 'Add service')
@section('subtitle', 'Configure hospital pricing and optional branch-specific rules.')
@section('actions')<a href="{{ route('services.index') }}" class="btn btn-outline-secondary">All services</a>@endsection
@section('content')
<section class="panel mb-4"><div class="panel-body"><h2>Hospital default</h2>
<form method="POST" action="{{ $item->exists ? route('services.update',$item) : route('services.store') }}">@csrf @if($item->exists)@method('PUT')@endif
<div class="row g-3">
<div class="col-md-3"><x-form.input name="code" label="Service code" :value="$item->code" required maxlength="40"/></div>
<div class="col-md-5"><x-form.input name="name" label="Name" :value="$item->name" required maxlength="150"/></div>
<div class="col-md-4"><x-form.select name="type" label="Type" :value="$item->type" :options="['CONSULTATION'=>'Consultation','SERVICE'=>'Service']" required/></div>
<div class="col-12"><x-form.textarea name="description" label="Description" :value="$item->description" maxlength="1000"/></div>
<div class="col-md-3"><x-form.input name="base_price" label="Price (INR)" type="number" step="0.01" min="0" :value="$item->base_price ?? '0.00'" required/></div>
<div class="col-md-3"><x-form.input name="tax_rate_percent" label="Tax rate (%)" type="number" step="0.01" min="0" max="100" :value="$item->tax_rate_percent ?? '0.00'" required/></div>
<div class="col-md-3"><x-form.select name="discount_type" label="Discount type" :value="$item->discount_type" :options="['none'=>'None','percentage'=>'Percentage','fixed'=>'Fixed INR']" required/></div>
<div class="col-md-3"><x-form.input name="discount_value" label="Discount value" type="number" step="0.01" min="0" :value="$item->discount_value ?? '0.00'" required/></div>
<div class="col-md-3"><x-form.select name="status" label="Status" :value="$item->status" :options="['active'=>'Active','inactive'=>'Inactive']" required/></div>
</div><button class="btn btn-primary mt-3">{{ $item->exists ? 'Save service' : 'Create service' }}</button></form>
</div></section>
@if($item->exists)
<section class="panel"><div class="panel-body"><h2>Branch override</h2><p>Leave price and tax blank to inherit the hospital default. To override a discount, enter both its type and value. Mark a branch unavailable to exclude this service there.</p>
<form method="POST" action="{{ route('services.branch-price',$item) }}">@csrf @method('PUT')<div class="row g-3">
<div class="col-md-4"><x-form.select name="branch_id" label="Branch" :options="$branches->pluck('name','id')->all()" required/></div>
<div class="col-md-4"><x-form.input name="base_price" label="Branch price (INR)" type="number" step="0.01" min="0"/></div>
<div class="col-md-4"><x-form.input name="tax_rate_percent" label="Branch tax (%)" type="number" step="0.01" min="0" max="100"/></div>
<div class="col-md-4"><x-form.select name="discount_type" label="Discount type" :options="[''=>'Inherit','none'=>'None','percentage'=>'Percentage','fixed'=>'Fixed INR']"/></div>
<div class="col-md-4"><x-form.input name="discount_value" label="Discount value" type="number" step="0.01" min="0"/></div>
<div class="col-md-4"><x-form.select name="is_available" label="Availability" :options="[1=>'Available',0=>'Unavailable']" required/></div>
</div><button class="btn btn-primary mt-3">Save branch rule</button></form>
<div class="table-responsive mt-4"><table class="table app-table"><thead><tr><th>Branch</th><th>Price</th><th>Tax</th><th>Discount</th><th>Available</th></tr></thead><tbody>@forelse($item->branchPrices as $price)<tr><td>{{ $branches->firstWhere('id',$price->branch_id)?->name }}</td><td>{{ $price->base_price ?? 'Inherit' }}</td><td>{{ $price->tax_rate_percent ?? 'Inherit' }}</td><td>{{ $price->discount_type ?? 'Inherit' }} {{ $price->discount_value }}</td><td>{{ $price->is_available ? 'Yes' : 'No' }}</td></tr>@empty<tr><td colspan="5">All branches inherit the hospital default.</td></tr>@endforelse</tbody></table></div>
</div></section>
@endif
@endsection

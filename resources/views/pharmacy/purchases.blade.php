@extends('layouts.app')
@section('title', 'Pharmacy stock')
@section('content')
<div class="page-heading"><div><p class="eyebrow">Pharmacy</p><h1>Purchases and batch stock</h1><p>Post supplier receipts and review branch stock by batch and expiry.</p></div></div>
@if($errors->any())<div class="alert alert-danger"><strong>The receipt was not posted.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($tenant->can('PHARMACY_PURCHASE.MANAGE'))
<section class="panel p-4 mb-4"><h2 class="h5">Receive supplier stock</h2><form method="POST" action="{{ route('pharmacy.purchases.store') }}" class="row g-3">@csrf
    <input type="hidden" name="request_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
    <div class="col-md-4"><x-form.select name="pharmacy_supplier_id" label="Supplier" :options="$suppliers->pluck('name','id')->all()" placeholder="Select supplier" required/></div>
    <div class="col-md-4"><x-form.input name="supplier_invoice_number" label="Supplier invoice" required maxlength="100"/></div>
    <div class="col-md-4"><x-form.input name="purchase_date" type="date" label="Purchase date" :value="now($tenant->branch()->timezone)->toDateString()" required/></div>
    <div class="col-md-4"><x-form.select name="items[0][medicine_id]" label="Medicine" :options="$medicines->mapWithKeys(fn($medicine) => [$medicine->id => $medicine->code.' · '.$medicine->name])->all()" placeholder="Select medicine" required/></div>
    <div class="col-md-2"><x-form.input name="items[0][batch_number]" label="Batch number" required maxlength="100"/></div>
    <div class="col-md-3"><x-form.input name="items[0][manufactured_on]" type="date" label="Manufactured on"/></div>
    <div class="col-md-3"><x-form.input name="items[0][expires_on]" type="date" label="Expires on" required/></div>
    <div class="col-md-2"><x-form.input name="items[0][quantity]" type="number" label="Purchased qty" value="1" min="0.001" step="0.001" required/></div>
    <div class="col-md-2"><x-form.input name="items[0][free_quantity]" type="number" label="Free qty" value="0" min="0" step="0.001"/></div>
    <div class="col-md-2"><x-form.input name="items[0][unit_cost]" type="number" label="Unit cost (₹)" min="0.01" step="0.01" required/></div>
    <div class="col-md-2"><x-form.input name="items[0][sale_price]" type="number" label="Sale price (₹)" min="0.01" step="0.01" required/></div>
    <div class="col-md-2"><x-form.input name="items[0][tax_rate_percent]" type="number" label="Tax %" value="0" min="0" max="100" step="0.01" required/></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Post receipt</button></div>
</form><p class="small text-secondary mt-3 mb-0">Posted receipts and stock movements are permanent. Use a separate adjustment workflow for later corrections.</p></section>
@endif
<section class="panel mb-4"><div class="panel-heading"><div><h2>Supplier receipts <span class="count-badge">{{ $purchases->total() }}</span></h2><p>Receipts posted for this branch</p></div></div><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Receipt</th><th>Supplier invoice</th><th>Supplier</th><th>Date</th><th>Total</th><th>Received by</th></tr></thead><tbody>@forelse($purchases as $purchase)<tr><td><a href="{{ route('pharmacy.purchases.show', $purchase) }}"><strong>{{ $purchase->number }}</strong></a></td><td>{{ $purchase->supplier_invoice_number }}</td><td>{{ $purchase->supplier->name }}</td><td>{{ $purchase->purchase_date->format('d M Y') }}</td><td>₹{{ number_format((float) $purchase->total, 2) }}</td><td>{{ $purchase->receivedBy->name }}</td></tr>@empty<tr><td colspan="6">No supplier receipts yet.</td></tr>@endforelse</tbody></table></div>@if($purchases->hasPages())<div class="panel-pagination">{{ $purchases->links() }}</div>@endif</section>
<section class="panel"><div class="panel-heading"><div><h2>Batch stock <span class="count-badge">{{ $batches->count() }}</span></h2><p>Current on-hand stock and expiry dates</p></div></div><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Medicine</th><th>Batch</th><th>Expiry</th><th>On hand</th><th>Cost / sale</th><th>Status</th></tr></thead><tbody>@forelse($batches as $batch)<tr><td><strong>{{ $batch->medicine->code }} · {{ $batch->medicine->name }}</strong></td><td>{{ $batch->batch_number }}</td><td>{{ $batch->expires_on->format('d M Y') }}</td><td>{{ $batch->on_hand_quantity }}</td><td>₹{{ $batch->unit_cost }} / ₹{{ $batch->sale_price }}</td><td><x-status :value="$batch->status"/></td></tr>@empty<tr><td colspan="6">No batch stock yet.</td></tr>@endforelse</tbody></table></div></section>
@endsection

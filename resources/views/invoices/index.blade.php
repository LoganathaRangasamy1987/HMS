@extends('layouts.app')
@section('title', 'Invoices')
@section('subtitle', 'Search invoices, collect balances, and open printable financial documents.')
@section('actions')<a href="{{ route('invoices.create') }}" class="btn btn-primary">New invoice</a>@endsection
@section('content')
<section class="panel p-4 mb-4">
    <form method="GET" action="{{ route('invoices.index') }}" class="row g-3 align-items-end">
        <div class="col-lg-4"><label class="form-label" for="invoice-search">Invoice or patient</label><input class="form-control" id="invoice-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Number, UHID, name, or mobile"></div>
        <div class="col-md-3 col-lg-2"><label class="form-label" for="invoice-status">Status</label><select class="form-select" id="invoice-status" name="status"><option value="">All statuses</option>@foreach(['DRAFT', 'ISSUED', 'PARTIALLY_PAID', 'PAID', 'VOID'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str_replace('_', ' ', $status) }}</option>@endforeach</select></div>
        <div class="col-md-3 col-lg-2"><label class="form-label" for="invoice-from">From</label><input class="form-control" id="invoice-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="col-md-3 col-lg-2"><label class="form-label" for="invoice-to">To</label><input class="form-control" id="invoice-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="col-md-3 col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Search</button><a class="btn btn-outline-secondary" href="{{ route('invoices.index') }}">Clear</a></div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" id="invoice-outstanding" name="outstanding" type="checkbox" value="1" @checked(request()->boolean('outstanding'))><label class="form-check-label" for="invoice-outstanding">Outstanding balances only</label> <span class="text-secondary small">({{ $outstandingCount }} at this branch)</span></div></div>
    </form>
</section>
<section class="panel">
    <div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Invoice</th><th>Patient</th><th>Status</th><th>Total</th><th>Received</th><th>Balance</th><th>Created</th><th></th></tr></thead><tbody>
    @forelse($invoices as $invoice)
        @php($summary = $paymentSummaries[$invoice->id])
        <tr><td>{{ $invoice->number ?? 'Draft #'.$invoice->id }}</td><td>{{ $invoice->patient->uhid }} · {{ $invoice->patient->first_name }} {{ $invoice->patient->last_name }}</td><td><x-status :value="$invoice->status"/></td><td>₹{{ $summary['adjusted_total'] }}</td><td>₹{{ $summary['net_paid'] }}</td><td><strong>₹{{ $summary['balance'] }}</strong></td><td>{{ $invoice->created_at->format('d M Y') }}</td><td><a class="btn btn-sm btn-table" href="{{ route('invoices.show', $invoice) }}">Open</a></td></tr>
    @empty<tr><td colspan="8">No invoices match these filters.</td></tr>@endforelse
    </tbody></table></div>
    @if($invoices->hasPages())<div class="panel-pagination">{{ $invoices->links() }}</div>@endif
</section>
@endsection

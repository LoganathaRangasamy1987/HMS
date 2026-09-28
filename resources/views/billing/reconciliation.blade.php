@extends('layouts.app')
@section('title', 'Daily reconciliation')
@section('subtitle', 'Reconcile branch collections and refunds by local business date.')
@section('content')
<section class="panel p-4 mb-4">
    <form method="GET" action="{{ route('billing.reconciliation') }}" class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-3"><label class="form-label" for="reconciliation-date">Business date</label><input class="form-control" id="reconciliation-date" name="date" type="date" value="{{ $report['date'] }}" required></div>
        <div class="col-sm-6 col-lg-3"><button class="btn btn-primary" type="submit">View reconciliation</button></div>
        <div class="col-lg-6 text-lg-end text-secondary small">Timezone: {{ $report['timezone'] }}</div>
    </form>
</section>
<section class="row g-3 mb-4">
    <div class="col-md-4"><div class="panel p-4 h-100"><div class="text-secondary small text-uppercase">Gross collected</div><strong class="display-6">₹{{ $report['gross_collected'] }}</strong><div class="text-secondary">{{ $report['payment_count'] }} payment entries</div></div></div>
    <div class="col-md-4"><div class="panel p-4 h-100"><div class="text-secondary small text-uppercase">Refunded</div><strong class="display-6">₹{{ $report['refunded'] }}</strong><div class="text-secondary">{{ $report['refund_count'] }} refund entries</div></div></div>
    <div class="col-md-4"><div class="panel p-4 h-100"><div class="text-secondary small text-uppercase">Net collections</div><strong class="display-6">₹{{ $report['net_collected'] }}</strong><div class="text-secondary">Gross less refunds recorded this date</div></div></div>
</section>
<section class="panel p-4 mb-4"><h2 class="h5">Collections by mode</h2><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Mode</th><th>Entries</th><th class="text-end">Amount</th></tr></thead><tbody>@forelse($report['modes'] as $mode => $summary)<tr><td>{{ str_replace('_', ' ', $mode) }}</td><td>{{ $summary['count'] }}</td><td class="text-end">₹{{ $summary['total'] }}</td></tr>@empty<tr><td colspan="3">No collections recorded for this date.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel p-4 mb-4"><h2 class="h5">Payment ledger</h2><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Time</th><th>Invoice</th><th>Patient</th><th>Mode</th><th>Reference</th><th class="text-end">Amount</th></tr></thead><tbody>@forelse($report['payments'] as $payment)<tr><td>{{ $payment->received_at->setTimezone($report['timezone'])->format('H:i') }}</td><td><a href="{{ route('invoices.show', $payment->invoice) }}">{{ $payment->invoice->number }}</a></td><td>{{ $payment->invoice->patient->uhid }} · {{ $payment->invoice->patient->first_name }} {{ $payment->invoice->patient->last_name }}</td><td>{{ str_replace('_', ' ', $payment->mode) }}</td><td>{{ $payment->reference ?: '—' }}</td><td class="text-end">₹{{ $payment->amount }}</td></tr>@empty<tr><td colspan="6">No payments recorded for this date.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel p-4"><h2 class="h5">Refund ledger</h2><div class="table-responsive"><table class="table app-table mb-0"><thead><tr><th>Time</th><th>Invoice</th><th>Patient</th><th>Original mode</th><th>Reason</th><th class="text-end">Amount</th></tr></thead><tbody>@forelse($report['refunds'] as $refund)<tr><td>{{ $refund->recorded_at->setTimezone($report['timezone'])->format('H:i') }}</td><td><a href="{{ route('invoices.show', $refund->invoice) }}">{{ $refund->invoice->number }}</a></td><td>{{ $refund->invoice->patient->uhid }} · {{ $refund->invoice->patient->first_name }} {{ $refund->invoice->patient->last_name }}</td><td>{{ str_replace('_', ' ', $refund->payment->mode) }}</td><td>{{ $refund->reason }}</td><td class="text-end">₹{{ $refund->amount }}</td></tr>@empty<tr><td colspan="6">No refunds recorded for this date.</td></tr>@endforelse</tbody></table></div></section>
@endsection

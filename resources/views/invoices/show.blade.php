@extends('layouts.app')
@section('title', $invoice->number ?? 'Invoice draft #'.$invoice->id)
@section('subtitle', 'Patient '.$invoice->patient->uhid.' · '.$invoice->patient->first_name.' '.$invoice->patient->last_name)
@section('actions')
<a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">All invoices</a>
@if($invoice->status !== 'DRAFT')<a href="{{ route('invoices.print', $invoice) }}" class="btn btn-outline-secondary">Print invoice</a>@endif
@if($invoice->status === 'DRAFT')<a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-outline-secondary">Edit draft</a><form method="POST" action="{{ route('invoices.issue', $invoice) }}" class="d-inline">@csrf<button class="btn btn-primary" type="submit">Issue invoice</button></form>@endif
@endsection
@section('content')
<section class="panel p-4">
    <p><strong>Status:</strong> {{ $invoice->status }} @if($invoice->appointment) · Appointment #{{ $invoice->appointment->id }} @endif @if($invoice->issued_at) · Issued {{ $invoice->issued_at->format('d M Y H:i') }} @endif</p>
    <div class="table-responsive"><table class="table app-table"><thead><tr><th>Service</th><th>Qty</th><th>Unit</th><th>Subtotal</th><th>Discount</th><th>Tax</th><th>Total</th></tr></thead><tbody>
    @foreach($invoice->lines as $line)<tr><td>{{ $line->service_code }} · {{ $line->description }}</td><td>{{ $line->quantity }}</td><td>₹{{ $line->unit_price }}</td><td>₹{{ $line->subtotal }}</td><td>₹{{ $line->discount_amount }}</td><td>₹{{ $line->tax_amount }}</td><td>₹{{ $line->total }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="text-end"><p>Subtotal ₹{{ $invoice->subtotal }} · Discount ₹{{ $invoice->discount_total }} · Tax ₹{{ $invoice->tax_total }}</p><strong class="h4">Original total ₹{{ $invoice->total }}</strong></div>
    @if($invoice->status === 'DRAFT')<p class="text-secondary small mt-3 mb-0">Draft prices are recalculated when edited. Issuing locks these charges and assigns the invoice number.</p>@endif
</section>
@if($invoice->status !== 'DRAFT')
<section class="panel p-4 mt-4">
    <h2 class="h5">Payments</h2>
    <p><strong>Gross received:</strong> ₹{{ $paymentSummary['gross_paid'] }} · <strong>Refunded:</strong> ₹{{ $paymentSummary['refunded_total'] }} · <strong>Net received:</strong> ₹{{ $paymentSummary['net_paid'] }} · <strong>Adjusted total:</strong> ₹{{ $paymentSummary['adjusted_total'] }} · <strong>Balance:</strong> ₹{{ $paymentSummary['balance'] }}</p>
    <div class="table-responsive"><table class="table app-table"><thead><tr><th>Received</th><th>Mode</th><th>Reference</th><th>Amount</th><th>Refunded</th><th></th></tr></thead><tbody>
        @forelse($invoice->payments as $payment)
        @php($refunded = $payment->refunds->sum(fn ($refund) => (float) $refund->amount))
        <tr><td>{{ $payment->received_at->format('d M Y H:i') }}</td><td>{{ str_replace('_', ' ', $payment->mode) }}</td><td>{{ $payment->reference ?: '—' }}</td><td>₹{{ $payment->amount }}</td><td>₹{{ number_format($refunded, 2, '.', '') }}</td><td><a class="btn btn-sm btn-table mb-2" href="{{ route('invoices.payments.receipt', [$invoice, $payment]) }}">Receipt</a>
            @if($tenant->can('FINANCIAL_ADJUSTMENT.MANAGE') && $invoice->status !== 'VOID' && $refunded < (float) $payment->amount)
            <form method="POST" action="{{ route('invoices.payments.refunds.store', [$invoice, $payment]) }}" class="d-flex gap-2 flex-wrap">@csrf
                <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <input class="form-control form-control-sm" style="width: 8rem" name="amount" type="number" min="0.01" max="{{ number_format((float) $payment->amount - $refunded, 2, '.', '') }}" step="0.01" aria-label="Refund amount" required>
                <input class="form-control form-control-sm" style="width: 12rem" name="reason" maxlength="500" placeholder="Refund reason" aria-label="Refund reason" required>
                <button class="btn btn-sm btn-outline-danger" type="submit">Refund</button>
            </form>
            @endif
        </td></tr>
        @empty<tr><td colspan="6">No payments recorded yet.</td></tr>@endforelse
    </tbody></table></div>
    @if($tenant->can('PAYMENT.MANAGE') && in_array($invoice->status, ['ISSUED', 'PARTIALLY_PAID'], true) && $paymentSummary['balance'] !== '0.00')
    <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="row g-3 mt-2">@csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="col-md-3"><label class="form-label" for="payment-amount">Amount (INR)</label><input class="form-control" id="payment-amount" name="amount" type="number" min="0.01" max="{{ $paymentSummary['balance'] }}" step="0.01" value="{{ old('amount') }}" required>@error('amount')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label class="form-label" for="payment-mode">Mode</label><select class="form-select" id="payment-mode" name="mode" required><option value="">Select mode</option>@foreach(['CASH' => 'Cash', 'UPI' => 'UPI', 'CARD' => 'Card', 'BANK_TRANSFER' => 'Bank transfer'] as $code => $label)<option value="{{ $code }}" @selected(old('mode') === $code)>{{ $label }}</option>@endforeach</select>@error('mode')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label" for="payment-reference">Reference (required except cash)</label><input class="form-control" id="payment-reference" name="reference" maxlength="100" value="{{ old('reference') }}">@error('reference')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Record payment</button></div>
    </form>
    @endif
</section>
@if($invoice->financialAdjustments->isNotEmpty())
<section class="panel p-4 mt-4"><h2 class="h5">Financial actions</h2><div class="table-responsive"><table class="table app-table"><thead><tr><th>Recorded</th><th>Type</th><th>Amount</th><th>Reason</th></tr></thead><tbody>
    @foreach($invoice->financialAdjustments as $adjustment)<tr><td>{{ $adjustment->recorded_at->format('d M Y H:i') }}</td><td>{{ $adjustment->type }}</td><td>₹{{ $adjustment->amount }}</td><td>{{ $adjustment->reason }}</td></tr>@endforeach
</tbody></table></div></section>
@endif
@if($tenant->can('FINANCIAL_ADJUSTMENT.MANAGE') && !in_array($invoice->status, ['DRAFT', 'VOID'], true))
<section class="panel p-4 mt-4"><h2 class="h5">Administrator financial action</h2>
    <form method="POST" action="{{ route('invoices.adjustments.store', $invoice) }}" class="row g-3">@csrf
        <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div class="col-md-2"><label class="form-label" for="adjustment-type">Type</label><select class="form-select" id="adjustment-type" name="type" required><option value="CREDIT">Credit</option><option value="DEBIT">Debit</option></select></div>
        <div class="col-md-3"><label class="form-label" for="adjustment-amount">Amount (INR)</label><input class="form-control" id="adjustment-amount" name="amount" type="number" min="0.01" step="0.01" required></div>
        <div class="col-md-5"><label class="form-label" for="adjustment-reason">Reason</label><input class="form-control" id="adjustment-reason" name="reason" minlength="3" maxlength="500" required></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100" type="submit">Record</button></div>
    </form>
    @if($paymentSummary['gross_paid'] === '0.00' && $invoice->financialAdjustments->isEmpty())
    <form method="POST" action="{{ route('invoices.void', $invoice) }}" class="row g-3 mt-2">@csrf
        <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div class="col-md-10"><label class="form-label" for="void-reason">Void reason</label><input class="form-control" id="void-reason" name="reason" minlength="3" maxlength="500" required></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-danger w-100" type="submit">Void invoice</button></div>
    </form>
    @endif
</section>
@endif
@endif
@endsection

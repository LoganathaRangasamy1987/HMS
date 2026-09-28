@extends('layouts.print')
@section('title', 'Receipt '.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT))
@section('back', route('invoices.show', $invoice))
@section('content')
<header class="header"><div><h1>{{ $hospital->name }}</h1><div class="muted">{{ $branch->name }}<br>{{ $branch->address }}@if($branch->city), {{ $branch->city }}@endif<br>{{ $branch->phone }} · {{ $branch->email }}</div></div><div class="meta"><h1>Payment receipt</h1><strong>RCT-{{ str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT) }}</strong><br><span class="muted">{{ $payment->received_at->format('d M Y, H:i') }}</span></div></header>
<section class="grid"><div><h2>Received from</h2><strong>{{ $invoice->patient->first_name }} {{ $invoice->patient->last_name }}</strong><br>{{ $invoice->patient->uhid }}<br>{{ $invoice->patient->mobile }}</div><div><h2>Against invoice</h2><strong>{{ $invoice->number }}</strong><br>Invoice total: ₹{{ $invoice->total }}<br>Current balance: ₹{{ $paymentSummary['balance'] }}</div></section>
<h2>Payment details</h2><table><tbody><tr><th>Amount received</th><td class="number"><strong>₹{{ $payment->amount }}</strong></td></tr><tr><th>Mode</th><td class="number">{{ str_replace('_', ' ', $payment->mode) }}</td></tr><tr><th>Reference</th><td class="number">{{ $payment->reference ?: '—' }}</td></tr><tr><th>Refunded against this payment</th><td class="number">₹{{ number_format((float) $payment->refunds->sum('amount'), 2, '.', '') }}</td></tr></tbody></table>
<div class="footer">Computer-generated receipt · Generated {{ now($branch->timezone)->format('d M Y, H:i') }}</div>
@endsection

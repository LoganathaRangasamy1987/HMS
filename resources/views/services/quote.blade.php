@extends('layouts.app')
@section('title', 'Service price')
@section('subtitle', $item->name.' · '.$branch->name)
@section('actions')<a href="{{ route('services.index') }}" class="btn btn-outline-secondary">All services</a>@endsection
@section('content')
<section class="panel"><div class="panel-body"><h2>{{ $item->name }}</h2><p>{{ $item->code }} · {{ $item->type }}</p><dl class="row"><dt class="col-sm-5">Base price</dt><dd class="col-sm-7">₹{{ $quote['base_price'] }}</dd><dt class="col-sm-5">Discount</dt><dd class="col-sm-7">− ₹{{ $quote['discount_amount'] }} ({{ $quote['discount_type'] }} {{ $quote['discount_value'] }})</dd><dt class="col-sm-5">Tax after discount</dt><dd class="col-sm-7">₹{{ $quote['tax_amount'] }} at {{ $quote['tax_rate_percent'] }}%</dd><dt class="col-sm-5">Total</dt><dd class="col-sm-7"><strong>₹{{ $quote['total'] }}</strong></dd></dl><p class="text-secondary">Configured catalog price for {{ $branch->name }}; an invoice is issued separately.</p></div></section>
@endsection

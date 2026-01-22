@extends('layouts.app')

@section('title', "Confirm Delivery #{$saleDelivery->reference}")

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.index') }}">Sale Deliveries</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}">{{ $saleDelivery->reference }}</a></li>
    <li class="breadcrumb-item active">Confirm</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    @include('utils.alerts')

    <div class="card">
        <div class="card-body">
            <div class="mb-2">
                <h5 class="mb-0">Confirm Stock Out</h5>
                <small class="text-muted">Once confirmed, stock will be deducted and cannot be confirmed again.</small>
            </div>

            <hr>

            <div class="table-responsive mb-3">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($saleDelivery->items as $it)
                            <tr>
                                <td>{{ $it->product_name ?? optional($it->product)->product_name }}</td>
                                <td class="text-end">{{ number_format((int)$it->quantity) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form action="{{ route('sale-deliveries.confirm.store', $saleDelivery->id) }}" method="POST"
                  onsubmit="return confirm('Confirm this delivery? Stock will be deducted.');">
                @csrf
                <button type="submit" class="btn btn-primary">
                    Yes, Confirm <i class="bi bi-check-lg"></i>
                </button>
                <a href="{{ route('sale-deliveries.show', $saleDelivery->id) }}" class="btn btn-light">Back</a>
            </form>
        </div>
    </div>
</div>
@endsection

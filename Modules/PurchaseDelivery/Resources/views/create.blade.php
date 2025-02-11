@extends('layouts.app')

@section('title', 'Create Purchase Delivery')

@section('content')
<div class="container">
    <form action="{{ route('purchase-deliveries.store') }}" method="POST">
        @csrf
        <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">

        <div class="card">
            <div class="card-header">
                <h3>New Purchase Delivery</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label>Supplier</label>
                        <input type="text" class="form-control" value="{{ $purchaseOrder->supplier->supplier_name }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label>Shipping Address</label>
                        <textarea class="form-control" name="shipping_address">{{ old('shipping_address', $purchaseOrder->supplier->address) }}</textarea>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label>Date</label>
                        <input type="date" class="form-control" name="date" value="{{ old('date', now()->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-4">
                        <label>Transaction No.</label>
                        <input type="text" class="form-control" value="[Auto]" readonly>
                    </div>

                    <div class="col-md-4">
                        <label>Purchase Order</label>
                        <input type="text" class="form-control" value="{{ $purchaseOrder->reference }}" readonly>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label>Ship Via</label>
                        <input type="text" class="form-control" name="ship_via">
                    </div>
                    <div class="col-md-4">
                        <label>Tracking No.</label>
                        <input type="text" class="form-control" name="tracking_number">
                    </div>
                </div>

                <!-- Product Table -->
                <div class="table-responsive mt-4">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Description</th>
                                <th>Qty</th>
                                <th>Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchaseOrder->purchaseOrderDetails->filter(fn($detail) => ($detail->quantity - $detail->fulfilled_quantity) > 0) as $detail)
                            <tr>
                                <td>
                                    {{ $detail->product_name }} <br>
                                    <span class="badge bg-success">{{ $detail->product_code }}</span>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="description[{{ $detail->id }}]" placeholder="Enter description">
                                </td>
                                <td>
                                    <input type="number" class="form-control qty-input" 
                                        name="quantity[{{ $detail->id }}]" 
                                        value="{{ $detail->quantity - $detail->fulfilled_quantity }}" 
                                        min="0"
                                        max="{{ $detail->quantity - $detail->fulfilled_quantity }}" 
                                        data-product-id="{{ $detail->id }}" 
                                        data-max="{{ $detail->quantity - $detail->fulfilled_quantity }}">
                                    <small class="text-muted">Remaining: <span class="remaining-qty" id="remaining-qty-{{ $detail->id }}">0</span></small>
                                </td>
                                <td>
                                    <input type="text" class="form-control" value="{{ $detail->unit ?? 'Unit' }}" readonly>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <label>Note</label>
                        <textarea class="form-control" name="note"></textarea>
                    </div>
                </div>

                <div class="mt-4 text-right">
                    <button type="submit" class="btn btn-primary">Create Purchase Delivery</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('page_scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    let qtyInputs = document.querySelectorAll(".qty-input");

    qtyInputs.forEach(function(input) {
        input.addEventListener("input", function() {
            let productId = this.dataset.productId;
            let maxQty = parseInt(this.dataset.max);
            let enteredQty = parseInt(this.value) || 0; // Convert input to number

            let remainingQty = maxQty - enteredQty;
            if (remainingQty < 0) remainingQty = 0; // Prevent negative values

            document.getElementById("remaining-qty-" + productId).innerText = remainingQty;
        });
    });
});
</script>
@endpush

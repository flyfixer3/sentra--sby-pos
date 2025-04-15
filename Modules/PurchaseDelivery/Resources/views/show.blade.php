@extends('layouts.app')

@section('title', "Purchase Delivery #$purchaseDelivery->id")

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h3>Purchase Delivery #{{ $purchaseDelivery->id }}
                <span class="badge badge-{{ $purchaseDelivery->status == 'Open' ? 'warning' : ($purchaseDelivery->status == 'Completed' ? 'success' : 'danger') }}">
                    {{ ucfirst($purchaseDelivery->status) }}
                </span>
            </h3>
            <a href="#" class="text-primary">View journal entry</a>
        </div>

        <div class="card-body">
            <div class="row mb-3">
                <!-- Left: Supplier & Address -->
                <div class="col-md-6">
                    <h5 class="mb-2"><strong>Vendor:</strong> 
                        <a href="#" class="text-primary">{{ $purchaseDelivery->purchaseOrder->supplier->supplier_name }}</a>
                    </h5>
                    <h5 class="mb-2"><strong>Email:</strong> -</h5>
                    <h5 class="mb-2"><strong>Shipping Address:</strong> {{ $purchaseDelivery->purchaseOrder->supplier->address ?? '-' }}</h5>
                </div>

                <!-- Right: Delivery Information -->
                <div class="col-md-6">
                    <h5 class="mb-2"><strong>Shipping Date:</strong> {{ \Carbon\Carbon::parse($purchaseDelivery->date)->format('d/m/Y') }}</h5>
                    <h5 class="mb-2"><strong>Ship via:</strong> {{ $purchaseDelivery->ship_via ?? '-' }}</h5>
                    <h5 class="mb-2"><strong>Tracking No.:</strong> {{ $purchaseDelivery->tracking_number ?? '-' }}</h5>
                    <h5 class="mb-2"><strong>Transaction No.:</strong> Purchase Delivery #{{ $purchaseDelivery->id }}</h5>
                    <h5 class="mb-2"><strong>Order No.:</strong> 
                        <a href="{{ route('purchase-orders.show', $purchaseDelivery->purchaseOrder->id) }}" class="text-primary">
                            Purchase Order #{{ $purchaseDelivery->purchaseOrder->id }}
                        </a>
                    </h5>
                    <h5 class="mb-2"><strong>Tags:</strong> -</h5>
                </div>
            </div>

            <!-- Products Table -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Units</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseDelivery->purchaseDeliveryDetails as $detail)
                        <tr>
                            <td>
                                <a href="#" class="text-primary">{{ $detail->product_name }}</a><br>
                                <span class="badge bg-success">{{ $detail->product_code }}</span>
                            </td>
                            <td>{{ $detail->description ?? '-' }}</td>
                            <td>{{ $detail->quantity }}</td>
                            <td>{{ $detail->unit ?? 'Unit' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Message & Memo -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <h5><strong>Note:</strong> {{ $purchaseDelivery->note ?? '-' }}</h5>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="mt-4">
                <p class="text-muted">Last updated by <strong>{{ auth()->user()->name }}</strong> on 
                    {{ \Carbon\Carbon::parse($purchaseDelivery->updated_at)->format('d/m/Y h:i:s A T') }}
                </p>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-between mt-4">
                <form action="{{ route('purchase-deliveries.destroy', $purchaseDelivery->id) }}" method="POST" class="d-inline-block delete-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger delete-btn">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>
                <div>
                    <a href="{{ route('purchase-deliveries.edit', $purchaseDelivery->id) }}" class="btn btn-sm btn-warning">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <a href="{{ route('purchases.createFromDelivery', ['purchase_delivery' => $purchaseDelivery]) }}" class="btn btn-sm btn-primary">
                        Create Invoice
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

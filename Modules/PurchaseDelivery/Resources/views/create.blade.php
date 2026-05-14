@extends('layouts.app')

@section('title', 'Create Purchase Delivery')

@push('page_css')
<style>
    .pd-header{
        display:flex;align-items:center;justify-content:space-between;gap:12px;
        padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.06);
    }
    .pd-title{margin:0;font-weight:700;font-size:16px;}
    .pd-sub{margin:2px 0 0;font-size:12px;color:#6c757d;}
    .pd-badge{
        display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;
        font-size:12px;background:rgba(13,110,253,.08);color:#0d6efd;border:1px solid rgba(13,110,253,.18);
        white-space:nowrap;
    }
    .pd-table thead th{white-space:nowrap;}
    .pd-qty-wrap{display:flex;align-items:center;gap:8px;}
    .pd-qty-wrap input{max-width:110px;}
    .pd-mini{font-size:12px;color:#6c757d;}
    .pd-remaining{font-weight:600;}
</style>
@endpush

@section('content')
<div class="container-fluid">
    <form action="{{ route('purchase-deliveries.store') }}" method="POST"
          data-confirm-submit="true"
          data-delivery-confirm-submit="true"
          data-confirm-title="Create Purchase Delivery?"
          data-confirm-message="Please confirm the item quantities before creating this Purchase Delivery. Stock will enter only after delivery confirmation."
          data-confirm-confirm-text="Create Delivery"
          data-confirm-cancel-text="Cancel"
          data-confirm-icon="warning"
          data-confirm-require-items="true"
          data-item-validation="purchase-delivery"
          data-confirm-items-message="Please input at least one item quantity before submitting this Purchase Delivery.">
        @csrf
        <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">

        <div class="card">
            <div class="pd-header">
                <div>
                    <h3 class="pd-title">New Purchase Delivery</h3>
                    <p class="pd-sub mb-0">
                        Based on PO:
                        <span class="pd-badge"><i class="bi bi-receipt"></i> {{ $purchaseOrder->reference }}</span>
                    </p>
                </div>
                <div class="text-right">
                    <span class="pd-badge">
                        <i class="bi bi-building"></i>
                        {{ $purchaseOrder->branch?->name ?? 'Branch: -' }}
                    </span>
                </div>
            </div>

            <div class="card-body">
                @include('utils.alerts')

                {{-- Top info --}}
                <div class="row">
                    <div class="col-md-6">
                        <label class="mb-1">Supplier</label>
                        <input type="text" class="form-control"
                               value="{{ $purchaseOrder->supplier?->supplier_name ?? '-' }}" readonly>
                    </div>

                    {{-- Receiving address (branch/warehouse kita) --}}
                    <div class="col-md-6">
                        <label class="mb-1">Receiving Address</label>
                        <textarea class="form-control" name="shipping_address" rows="2"
                                  placeholder="Address where goods will be received (branch/warehouse address)">{{ old('shipping_address', $purchaseOrder->branch?->address ?? ($purchaseOrder->warehouse?->address ?? ($purchaseOrder->supplier?->address ?? ''))) }}</textarea>
                        <div class="pd-mini mt-1">
                            Tip: default diambil dari alamat cabang (tujuan barang masuk).
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="mb-1">Date</label>
                        <input type="date" class="form-control" name="date"
                               value="{{ old('date', now()->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="mb-1">Transaction No.</label>
                        <input type="text" class="form-control" value="[Auto]" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="mb-1">Purchase Order</label>
                        <input type="text" class="form-control" value="{{ $purchaseOrder->reference }}" readonly>
                    </div>
                </div>

                {{-- ✅ Warehouse dropdown DIHAPUS --}}
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="mb-1">Status</label>
                        <input type="text" class="form-control" value="Pending" readonly>
                        <small class="text-muted">Stock will be updated after confirmation.</small>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="mb-1">Ship Via</label>
                        <input type="text" class="form-control" name="ship_via" value="{{ old('ship_via') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1">Tracking No.</label>
                        <input type="text" class="form-control" name="tracking_number" value="{{ old('tracking_number') }}">
                    </div>
                </div>

                {{-- Product Table --}}
                @php
                    $items = $remainingItems ?? collect();
                @endphp

                <div class="d-flex align-items-center justify-content-between mt-4">
                    <h5 class="mb-0">Items to Receive</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-apply-all">
                        <i class="bi bi-check2-circle"></i> Apply All
                    </button>
                </div>

                <div class="table-responsive mt-2">
                    <table class="table table-striped pd-table">
                        <thead>
                        <tr>
                            <th style="min-width:260px;">Product</th>
                            <th style="min-width:220px;">Description</th>
                            <th style="width:220px;">Qty</th>
                            <th style="width:120px;">Unit</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($items as $detail)
                            @php
                                $maxQty = (int) ($detail->delivery_remaining_quantity ?? 0);
                                $allocatedQty = (int) ($detail->allocated_delivery_quantity ?? 0);
                                $productName = $detail->product->product_name ?? $detail->product_name ?? '-';
                                $productCode = $detail->product->product_code ?? $detail->product_code ?? '-';
                            @endphp

                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $productName }}</div>
                                    <span class="badge bg-success">{{ $productCode }}</span>
                                    <div class="pd-mini mt-1">
                                        Ordered: <span class="pd-remaining">{{ (int) ($detail->quantity ?? 0) }}</span>
                                        • Already in PD: <span class="pd-remaining">{{ $allocatedQty }}</span>
                                        • Remaining max: <span class="pd-remaining">{{ $maxQty }}</span>
                                    </div>
                                </td>

                                <td>
                                    <input type="text" class="form-control"
                                           name="description[{{ $detail->id }}]"
                                           value="{{ old('description.'.$detail->id) }}"
                                           placeholder="Optional note per item">
                                </td>

                                <td>
                                    <div class="pd-qty-wrap">
                                        <input type="number"
                                               class="form-control qty-input"
                                               name="quantity[{{ $detail->id }}]"
                                               value="{{ old('quantity.'.$detail->id, $maxQty) }}"
                                               min="0"
                                               max="{{ $maxQty }}"
                                               data-product-id="{{ $detail->id }}"
                                               data-max="{{ $maxQty }}">
                                        <div class="pd-mini">
                                            Remaining:
                                            <span class="remaining-qty" id="remaining-qty-{{ $detail->id }}">
                                                {{ max(0, $maxQty - (int) old('quantity.'.$detail->id, $maxQty)) }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <input type="text" class="form-control" value="{{ $detail->unit ?? 'Unit' }}" readonly>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No remaining quantities available for a new Purchase Delivery.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="mb-1">Note</label>
                        <textarea class="form-control" name="note" rows="3">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 text-right">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-truck"></i> Create Purchase Delivery
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('page_scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    const qtyInputs = document.querySelectorAll(".qty-input");

    function updateRemaining(input) {
        const productId = input.dataset.productId;
        const maxQty = parseInt(input.dataset.max || "0", 10);
        let enteredQty = parseInt(input.value || "0", 10);

        if (isNaN(enteredQty)) enteredQty = 0;
        if (enteredQty < 0) enteredQty = 0;
        if (enteredQty > maxQty) enteredQty = maxQty;

        input.value = enteredQty;

        const remainingQty = Math.max(0, maxQty - enteredQty);
        const el = document.getElementById("remaining-qty-" + productId);
        if (el) el.innerText = remainingQty;
    }

    qtyInputs.forEach((input) => {
        input.addEventListener("input", () => updateRemaining(input));
        updateRemaining(input);
    });

    const applyAll = document.getElementById("btn-apply-all");
    if (applyAll) {
        applyAll.addEventListener("click", () => {
            document.querySelectorAll(".qty-input").forEach((input) => updateRemaining(input));

            document.querySelectorAll("tbody tr").forEach((row) => {
                row.classList.add("table-success");
                setTimeout(() => row.classList.remove("table-success"), 600);
            });
        });
    }
});
</script>
@endpush

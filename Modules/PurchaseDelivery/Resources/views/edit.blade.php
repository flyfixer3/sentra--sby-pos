@extends('layouts.app')

@section('title', 'Edit Purchase Delivery')

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
    .pd-mini{font-size:12px;color:#6c757d;}
    .pd-remaining{font-weight:600;}
</style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-deliveries.index') }}">Purchase Deliveries</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-deliveries.show', $purchaseDelivery->id) }}">Details</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <form action="{{ route('purchase-deliveries.update', $purchaseDelivery->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="pd-header">
                <div>
                    <h3 class="pd-title">Edit Purchase Delivery</h3>
                    <p class="pd-sub mb-0">
                        PD: <span class="pd-badge"><i class="bi bi-truck"></i> #{{ $purchaseDelivery->id }}</span>
                        @if($purchaseDelivery->purchaseOrder)
                            <span class="pd-badge"><i class="bi bi-receipt"></i> PO: {{ $purchaseDelivery->purchaseOrder->reference }}</span>
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <span class="pd-badge">
                        <i class="bi bi-building"></i>
                        {{ $purchaseDelivery->branch?->name ?? 'Branch: -' }}
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
                               value="{{ $purchaseDelivery->purchaseOrder?->supplier?->supplier_name ?? '-' }}" readonly>
                    </div>

                    {{-- Receiving address: kalau kamu simpan di PD, pakai itu. Kalau tidak, fallback branch address --}}
                    <div class="col-md-6">
                        <label class="mb-1">Receiving Address</label>
                        <textarea class="form-control"
                                  name="shipping_address"
                                  rows="2"
                                  placeholder="Address where goods will be received (branch/warehouse address)">{{ old('shipping_address', $purchaseDelivery->branch?->address ?? ($purchaseDelivery->warehouse?->address ?? ($purchaseDelivery->purchaseOrder?->supplier?->address ?? ''))) }}</textarea>
                        <div class="pd-mini mt-1">
                            Tip: default diambil dari alamat cabang (tujuan barang masuk).
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="mb-1">Date</label>
                        <input type="date" class="form-control" name="date"
                               value="{{ old('date', \Illuminate\Support\Carbon::parse($purchaseDelivery->date)->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="mb-1">Transaction No.</label>
                        <input type="text" class="form-control" value="[Auto]" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="mb-1">Purchase Order</label>
                        <input type="text" class="form-control" value="{{ $purchaseDelivery->purchaseOrder?->reference ?? '-' }}" readonly>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="mb-1">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-control" required>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}"
                                    {{ (int) old('warehouse_id', $purchaseDelivery->warehouse_id ?? 0) === (int) $wh->id ? 'selected' : '' }}>
                                    {{ $wh->warehouse_name }} {{ $wh->is_main ? '(Main)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Goods will be received into this warehouse.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="mb-1">Status</label>
                        <input type="text" class="form-control" value="{{ $purchaseDelivery->status ?? 'Pending' }}" readonly>
                        <small class="text-muted">Only pending Purchase Delivery can be edited.</small>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="mb-1">Ship Via</label>
                        <input type="text" class="form-control" name="ship_via"
                               value="{{ old('ship_via', $purchaseDelivery->ship_via) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1">Tracking No.</label>
                        <input type="text" class="form-control" name="tracking_number"
                               value="{{ old('tracking_number', $purchaseDelivery->tracking_number) }}">
                    </div>
                </div>

                {{-- Items Table (READONLY) --}}
                <div class="d-flex align-items-center justify-content-between mt-4">
                    <h5 class="mb-0">Items in this Delivery</h5>
                    <div class="pd-mini">
                        Note: qty items tidak diubah di edit ini (edit hanya header + note). Qty diterima diatur saat Confirm.
                    </div>
                </div>

                <div class="table-responsive mt-2">
                    <table class="table table-striped pd-table">
                        <thead>
                            <tr>
                                <th style="min-width:260px;">Product</th>
                                <th style="min-width:220px;">Description</th>
                                <th style="width:180px;">Qty Expected</th>
                                <th style="width:120px;">Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($purchaseDelivery->purchaseDeliveryDetails as $d)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $d->product_name ?? '-' }}</div>
                                        <span class="badge bg-success">{{ $d->product_code ?? '-' }}</span>
                                    </td>

                                    <td>
                                        <input type="text" class="form-control"
                                               value="{{ $d->description ?? '' }}"
                                               readonly
                                               placeholder="(per item note on create)">

                                    </td>

                                    <td>
                                        <input type="number" class="form-control"
                                               value="{{ (int) ($d->quantity ?? 0) }}"
                                               readonly>
                                    </td>

                                    <td>
                                        <input type="text" class="form-control"
                                               value="{{ $d->unit ?? 'Unit' }}"
                                               readonly>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No items found in this Purchase Delivery.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Note + meta --}}
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="mb-1">Note (Create/Edit PD)</label>
                        <textarea class="form-control" name="note" rows="3" maxlength="1000">{{ old('note', $purchaseDelivery->note) }}</textarea>

                        @if(!empty($purchaseDelivery->note_updated_at))
                            <div class="pd-mini mt-2">
                                Last updated:
                                <strong>{{ \Illuminate\Support\Carbon::parse($purchaseDelivery->note_updated_at)->format('d M Y H:i') }}</strong>
                                · Role: <strong>{{ $purchaseDelivery->note_updated_role ?? '-' }}</strong>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-4 text-right">
                    <a href="{{ route('purchase-deliveries.show', $purchaseDelivery->id) }}" class="btn btn-light">
                        Cancel
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

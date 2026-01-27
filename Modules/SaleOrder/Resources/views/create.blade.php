@extends('layouts.app')

@section('title', 'Create Sale Order')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.index') }}">Sale Orders</a></li>
        <li class="breadcrumb-item active">Create</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">

            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center">
                    <div>
                        <strong>Create Sale Order</strong>
                        @if(!empty($prefillRefText))
                            <div class="text-muted small">{{ $prefillRefText }}</div>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    <form action="{{ route('sale-orders.store') }}" method="POST">
                        @csrf

                        <input type="hidden" name="source" value="{{ $source }}">

                        @if($source === 'quotation')
                            <input type="hidden" name="quotation_id" value="{{ request('quotation_id') }}">
                        @endif

                        @if($source === 'sale')
                            <input type="hidden" name="sale_id" value="{{ request('sale_id') }}">
                        @endif

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="{{ old('date', $prefillDate) }}" required>
                            </div>

                            <div class="col-md-5 mb-3">
                                <label class="form-label">Customer</label>
                                <select name="customer_id" class="form-control" required>
                                    <option value="">-- Choose --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}"
                                            {{ (int) old('customer_id', $prefillCustomerId) === (int) $c->id ? 'selected' : '' }}>
                                            {{ $c->customer_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Default Warehouse (optional)</label>
                                <select name="warehouse_id" class="form-control">
                                    <option value="">-- None --</option>
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w->id }}"
                                            {{ (int) old('warehouse_id', $prefillWarehouseId) === (int) $w->id ? 'selected' : '' }}>
                                            {{ $w->warehouse_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="small text-muted mt-1">
                                    Warehouse ini hanya anchor default. Sale Delivery tetap bisa pilih warehouse saat dibuat.
                                </div>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Note</label>
                                <textarea name="note" class="form-control" rows="2">{{ old('note', $prefillNote) }}</textarea>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex align-items-center justify-content-between">
                            <div><strong>Items</strong></div>
                            <div class="text-muted small">Kamu bisa edit qty & price sebelum save</div>
                        </div>

                        <div class="table-responsive mt-2">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th style="width: 45%;">Product ID</th>
                                        <th style="width: 20%;">Qty</th>
                                        <th style="width: 25%;">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @php
                                    $items = old('items', $prefillItems);
                                    if (!is_array($items) || count($items) === 0) {
                                        $items = [['product_id'=>'','quantity'=>1,'price'=>0]];
                                    }
                                @endphp

                                @foreach($items as $i => $row)
                                    <tr>
                                        <td>
                                            <input type="number" name="items[{{ $i }}][product_id]" class="form-control"
                                                   value="{{ $row['product_id'] ?? '' }}" required>
                                            <div class="small text-muted mt-1">
                                                (Saat ini input product_id manual dulu agar cepat. Kalau kamu mau dropdown product, kita bisa upgrade next step.)
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="items[{{ $i }}][quantity]" class="form-control"
                                                   value="{{ $row['quantity'] ?? 1 }}" min="1" required>
                                        </td>
                                        <td>
                                            <input type="number" name="items[{{ $i }}][price]" class="form-control"
                                                   value="{{ $row['price'] ?? 0 }}" min="0">
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-save"></i> Save Sale Order
                            </button>
                            <a href="{{ route('sale-orders.index') }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

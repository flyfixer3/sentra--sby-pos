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
@php
    $items = old('items', $prefillItems ?? []);
    if (!is_array($items) || count($items) === 0) {
        $items = [];
    }
@endphp

<div class="container-fluid mb-4">
    {{-- ✅ search bar (komponen yang sama seperti Transfer) --}}
    <div class="row">
        <div class="col-12">
            <livewire:search-product />
        </div>
    </div>

    <div class="row mt-4">
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
                    @include('utils.alerts')

                    <form action="{{ route('sale-orders.store') }}" method="POST" id="soForm">
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
                                <input type="date" name="date" class="form-control"
                                       value="{{ old('date', $prefillDate) }}" required>
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

                        {{-- ✅ Items table: Livewire (selaras dengan Transfer style) --}}
                        <div class="mt-2">
                            <livewire:sale-order.product-table :prefillItems="$items" />
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">

                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-save"></i> Save Sale Order
                            </button>

                            <a href="{{ route('sale-orders.index') }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>

                    <div class="small text-muted mt-3">
                        Tips: cari produk lewat search bar di atas, lalu klik hasilnya untuk menambahkan ke tabel item.
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', "Edit Sale Order #{$saleOrder->reference}")

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.index') }}">Sale Orders</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.show', $saleOrder->id) }}">Details</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
@php
    $items = old('items', $items ?? []);
    if (!is_array($items)) $items = [];
@endphp

<div class="container-fluid mb-4">
    {{-- ✅ search bar (komponen yang sama seperti Transfer / Create) --}}
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
                        <strong>Edit Sale Order</strong>
                        <div class="text-muted small">
                            Reference: <span class="fw-bold">{{ $saleOrder->reference }}</span>
                            • Status: <span class="badge bg-warning text-dark">{{ ucfirst($saleOrder->status) }}</span>
                        </div>
                    </div>

                    <div class="mfs-auto d-flex gap-2">
                        <a href="{{ route('sale-orders.show', $saleOrder->id) }}" class="btn btn-sm btn-light">
                            Back
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    @include('utils.alerts')

                    <form action="{{ route('sale-orders.update', $saleOrder->id) }}" method="POST" id="soEditForm">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control"
                                       value="{{ old('date', (string) $saleOrder->getRawOriginal('date')) }}" required>
                            </div>

                            <div class="col-md-5 mb-3">
                                <label class="form-label">Customer</label>
                                <select name="customer_id" class="form-control" required>
                                    <option value="">-- Choose --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}"
                                            {{ (int) old('customer_id', $saleOrder->customer_id) === (int) $c->id ? 'selected' : '' }}>
                                            {{ $c->customer_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Note</label>
                                <textarea name="note" class="form-control" rows="2">{{ old('note', $saleOrder->note) }}</textarea>
                                <div class="small text-muted mt-1">
                                    Warehouse tidak diatur di Sale Order (dipilih saat membuat Sale Delivery).
                                </div>
                            </div>
                        </div>

                        <hr>

                        {{-- ✅ Items table: Livewire (selaras dengan Create) --}}
                        <div class="mt-2">
                            <livewire:sale-order.product-table :prefillItems="$items" />
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-save"></i> Save Changes
                            </button>

                            <a href="{{ route('sale-orders.show', $saleOrder->id) }}" class="btn btn-secondary">
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

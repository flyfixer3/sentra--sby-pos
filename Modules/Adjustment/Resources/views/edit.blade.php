@extends('layouts.app')

@section('title', 'Edit Pending Adjustment')

@push('page_css')
    @livewireStyles
    <style>
        .sa-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.06);}
        .sa-title{margin:0;font-weight:700;font-size:16px;}
        .sa-sub{margin:2px 0 0;font-size:12px;color:#6c757d;}
        .sa-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:12px;background:rgba(255,193,7,.14);color:#7a5b00;border:1px solid rgba(255,193,7,.35);white-space:nowrap;}
        .sa-help{font-size:12px;color:#6c757d;}
        .sa-card{box-shadow:0 4px 16px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);}
        .sa-form-label{font-weight:600;font-size:13px;}
    </style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('adjustments.index') }}">Adjustments</a></li>
        <li class="breadcrumb-item"><a href="{{ route('adjustments.show', $adjustment) }}">{{ $adjustment->reference }}</a></li>
        <li class="breadcrumb-item active">Edit Pending</li>
    </ol>
@endsection

@section('content')
@php
    $requestType = (string) ($adjustment->request_type ?? 'stock_add');
    $rawDate = $adjustment->getAttributes()['date'] ?? now()->format('Y-m-d');
    $requestNote = data_get($adjustment->payload, 'note', data_get($adjustment->payload, 'user_note', ''));
    $qualityType = match ($requestType) {
        'quality_good_to_damaged' => 'damaged',
        'quality_defect_to_good' => 'defect_to_good',
        'quality_damaged_to_good' => 'damaged_to_good',
        default => 'defect',
    };
    $issueCondition = $qualityType === 'damaged_to_good' ? 'damaged' : 'defect';
@endphp

<div class="container-fluid mb-4">
    <div class="row">
        <div class="col-12">
            <livewire:search-product/>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card sa-card">
                <div class="sa-header">
                    <div>
                        <h5 class="sa-title">Edit Pending Adjustment Request</h5>
                        <p class="sa-sub">Only pending request data is updated here. Stock, mutations, and posted adjustment rows are untouched until approval.</p>
                    </div>
                    <span class="sa-badge">
                        <i class="bi bi-hourglass-split"></i>
                        PENDING
                    </span>
                </div>

                <div class="card-body">
                    @include('utils.alerts')

                    <div class="alert alert-warning border">
                        Approved and rejected adjustments are locked. This page is only for editing a request before approval.
                    </div>

                    @if($requestType === 'stock_add')
                        <form action="{{ route('adjustments.update', $adjustment) }}" method="POST" enctype="multipart/form-data" id="adjustmentForm">
                            @csrf
                            @method('patch')
                            <input type="hidden" name="adjustment_type" value="add">

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label class="sa-form-label">Reference</label>
                                        <input type="text" class="form-control" value="{{ $adjustment->reference }}" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label class="sa-form-label">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ old('date', $rawDate) }}">
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label class="sa-form-label">Warehouse <span class="text-danger">*</span></label>
                                        <select name="warehouse_id" id="warehouse_id_stock" class="form-control" required>
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}" {{ (int) old('warehouse_id', $defaultWarehouseId) === (int) $wh->id ? 'selected' : '' }}>
                                                    {{ $wh->warehouse_name }}{{ (int)$wh->is_main === 1 ? ' (Main)' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <livewire:adjustment.product-table-stock :adjustedProducts="$pendingItems" mode="stock_add" :warehouseId="$defaultWarehouseId"/>

                            <div class="form-group mt-3">
                                <label class="sa-form-label">Note</label>
                                <textarea name="note" rows="3" class="form-control">{{ old('note', $requestNote) }}</textarea>
                            </div>

                            <div class="d-flex justify-content-end">
                                <a href="{{ route('adjustments.show', $adjustment) }}" class="btn btn-light border mr-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Pending Request</button>
                            </div>
                        </form>
                    @elseif($requestType === 'stock_sub')
                        <form action="{{ route('adjustments.update', $adjustment) }}" method="POST" id="adjustmentForm">
                            @csrf
                            @method('patch')
                            <input type="hidden" name="adjustment_type" value="sub">

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label class="sa-form-label">Reference</label>
                                        <input type="text" class="form-control" value="{{ $adjustment->reference }}" readonly>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label class="sa-form-label">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ old('date', $rawDate) }}">
                                    </div>
                                </div>
                            </div>

                            <livewire:adjustment.product-table-stock-sub :pendingItems="$pendingItems" />

                            <div class="form-group mt-3">
                                <label class="sa-form-label">Note</label>
                                <textarea name="note" rows="3" class="form-control">{{ old('note', $requestNote) }}</textarea>
                            </div>

                            <div class="d-flex justify-content-end">
                                <a href="{{ route('adjustments.show', $adjustment) }}" class="btn btn-light border mr-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Pending Request</button>
                            </div>
                        </form>
                    @elseif(in_array($requestType, ['quality_good_to_defect', 'quality_good_to_damaged'], true))
                        <form action="{{ route('adjustments.update', $adjustment) }}" method="POST" enctype="multipart/form-data" id="qualityForm">
                            @csrf
                            @method('patch')
                            <input type="hidden" name="type" value="{{ $qualityType }}">

                            <div class="form-row">
                                <div class="col-lg-3">
                                    <label class="sa-form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="date" class="form-control" value="{{ old('date', $rawDate) }}" required>
                                </div>
                                <div class="col-lg-5">
                                    <label class="sa-form-label">Warehouse <span class="text-danger">*</span></label>
                                    <select name="warehouse_id" id="qrcWarehouse" class="form-control" required>
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh->id }}" {{ (int) old('warehouse_id', $defaultWarehouseId) === (int) $wh->id ? 'selected' : '' }}>
                                                {{ $wh->warehouse_name }}{{ (int)$wh->is_main === 1 ? ' (Main)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-4">
                                    <label class="sa-form-label">Flow</label>
                                    <input type="text" class="form-control" value="{{ strtoupper(str_replace('_', ' ', $requestType)) }}" readonly>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <label class="sa-form-label">User Note</label>
                                <textarea name="user_note" class="form-control" rows="2">{{ old('user_note', $requestNote) }}</textarea>
                            </div>

                            <livewire:adjustment.product-table mode="quality" :warehouseId="$defaultWarehouseId" :adjustedProducts="$pendingItems" />

                            <div class="d-flex justify-content-end mt-3">
                                <a href="{{ route('adjustments.show', $adjustment) }}" class="btn btn-light border mr-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Pending Request</button>
                            </div>
                        </form>
                    @else
                        <form action="{{ route('adjustments.update', $adjustment) }}" method="POST" id="qualityForm">
                            @csrf
                            @method('patch')
                            <input type="hidden" name="type" value="{{ $qualityType }}">

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <label class="sa-form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" name="date" class="form-control" value="{{ old('date', $rawDate) }}" required>
                                </div>
                                <div class="col-lg-4">
                                    <label class="sa-form-label">Flow</label>
                                    <input type="text" class="form-control" value="{{ strtoupper(str_replace('_', ' ', $requestType)) }}" readonly>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <label class="sa-form-label">User Note <span class="text-danger">*</span></label>
                                <textarea name="user_note" class="form-control" rows="2" required>{{ old('user_note', $requestNote) }}</textarea>
                            </div>

                            <livewire:adjustment.product-table-quality-to-good :pendingItems="$pendingItems" :condition="$issueCondition" />

                            <div class="d-flex justify-content-end mt-3">
                                <a href="{{ route('adjustments.show', $adjustment) }}" class="btn btn-light border mr-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Pending Request</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@include('includes.defect-type-picker-assets')

@push('page_scripts')
    @livewireScripts
    <script>
        document.addEventListener('livewire:load', function () {
            const stockWarehouse = document.getElementById('warehouse_id_stock');
            if (stockWarehouse && window.Livewire) {
                const emitStockWarehouse = function () {
                    const wid = parseInt(stockWarehouse.value || 0, 10);
                    window.Livewire.emit('stockWarehouseChanged', wid > 0 ? wid : null);
                };
                stockWarehouse.addEventListener('change', emitStockWarehouse);
                emitStockWarehouse();
            }

            const qualityWarehouse = document.getElementById('qrcWarehouse');
            if (qualityWarehouse && window.Livewire) {
                const emitQualityWarehouse = function () {
                    const wid = parseInt(qualityWarehouse.value || 0, 10);
                    window.Livewire.emit('qualityWarehouseChanged', wid > 0 ? wid : null);
                };
                qualityWarehouse.addEventListener('change', emitQualityWarehouse);
                emitQualityWarehouse();
            }

            if (window.Livewire) {
                window.Livewire.emit('qualityTypeChanged', @json($qualityType));
                window.Livewire.emit('qualityToGoodTypeChanged', @json($qualityType));
            }
        });
    </script>
@endpush

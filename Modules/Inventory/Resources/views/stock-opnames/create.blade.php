@extends('layouts.app')

@section('title', 'Buat Draft Stock Opname')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('inventory.stock-opnames.index') }}">Stock Opname</a></li>
        <li class="breadcrumb-item active">Buat Draft</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card card-modern">
        <div class="card-body">
            <div class="page-title mb-1">Buat Draft Stock Opname Kaca</div>
            <div class="text-muted small mb-4">Draft akan menarik stok kaca cabang aktif. Template hasilnya bisa diisi tim lalu diimport balik.</div>

            <form action="{{ route('inventory.stock-opnames.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Tanggal Opname</label>
                            <input type="date" name="opname_date" class="form-control" value="{{ old('opname_date', now()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Gudang Adjustment</label>
                            <select name="warehouse_id" class="form-control" required>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ (string) old('warehouse_id', $defaultWarehouseId) === (string) $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->warehouse_name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Gudang ini dipakai saat finalize selisih opname menjadi adjustment.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Judul Draft</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="Opsional">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="note" rows="3" class="form-control" placeholder="Opsional">{{ old('note') }}</textarea>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="include_zero_stock" name="include_zero_stock" value="1" {{ old('include_zero_stock') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="include_zero_stock">
                            Sertakan semua master kaca, termasuk yang stok sistemnya 0
                        </label>
                    </div>
                    <small class="text-muted">Default lebih ringan: hanya item kaca dengan stok sistem lebih dari 0.</small>
                </div>

                <div class="d-flex" style="gap:10px;">
                    <a href="{{ route('inventory.stock-opnames.index') }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary btn-modern">Generate Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page_css')
<style>
    .card-modern{border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 22px rgba(15,23,42,.06)}
    .page-title{font-weight:800;font-size:18px;color:#0f172a;line-height:1.2}
    .btn-modern{border-radius:999px;padding:8px 14px;font-weight:700;box-shadow:0 6px 14px rgba(2,6,23,.12)}
</style>
@endpush

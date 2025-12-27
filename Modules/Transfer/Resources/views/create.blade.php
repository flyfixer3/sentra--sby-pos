@extends('layouts.app')

@section('title', 'Create Transfer')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">Transfers</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <livewire:search-product />
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card card-modern">
                    <div class="card-body">
                        @include('utils.alerts')

                        <div class="alert alert-light border mb-3">
                            <div class="d-flex align-items-start">
                                <div>
                                    <div class="font-weight-bold mb-1">Create Transfer</div>
                                    <div class="text-muted">
                                        Pilih <b>From Warehouse</b> dulu supaya sistem bisa ambil stok per produk dari gudang tersebut.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form action="{{ route('transfers.store') }}" method="POST">
                            @csrf

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text"
                                            class="form-control form-control-modern"
                                            name="reference"
                                            value="{{ $reference }}"
                                            readonly
                                            required>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="date">Date <span class="text-danger">*</span></label>
                                        <input
                                            type="date"
                                            class="form-control form-control-modern"
                                            name="date"
                                            required
                                            value="{{ old('date', now()->format('Y-m-d')) }}">
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="note">Note</label>
                                        <input type="text" class="form-control form-control-modern" name="note" value="{{ old('note') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="from_warehouse_id">From Warehouse <span class="text-danger">*</span></label>
                                        <select
                                            class="form-control form-control-modern"
                                            name="from_warehouse_id"
                                            id="from_warehouse_id"
                                            required
                                        >
                                            <option value="">Select Source Warehouse</option>
                                            @foreach ($warehouses as $wh)
                                                <option value="{{ $wh->id }}" {{ (string)old('from_warehouse_id') === (string)$wh->id ? 'selected' : '' }}>
                                                    {{ $wh->warehouse_name }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @error('from_warehouse_id')
                                            <div class="text-danger mt-1">{{ $message }}</div>
                                        @enderror

                                        <div class="small text-muted mt-1">
                                            <span class="badge badge-pill badge-light border">Tip</span>
                                            Pilih gudang dulu agar stok “Stock (From WH)” muncul di tabel produk.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="to_branch_id">To Branch <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-modern" name="to_branch_id" required>
                                            <option value="" disabled {{ old('to_branch_id') ? '' : 'selected' }}>Select Destination Branch</option>
                                            @foreach (\Modules\Branch\Entities\Branch::where('id', '!=', session('active_branch'))->get() as $branch)
                                                <option value="{{ $branch->id }}" {{ (string)old('to_branch_id') === (string)$branch->id ? 'selected' : '' }}>
                                                    {{ $branch->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('to_branch_id')
                                            <div class="text-danger mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <livewire:transfer.product-table />
                            </div>

                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary btn-modern">
                                    Submit Transfer <i class="bi bi-check"></i>
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_css')
<style>
    .card-modern {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    }

    .form-control-modern {
        border-radius: 10px;
        border-color: #e2e8f0;
    }
    .form-control-modern:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 0.2rem rgba(147,197,253,0.25);
    }

    .btn-modern {
        border-radius: 999px;
        padding: 10px 16px;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(2, 6, 23, 0.12);
    }

    /* biar table livewire (produk) look lebih rapi */
    .table thead th {
        background: #f8fafc;
        color: #334155;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
    }
</style>
@endpush

@push('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.Livewire?.emit) {
        window.Livewire.emit('enableWarehouseRequirement');
    }
});
(function () {
    /**
     * Support Livewire v2 & v3
     * - v2: Livewire.emit('event', payload)
     * - v3: Livewire.dispatch('event', payload)
     */
    function emitToLivewire(eventName, payload) {
        if (!window.Livewire) return false;

        // Livewire v2
        if (typeof window.Livewire.emit === 'function') {
            window.Livewire.emit(eventName, payload);
            return true;
        }

        // Livewire v3
        if (typeof window.Livewire.dispatch === 'function') {
            window.Livewire.dispatch(eventName, payload);
            return true;
        }

        return false;
    }

    function getWarehouseValue() {
        var el = document.getElementById('from_warehouse_id');
        if (!el) return null;
        return el.value ? el.value : null;
    }

    function emitWarehouseToComponents() {
        var val = getWarehouseValue();

        // IMPORTANT:
        // Kalau komponen kamu listen event: 'fromWarehouseSelected'
        // maka ini harus sama persis.
        // payload kita kirim dalam 2 bentuk agar kompatibel:
        // - v2 biasa: emit(event, val)
        // - v3 / beberapa pattern: emit(event, { warehouseId: val })
        var ok1 = emitToLivewire('fromWarehouseSelected', val);
        var ok2 = emitToLivewire('fromWarehouseSelected', { warehouseId: val });

        return (ok1 || ok2);
    }

    function bindWarehouseChange() {
        var el = document.getElementById('from_warehouse_id');
        if (!el) return;

        el.addEventListener('change', function () {
            emitWarehouseToComponents();
        });
    }

    function boot() {
        bindWarehouseChange();

        // Emit awal beberapa kali untuk mengejar timing Livewire mount
        var tries = 0;
        var timer = setInterval(function () {
            tries++;
            var ok = emitWarehouseToComponents();

            // Stop cepat begitu Livewire sudah siap / sudah emit
            if (ok || tries >= 12) {
                clearInterval(timer);
            }
        }, 180);
    }

    document.addEventListener('DOMContentLoaded', boot);

    // Kalau project kamu pakai livewire navigate / turbo (kadang DOMContentLoaded tidak terpanggil ulang)
    document.addEventListener('livewire:navigated', boot);
})();
</script>
@endpush

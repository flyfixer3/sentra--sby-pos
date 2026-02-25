@extends('layouts.app')

@section('title', 'Create Rack Movement')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('inventory.rack-movements.index') }}">Rack Movements</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid mb-4">
    <div class="row">
        <div class="col-12">
            {{-- Reuse global search component (emit: productSelected) --}}
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
                                <div class="font-weight-bold mb-1">Create Rack Movement</div>
                                <div class="text-muted">
                                    Flow: pilih <b>From Warehouse</b> → pilih <b>From Rack</b> → pilih <b>To Warehouse</b> → pilih <b>To Rack</b> → search & add products.
                                </div>
                                <div class="small text-muted mt-1">
                                    Catatan: ini <b>internal move</b> (cabang yang sama). Semua pergerakan stock dicatat lewat <b>Mutations</b>.
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('inventory.rack-movements.store') }}" method="POST">
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
                                    <label>From Warehouse <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-modern" name="from_warehouse_id" id="from_warehouse_id" required>
                                        <option value="">Select Source Warehouse</option>
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh->id }}" {{ (string)old('from_warehouse_id') === (string)$wh->id ? 'selected' : '' }}>
                                                {{ $wh->warehouse_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('from_warehouse_id')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>From Rack <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-modern" name="from_rack_id" id="from_rack_id" required disabled>
                                        <option value="">Select Source Rack</option>
                                    </select>
                                    @error('from_rack_id')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                    <div class="small text-muted mt-1">
                                        Pilih <b>From Rack</b> untuk menentukan stock maksimum yang bisa dipindah.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>To Warehouse <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-modern" name="to_warehouse_id" id="to_warehouse_id" required>
                                        <option value="">Select Destination Warehouse</option>
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh->id }}" {{ (string)old('to_warehouse_id') === (string)$wh->id ? 'selected' : '' }}>
                                                {{ $wh->warehouse_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('to_warehouse_id')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>To Rack <span class="text-danger">*</span></label>
                                    <select class="form-control form-control-modern" name="to_rack_id" id="to_rack_id" required disabled>
                                        <option value="">Select Destination Rack</option>
                                    </select>
                                    @error('to_rack_id')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <livewire:inventory.rack-move-product-table />
                        </div>

                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-modern">
                                Submit Rack Movement <i class="bi bi-check"></i>
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
</style>
@endpush

@push('page_scripts')
<script>
    (function () {
        function emitToLivewire(eventName, payload) {
            if (!window.Livewire) return false;
            if (typeof window.Livewire.emit === 'function') {
                window.Livewire.emit(eventName, payload);
                return true;
            }
            if (typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch(eventName, payload);
                return true;
            }
            return false;
        }

        async function fetchRacks(warehouseId) {
            const url = new URL("{{ route('inventory.rack-movements.racks.by-warehouse') }}", window.location.origin);
            url.searchParams.set('warehouse_id', warehouseId);

            const res = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const json = await res.json();
            if (!json || json.success !== true) {
                throw new Error(json?.message || 'Failed to load racks');
            }
            return json.racks || [];
        }

        function fillSelect(selectEl, options, placeholder) {
            selectEl.innerHTML = '';
            const opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = placeholder || 'Select';
            selectEl.appendChild(opt0);

            options.forEach(function (o) {
                const opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = o.label;
                selectEl.appendChild(opt);
            });
        }

        function boot() {
            const fromWarehouse = document.getElementById('from_warehouse_id');
            const toWarehouse = document.getElementById('to_warehouse_id');
            const fromRack = document.getElementById('from_rack_id');
            const toRack = document.getElementById('to_rack_id');

            if (!fromWarehouse || !toWarehouse || !fromRack || !toRack) return;

            fromWarehouse.addEventListener('change', async function () {
                const wid = fromWarehouse.value;
                fromRack.disabled = true;
                fillSelect(fromRack, [], 'Select Source Rack');

                // emit warehouse ke Livewire
                emitToLivewire('rackMoveFromWarehouseSelected', { value: wid });

                if (!wid) {
                    emitToLivewire('rackMoveFromRackSelected', { value: null });
                    return;
                }

                try {
                    const racks = await fetchRacks(wid);
                    fillSelect(fromRack, racks, 'Select Source Rack');
                    fromRack.disabled = false;
                } catch (e) {
                    console.error(e);
                    alert('Failed to load racks for From Warehouse');
                }
            });

            fromRack.addEventListener('change', function () {
                emitToLivewire('rackMoveFromRackSelected', { value: fromRack.value });
            });

            toWarehouse.addEventListener('change', async function () {
                const wid = toWarehouse.value;
                toRack.disabled = true;
                fillSelect(toRack, [], 'Select Destination Rack');

                if (!wid) return;

                try {
                    const racks = await fetchRacks(wid);
                    fillSelect(toRack, racks, 'Select Destination Rack');
                    toRack.disabled = false;
                } catch (e) {
                    console.error(e);
                    alert('Failed to load racks for To Warehouse');
                }
            });

            // ======== initial load (old input) ========
            if (fromWarehouse.value) {
                fromWarehouse.dispatchEvent(new Event('change'));
            }
            if (toWarehouse.value) {
                toWarehouse.dispatchEvent(new Event('change'));
            }

            // after racks loaded, try restore old selected rack
            const oldFromRack = "{{ old('from_rack_id') }}";
            const oldToRack = "{{ old('to_rack_id') }}";

            const tryRestore = setInterval(function () {
                // from rack
                if (oldFromRack && fromRack.options.length > 1 && !fromRack.value) {
                    fromRack.value = oldFromRack;
                    emitToLivewire('rackMoveFromRackSelected', { value: fromRack.value });
                }

                // to rack
                if (oldToRack && toRack.options.length > 1 && !toRack.value) {
                    toRack.value = oldToRack;
                }

                if ((oldFromRack ? fromRack.value === oldFromRack : true) && (oldToRack ? toRack.value === oldToRack : true)) {
                    clearInterval(tryRestore);
                }
            }, 250);

            // safety stop
            setTimeout(function () { clearInterval(tryRestore); }, 5000);
        }

        document.addEventListener('DOMContentLoaded', boot);
    })();
</script>
@endpush
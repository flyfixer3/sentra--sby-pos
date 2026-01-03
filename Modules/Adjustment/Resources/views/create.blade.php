@extends('layouts.app')

@section('title', 'Create Adjustment')

@push('page_css')
    @livewireStyles
    <style>
        .sa-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid rgba(0,0,0,.06);}
        .sa-title{margin:0;font-weight:700;font-size:16px;}
        .sa-sub{margin:2px 0 0;font-size:12px;color:#6c757d;}
        .sa-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:12px;background:rgba(13,110,253,.08);color:#0d6efd;border:1px solid rgba(13,110,253,.18);white-space:nowrap;}
        .sa-help{font-size:12px;color:#6c757d;}
        .sa-divider{height:1px;background:rgba(0,0,0,.06);margin:14px 0;}
        .nav-pills .nav-link{border-radius:10px;}
        .nav-pills .nav-link.active{font-weight:600;}
        .sa-card{box-shadow:0 4px 16px rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.06);}
        .sa-form-label{font-weight:600;font-size:13px;}
        .photo-input{border:1px dashed #cbd5e1;padding:6px;border-radius:10px;background:#fff;width:100%;font-size:12px;}
        .unit-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:12px;overflow:hidden;}
        .unit-card .unit-head{padding:10px 12px;background:#f8fafc;border-bottom:1px solid rgba(0,0,0,.06);font-weight:700;}
        .unit-card .unit-body{padding:12px;}
        .unit-table thead th{background:#f8fafc;font-weight:700;border-bottom:1px solid rgba(0,0,0,.06);}
        .sa-mini-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;background:#f1f5f9;border:1px solid rgba(0,0,0,.06);}
    </style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('adjustments.index') }}">Adjustments</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid mb-4">

    <div class="row">
        <div class="col-12">
            {{-- Search Product JANGAN DIGANTI --}}
            <livewire:search-product/>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">

            <div class="card sa-card">
                <div class="sa-header">
                    <div>
                        <h5 class="sa-title">Create Adjustment</h5>
                        <p class="sa-sub">Mode 1: Stock add/sub (creates mutation). Mode 2: Quality reclass GOOD → defect/damaged (label only).</p>
                    </div>

                    <span class="sa-badge">
                        <i class="bi bi-diagram-3"></i>
                        Active Branch: {{ $activeBranchId }}
                    </span>
                </div>

                <div class="card-body">
                    @include('utils.alerts')

                    <ul class="nav nav-pills mb-3" id="adjustmentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-stock" data-toggle="pill" data-target="#pane-stock" type="button" role="tab">
                                <i class="bi bi-arrow-left-right"></i> Stock Adjustment
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-quality" data-toggle="pill" data-target="#pane-quality" type="button" role="tab">
                                <i class="bi bi-shield-check"></i> Quality Reclass
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="adjustmentTabsContent">

                        {{-- TAB 1: STOCK --}}
                        <div class="tab-pane fade show active" id="pane-stock" role="tabpanel" aria-labelledby="tab-stock">

                            <div class="alert alert-light border mb-3">
                                <div class="sa-help">
                                    <b>Stock Adjustment</b> akan membuat <b>Mutation In/Out</b> dan update <b>Stock.qty_available</b>.
                                </div>
                            </div>

                            <form action="{{ route('adjustments.store') }}" method="POST" id="adjustmentForm">
                                @csrf

                                <div class="form-row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Reference <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="reference" readonly value="ADJ">
                                            <small class="text-muted">Auto-generate by system after submit.</small>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Warehouse <span class="text-danger">*</span></label>
                                            <select name="warehouse_id" id="warehouse_id_stock" class="form-control" required>
                                                @foreach($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" {{ (int)$defaultWarehouseId === (int)$wh->id ? 'selected' : '' }}>
                                                        {{ $wh->warehouse_name }}{{ (int)$wh->is_main === 1 ? ' (Main)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Showing warehouses for active branch (ID: {{ $activeBranchId }})</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="sa-divider"></div>

                                <livewire:adjustment.product-table mode="stock"/>

                                <div class="sa-divider"></div>

                                <div class="form-group">
                                    <label class="sa-form-label">Note (If Needed)</label>
                                    <textarea name="note" id="note" rows="4" class="form-control" placeholder="Optional note..."></textarea>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        Create Adjustment <i class="bi bi-check"></i>
                                    </button>
                                </div>
                            </form>

                        </div>

                        {{-- TAB 2: QUALITY --}}
                        <div class="tab-pane fade" id="pane-quality" role="tabpanel" aria-labelledby="tab-quality">

                            <div class="alert alert-info border mb-3">
                                <div class="sa-help mb-1"><b>Info:</b> GOOD = TOTAL - defect - damaged (warehouse yang dipilih).</div>
                                <div class="sa-help">Reclass ini <b>label only</b> + create detail per unit + foto opsional.</div>
                            </div>

                            @php
                                $defaultQualityWarehouseId = (int) ($defaultWarehouseId ?: optional($warehouses->first())->id);
                            @endphp

                            <form id="qualityForm" method="POST" action="{{ route('adjustments.quality.store') }}" enctype="multipart/form-data">
                                @csrf

                                <input type="hidden" name="date" value="{{ now()->format('Y-m-d') }}">

                                <div class="form-row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Warehouse <span class="text-danger">*</span></label>

                                            <select id="warehouse_id_quality" class="form-control" required>
                                                @foreach($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" {{ (int)$defaultQualityWarehouseId === (int)$wh->id ? 'selected' : '' }}>
                                                        {{ $wh->warehouse_name }}{{ (int)$wh->is_main === 1 ? ' (Main)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            {{-- dikirim ke backend --}}
                                            <input type="hidden" name="warehouse_id" id="quality_warehouse_id" value="{{ $defaultQualityWarehouseId }}">

                                            <small class="text-muted">
                                                Warehouse untuk Quality tab <b>terpisah</b> dari Stock tab.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Type <span class="text-danger">*</span></label>
                                           <select name="type" id="quality_type" class="form-control" required>
                                                <optgroup label="GOOD → Quality Issue">
                                                    <option value="defect">Defect (GOOD → DEFECT)</option>
                                                    <option value="damaged">Damaged (GOOD → DAMAGED)</option>
                                                </optgroup>
                                                <optgroup label="Quality Issue → GOOD">
                                                    <option value="defect_to_good">Defect → Good (DELETE defect rows)</option>
                                                    <option value="damaged_to_good">Damaged → Good (DELETE damaged rows)</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="sa-form-label">Summary</label>
                                            <div class="d-flex align-items-center gap-2" style="gap:10px;">
                                                <span class="sa-mini-badge">
                                                    <i class="bi bi-box"></i>
                                                    <span id="quality_selected_product_text">No product selected</span>
                                                </span>

                                                <span class="sa-mini-badge">
                                                    <i class="bi bi-hash"></i>
                                                    Qty: <b id="quality_total_qty">0</b>
                                                </span>
                                            </div>
                                            <small class="text-muted">Product & Qty diambil dari tabel list di bawah.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="sa-divider"></div>

                                <livewire:adjustment.product-table mode="quality"/>

                                <input type="hidden" name="product_id" id="quality_product_id" value="">
                                <input type="hidden" name="qty" id="quality_qty" value="0">

                                <div class="sa-divider"></div>

                                <div class="unit-card mt-3">
                                    <div class="unit-head d-flex align-items-center justify-content-between">
                                        <div>Per-Unit Details & Photo (Optional)</div>
                                        <div class="text-muted"><small>Auto-build dari Qty + Type</small></div>
                                    </div>
                                    <div class="unit-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered unit-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width:60px" class="text-center">#</th>
                                                        <th id="unit_col_title">Defect Type / Reason *</th>
                                                        <th>Description (optional)</th>
                                                        <th style="width:220px">Photo (optional)</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="unit_tbody"></tbody>
                                            </table>
                                        </div>
                                        <div class="text-muted mt-2">
                                            <small>
                                                - Defect wajib isi <b>Defect Type</b> (bubble / scratch / distortion).<br>
                                                - Damaged wajib isi <b>Reason</b> (pecah sudut / retak / shipping damage).<br>
                                                - Foto opsional, max 5MB.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        Submit Reclass <i class="bi bi-check"></i>
                                    </button>
                                </div>

                                <div class="mt-2 sa-help">
                                    Note: Remove/sell defect/damaged dilakukan di Inventory → Quality Details.
                                </div>
                            </form>

                        </div>

                    </div>{{-- tab content --}}
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('page_scripts')
<script>
(function(){

    function livewireEmit(eventName, payload){
        try{
            if (window.Livewire && typeof window.Livewire.emit === 'function') {
                window.Livewire.emit(eventName, payload);
            }
        }catch(e){}
    }

    function toastWarn(msg){
        try{
            if (window.toastr && typeof window.toastr.warning === 'function') {
                window.toastr.warning(msg);
                return;
            }
        }catch(e){}
        alert(msg);
    }

    function syncQualityWarehouse(){
        const wq = document.getElementById('warehouse_id_quality');
        const hidden = document.getElementById('quality_warehouse_id');
        if (!wq || !hidden) return;

        const wid = wq.value ? parseInt(wq.value, 10) : null;
        hidden.value = wid ?? '';

        if (window.Livewire && typeof window.Livewire.emit === 'function') {
            window.Livewire.emit('qualityWarehouseChanged', wid);
        }
    }


    function buildUnits(qty, type){
        const tbody = document.getElementById('unit_tbody');
        const title = document.getElementById('unit_col_title');

        if (!tbody || !title) return;

        qty = parseInt(qty || 0, 10);
        if (isNaN(qty) || qty < 0) qty = 0;

        let key = 'defect_type';
        let placeholder = 'bubble / scratch / distortion';
        let colTitle = 'Defect Type *';

        if (type === 'damaged') {
            key = 'reason';
            placeholder = 'pecah sudut kiri saat bongkar peti';
            colTitle = 'Damaged Reason *';
        }

        if (type === 'defect_to_good' || type === 'damaged_to_good') {
            key = 'resolution_reason';
            placeholder = 'rework OK / polish / claim accepted';
            colTitle = 'Resolution Reason *';
        }

        title.textContent = colTitle;

        tbody.innerHTML = '';
        for (let i=0;i<qty;i++) {
            tbody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td class="text-center align-middle">${i+1}</td>
                    <td class="align-middle">
                        <input class="form-control form-control-sm" required
                            name="units[${i}][${key}]"
                            placeholder="${placeholder}">
                    </td>
                    <td class="align-middle">
                        <textarea class="form-control form-control-sm"
                            name="units[${i}][description]" rows="2"
                            placeholder="optional..."></textarea>
                    </td>
                    <td class="align-middle">
                        <input type="file" class="photo-input" name="units[${i}][photo]" accept="image/*">
                        <div class="text-muted mt-1"><small>jpg/png/webp (opsional, max 5MB)</small></div>
                    </td>
                </tr>
            `);
        }
    }



    window.addEventListener('quality-table-updated', function(e){
        const detail = (e && e.detail) ? e.detail : {};
        const productId = detail.product_id || '';
        const qty = detail.qty || 0;
        const productText = detail.product_text || 'No product selected';

        document.getElementById('quality_product_id').value = productId;
        document.getElementById('quality_qty').value = qty;

        document.getElementById('quality_total_qty').textContent = qty;
        document.getElementById('quality_selected_product_text').textContent = productText;

        const type = document.getElementById('quality_type').value;
        buildUnits(qty, type);
    });

    // saat tab quality benar-benar aktif
    document.querySelector('button[data-target="#pane-quality"]')
        ?.addEventListener('shown.bs.tab', function(){
            syncQualityWarehouse();
            const qty = document.getElementById('quality_qty')?.value || 0;
            const type = document.getElementById('quality_type')?.value || 'defect';
            buildUnits(qty, type);
        });

    // fallback click
    document.getElementById('tab-quality')?.addEventListener('click', ()=>{
        syncQualityWarehouse();
        const qty = document.getElementById('quality_qty')?.value || 0;
        const type = document.getElementById('quality_type')?.value || 'defect';
        buildUnits(qty, type);
    });

    document.getElementById('warehouse_id_quality')?.addEventListener('change', syncQualityWarehouse);

    document.getElementById('quality_type')?.addEventListener('change', ()=>{
        const typeVal = document.getElementById('quality_type').value;

        // update unit table
        const qty = document.getElementById('quality_qty')?.value || 0;
        buildUnits(qty, typeVal);

        // NEW: kasih tahu Livewire agar tabel stok berubah sumbernya
        try{
            if (window.Livewire && typeof window.Livewire.emit === 'function') {
                window.Livewire.emit('qualityTypeChanged', typeVal);
            }
        }catch(e){}
    });


    document.getElementById('qualityForm')?.addEventListener('submit', function(ev){
        const wh = document.getElementById('quality_warehouse_id').value;
        const pid = document.getElementById('quality_product_id').value;
        const qty = parseInt(document.getElementById('quality_qty').value || '0', 10);

        if (!wh) { ev.preventDefault(); toastWarn('Please select Warehouse first (Quality tab).'); return; }
        if (!pid) { ev.preventDefault(); toastWarn('Please select 1 product (via SearchProduct) for Quality Reclass.'); return; }
        if (!qty || qty < 1) { ev.preventDefault(); toastWarn('Qty must be at least 1.'); return; }
    });

    // INIT: sekali saat Livewire ready
    document.addEventListener('livewire:load', function(){
        syncQualityWarehouse();
    });

})();
</script>
@endpush

{{-- Modules/Adjustment/Resources/views/partials/quality_reclass_good_to_issue.blade.php --}}

<div class="card mt-3">
    <div class="card-header">
        <strong>Quality Reclass (GOOD → DEFECT/DAMAGED)</strong>
    </div>

    <div class="card-body">
        {{-- marker mode untuk backend --}}
        <input type="hidden" name="mode" value="quality_reclass_good_to_issue" />

        <div class="row">
            <div class="col-md-3">
                <label class="mb-1">Date <span class="text-danger">*</span></label>
                <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
            </div>

            <div class="col-md-6">
                <label class="mb-1">Warehouse <span class="text-danger">*</span></label>
                <select name="warehouse_id" id="qrcWarehouse" class="form-control" required>
                    <option value="">-- Select Warehouse --</option>
                    @foreach($warehouses as $w)
                        <option value="{{ (int)$w->id }}" {{ (int)old('warehouse_id', $defaultWarehouseId ?? 0) === (int)$w->id ? 'selected' : '' }}>
                            {{ $w->warehouse_name }} {{ ((int)$w->is_main === 1) ? '(Main)' : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="text-muted small mt-1">Rack akan dipilih per item di table.</div>
            </div>
        </div>

        <div class="mt-3">
            <label class="mb-1">User Note (optional)</label>
            <textarea name="user_note" class="form-control" rows="2" placeholder="Contoh: alasan QC / info teknisi...">{{ old('user_note') }}</textarea>
        </div>

        <hr>

        {{-- TABLE (Livewire): auto add dari SearchProduct (event productSelected) --}}
        <livewire:adjustment.product-table mode="quality" :warehouseId="(int)($defaultWarehouseId ?? 0)" />

        <div class="alert alert-light border mt-3 mb-0">
            <div class="font-weight-bold mb-1">Rule</div>
            <ul class="mb-0 pl-3">
                <li>Qty menentukan jumlah detail unit yang wajib diisi untuk DEFECT/DAMAGED.</li>
                <li>Rack dipilih per item, lalu semua unit detail mengikuti rack tersebut.</li>
                <li>Photo optional (max 5MB per unit).</li>
            </ul>
        </div>
    </div>
</div>

@push('page_scripts')
<script>
(function(){
    // Quality Classic: warehouse selector -> Livewire event
    function emitWarehouse(){
        const el = document.getElementById('qrcWarehouse');
        if(!el || !window.Livewire) return;
        const wid = parseInt(el.value || 0);
        Livewire.emit('qualityWarehouseChanged', wid > 0 ? wid : null);
    }

    document.addEventListener('DOMContentLoaded', function(){
        const el = document.getElementById('qrcWarehouse');
        if(el){
            el.addEventListener('change', emitWarehouse);
            // init emit
            emitWarehouse();
        }
    });
})();
</script>
@endpush
<div>
    @if (session()->has('message'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="alert-body">
                <span>{{ session('message') }}</span>
                {{-- FIX: ini alert dismiss, bukan modal dismiss --}}
                <button type="button" class="btn-close" data-bs-dismiss="alert" data-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    {{-- =========================
        STOCK MODE ONLY (confirm-like UI)
       ========================= --}}
    @if($mode !== 'quality')

        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="font-weight-bold">Receive Details (per item)</div>
                    <div class="text-muted small">
                        Rule: <b>ADD DEFECT/DAMAGED</b> wajib isi per-unit detail. <b>SUB DEFECT/DAMAGED</b> wajib pick unit IDs.
                    </div>
                </div>
            </div>
        </div>

        @if(empty($products))
            <div class="alert alert-danger">
                Please search & select products!
            </div>
        @else
            @foreach($products as $key => $product)
                @php
                    $rowCondition = strtolower((string)($product['condition'] ?? 'good'));
                    $rowType      = strtolower((string)($product['type'] ?? 'add'));
                    $rowQty       = (int)($product['quantity'] ?? 1);
                    $selectedRackId = isset($product['rack_id']) ? (int)$product['rack_id'] : null;

                    $defectsJson = (string)($product['defects_json'] ?? '[]');
                    $damagedJson = (string)($product['damaged_json'] ?? '[]');
                    $defectPick  = (string)($product['defect_unit_ids'] ?? '[]');
                    $damagedPick = (string)($product['damaged_unit_ids'] ?? '[]');

                    $stockQty = (int)($product['stock_qty'] ?? 0);
                    $unit     = (string)($product['product_unit'] ?? '');
                @endphp

                <div class="card mb-3 adj-card" data-row="{{ $key }}">
                    <div class="card-body">
                        {{-- hidden arrays for controller --}}
                        <input type="hidden" name="product_ids[]" value="{{ (int)$product['id'] }}">

                        <input type="hidden" name="defects_json[]" class="adj-defects-json" value="{{ $defectsJson }}">
                        <input type="hidden" name="damaged_json[]" class="adj-damaged-json" value="{{ $damagedJson }}">
                        <input type="hidden" name="defect_unit_ids[]" class="adj-defect-pick" value="{{ $defectPick }}">
                        <input type="hidden" name="damaged_unit_ids[]" class="adj-damaged-pick" value="{{ $damagedPick }}">

                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="font-weight-bold" style="font-size:15px;">
                                    {{ $product['product_name'] ?? '-' }}
                                </div>
                                <div class="text-muted small">
                                    Code: {{ $product['product_code'] ?? '-' }}
                                    @if(!empty($product['id']))
                                        <span class="ml-2">PID: {{ (int)$product['id'] }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="badge badge-info">
                                    Stock: {{ number_format($stockQty) }} {{ $unit }}
                                </span>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-3">
                                <label class="small text-muted mb-1">Rack <span class="text-danger">*</span></label>
                                <select
                                    class="form-control"
                                    name="rack_ids[]"
                                    wire:model.lazy="products.{{ $key }}.rack_id"
                                    required
                                >
                                    <option value="">-- Select Rack --</option>
                                    @foreach(($rackOptions ?? []) as $opt)
                                        <option value="{{ $opt['id'] }}" {{ (int)$opt['id'] === (int)$selectedRackId ? 'selected' : '' }}>
                                            {{ $opt['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Required</small>
                            </div>

                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Condition</label>
                                <select name="conditions[]" class="form-control adj-condition">
                                    <option value="good" {{ $rowCondition==='good'?'selected':'' }}>GOOD</option>
                                    <option value="defect" {{ $rowCondition==='defect'?'selected':'' }}>DEFECT</option>
                                    <option value="damaged" {{ $rowCondition==='damaged'?'selected':'' }}>DAMAGED</option>
                                </select>
                                <small class="text-muted">Bucket target</small>
                            </div>

                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Qty</label>
                                <input
                                    type="number"
                                    min="1"
                                    class="form-control adj-qty"
                                    name="quantities[]"
                                    value="{{ $rowQty }}"
                                >
                            </div>

                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Type</label>
                                <select name="types[]" class="form-control adj-type">
                                    <option value="add" {{ $rowType==='add'?'selected':'' }}>Add</option>
                                    <option value="sub" {{ $rowType==='sub'?'selected':'' }}>Sub</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="small text-muted mb-1">Per-Unit / Pick</label>
                                <div class="d-flex" style="gap:8px;">
                                    <button type="button"
                                            class="btn btn-outline-primary btn-perunit flex-grow-1"
                                            data-row="{{ $key }}">
                                        Open
                                    </button>
                                    <button type="button"
                                            class="btn btn-danger"
                                            wire:click="removeProduct({{ $key }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>

                                <div class="small text-muted mt-2 perunit-hint">-</div>
                            </div>

                            <div class="col-12 mt-3">
                                <label class="small text-muted mb-1">Note (optional)</label>
                                <input type="text" name="notes[]" class="form-control" value="{{ $product['note'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        {{-- =========================
            Modal: Per-Unit / Pick Items
           ========================= --}}
        <div class="modal fade" id="adjPerUnitModal" tabindex="-1" role="dialog" aria-labelledby="adjPerUnitModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0" id="adjPerUnitModalLabel">Per-Unit Detail</h5>
                            <div class="text-muted small" id="adjPerUnitSubTitle">-</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        {{-- info --}}
                        <div class="alert alert-info py-2">
                            <div class="small">
                                <b>ADD DEFECT/DAMAGED</b>: auto generate row = Qty (tiap row = 1 pcs).<br>
                                <b>SUB DEFECT/DAMAGED</b>: pick unit IDs sesuai Qty.
                            </div>
                        </div>

                        {{-- ADD: DEFECT form --}}
                        <div id="adjAddDefectWrap" style="display:none;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="font-weight-bold">Defect Items</div>
                                <div class="text-muted small">Total rows harus = Qty</div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width:60px;">#</th>
                                        <th style="width:260px;">To Rack</th>
                                        <th style="width:240px;">Defect Type <span class="text-danger">*</span></th>
                                        <th>Description</th>
                                    </tr>
                                    </thead>
                                    <tbody id="adjAddDefectTbody"></tbody>
                                </table>
                            </div>
                        </div>

                        {{-- ADD: DAMAGED form --}}
                        <div id="adjAddDamagedWrap" style="display:none;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="font-weight-bold">Damaged Items</div>
                                <div class="text-muted small">Total rows harus = Qty</div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width:60px;">#</th>
                                        <th style="width:260px;">To Rack</th>
                                        <th>Damaged Reason <span class="text-danger">*</span></th>
                                    </tr>
                                    </thead>
                                    <tbody id="adjAddDamagedTbody"></tbody>
                                </table>
                            </div>
                        </div>

                        {{-- SUB: PICK items --}}
                        <div id="adjSubPickWrap" style="display:none;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="font-weight-bold">Pick Items</div>
                                <div class="text-muted small">
                                    Selected: <span id="adjPickSelected">0</span> / <span id="adjPickRequired">0</span>
                                </div>
                            </div>

                            <div class="mb-2 small text-muted">
                                Sumber data: unit DEFECT/DAMAGED yang <b>belum moved_out</b> di warehouse aktif.
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0">
                                    <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width:50px;">
                                            <input type="checkbox" id="adjPickToggleAll">
                                        </th>
                                        <th style="width:120px;">Unit ID</th>
                                        <th>Info</th>
                                    </tr>
                                    </thead>
                                    <tbody id="adjPickTbody">
                                    <tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="small text-muted mt-2">
                                Tips: jumlah checkbox terpilih harus sama dengan Qty, kalau lebih tombol save akan block.
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <div class="mr-auto small text-muted" id="adjModalHint">-</div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="adjModalSaveBtn">Save</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- CSS --}}
        @push('page_css')
            <style>
                .adj-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 8px 18px rgba(15,23,42,.06); }
                .adj-card .card-body { padding: 14px; }
                .modal-xl { max-width: 1100px; }
            </style>
        @endpush

        {{-- JS --}}
        @push('page_scripts')
            <script>
                (function(){
                    function asInt(v){ var n=parseInt(v,10); return isNaN(n)?0:n; }
                    function parseJson(v){ try{ return JSON.parse(v || '[]'); }catch(e){ return []; } }
                    function toJson(v){ return JSON.stringify(v || []); }

                    // ==========================================
                    // ✅ MODAL BRIDGE (Support BS4 & BS5)
                    // ==========================================
                    var modalDom = document.getElementById('adjPerUnitModal');
                    var hasBS5 = !!(window.bootstrap && window.bootstrap.Modal);
                    var hasJQ  = !!window.jQuery;

                    function showModal(){
                        if(!modalDom) return;
                        if(hasBS5){
                            var inst = window.bootstrap.Modal.getOrCreateInstance(modalDom);
                            inst.show();
                            return;
                        }
                        if(hasJQ){
                            window.jQuery(modalDom).modal('show');
                            return;
                        }
                        // fallback
                        modalDom.style.display = 'block';
                        modalDom.classList.add('show');
                    }

                    function hideModal(){
                        if(!modalDom) return;
                        if(hasBS5){
                            var inst = window.bootstrap.Modal.getOrCreateInstance(modalDom);
                            inst.hide();
                            return;
                        }
                        if(hasJQ){
                            window.jQuery(modalDom).modal('hide');
                            return;
                        }
                        modalDom.style.display = 'none';
                        modalDom.classList.remove('show');
                    }

                    // cache DOM modal content
                    var modalTitle = document.getElementById('adjPerUnitModalLabel');
                    var modalSub = document.getElementById('adjPerUnitSubTitle');
                    var modalHint = document.getElementById('adjModalHint');

                    var wrapAddDefect = document.getElementById('adjAddDefectWrap');
                    var wrapAddDamaged = document.getElementById('adjAddDamagedWrap');
                    var wrapSubPick = document.getElementById('adjSubPickWrap');

                    var tbAddDefect = document.getElementById('adjAddDefectTbody');
                    var tbAddDamaged = document.getElementById('adjAddDamagedTbody');
                    var tbPick = document.getElementById('adjPickTbody');

                    var pickSelectedEl = document.getElementById('adjPickSelected');
                    var pickRequiredEl = document.getElementById('adjPickRequired');
                    var pickToggleAll = document.getElementById('adjPickToggleAll');

                    var saveBtn = document.getElementById('adjModalSaveBtn');

                    var currentRowIndex = null; // row key
                    var currentMode = null;     // add_defect | add_damaged | sub_defect | sub_damaged

                    function getRowEl(row){
                        return document.querySelector('.adj-card[data-row="'+row+'"]');
                    }

                    function getRackOptionsHtml(rowEl){
                        var rackSelect = rowEl.querySelector('select[name="rack_ids[]"]');
                        if(!rackSelect) return '<option value="">-- Select Rack --</option>';
                        return rackSelect.innerHTML;
                    }

                    function setHint(rowEl, text){
                        var hint = rowEl.querySelector('.perunit-hint');
                        if(hint) hint.textContent = text;
                    }

                    function refreshRowHint(rowEl){
                        var condEl = rowEl.querySelector('.adj-condition');
                        var typeEl = rowEl.querySelector('.adj-type');
                        var qtyEl  = rowEl.querySelector('.adj-qty');

                        var defectsJsonEl = rowEl.querySelector('.adj-defects-json');
                        var damagedJsonEl = rowEl.querySelector('.adj-damaged-json');
                        var defectPickEl  = rowEl.querySelector('.adj-defect-pick');
                        var damagedPickEl = rowEl.querySelector('.adj-damaged-pick');

                        var cond = String(condEl.value||'good').toLowerCase();
                        var typ  = String(typeEl.value||'add').toLowerCase();
                        var qty  = asInt(qtyEl.value||0);

                        if(cond === 'good'){
                            setHint(rowEl, 'GOOD: no per-unit needed.');
                            return;
                        }

                        if(typ === 'add' && cond === 'defect'){
                            var arr = parseJson(defectsJsonEl.value || '[]');
                            setHint(rowEl, 'ADD DEFECT: detail ' + arr.length + '/' + qty + ' unit');
                            return;
                        }
                        if(typ === 'add' && cond === 'damaged'){
                            var arr2 = parseJson(damagedJsonEl.value || '[]');
                            setHint(rowEl, 'ADD DAMAGED: detail ' + arr2.length + '/' + qty + ' unit');
                            return;
                        }
                        if(typ === 'sub' && cond === 'defect'){
                            var ids = parseJson(defectPickEl.value || '[]');
                            setHint(rowEl, 'SUB DEFECT: picked ' + ids.length + '/' + qty + ' unit ID');
                            return;
                        }
                        if(typ === 'sub' && cond === 'damaged'){
                            var ids2 = parseJson(damagedPickEl.value || '[]');
                            setHint(rowEl, 'SUB DAMAGED: picked ' + ids2.length + '/' + qty + ' unit ID');
                            return;
                        }

                        setHint(rowEl, '-');
                    }

                    function hideAllWrap(){
                        wrapAddDefect.style.display = 'none';
                        wrapAddDamaged.style.display = 'none';
                        wrapSubPick.style.display = 'none';
                    }

                    function buildAddDefectRows(rowEl, qty){
                        tbAddDefect.innerHTML = '';
                        var rackOptions = getRackOptionsHtml(rowEl);

                        for(var i=0;i<qty;i++){
                            var tr = document.createElement('tr');
                            tr.innerHTML = '' +
                                '<td class="text-center align-middle">'+(i+1)+'</td>' +
                                '<td><select class="form-control form-control-sm js-def-rack">'+ rackOptions +'</select></td>' +
                                '<td><input type="text" class="form-control form-control-sm js-def-type" placeholder="contoh: bubble / baret"></td>' +
                                '<td><textarea class="form-control form-control-sm js-def-desc" rows="2" placeholder="optional"></textarea></td>';
                            tbAddDefect.appendChild(tr);
                        }
                    }

                    function buildAddDamagedRows(rowEl, qty){
                        tbAddDamaged.innerHTML = '';
                        var rackOptions = getRackOptionsHtml(rowEl);

                        for(var i=0;i<qty;i++){
                            var tr = document.createElement('tr');
                            tr.innerHTML = '' +
                                '<td class="text-center align-middle">'+(i+1)+'</td>' +
                                '<td><select class="form-control form-control-sm js-dm-rack">'+ rackOptions +'</select></td>' +
                                '<td><textarea class="form-control form-control-sm js-dm-reason" rows="2" placeholder="contoh: pecah sudut kiri"></textarea></td>';
                            tbAddDamaged.appendChild(tr);
                        }
                    }

                    function loadPickItems(rowEl, requiredQty, condition){
                        tbPick.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr>';
                        pickRequiredEl.textContent = String(requiredQty);
                        pickSelectedEl.textContent = '0';
                        if(pickToggleAll) pickToggleAll.checked = false;

                        // ✅ FIX: ambil warehouse dari STOCK TAB (name=warehouse_id)
                        var warehouseIdEl = document.querySelector('select[name="warehouse_id"]');
                        var warehouseId = warehouseIdEl ? asInt(warehouseIdEl.value) : 0;

                        var productIdEl = rowEl.querySelector('input[name="product_ids[]"]');
                        var productId = productIdEl ? asInt(productIdEl.value) : 0;

                        var rackIdEl = rowEl.querySelector('select[name="rack_ids[]"]');
                        var rackId = rackIdEl ? asInt(rackIdEl.value) : 0;

                        var url = "{{ route('adjustments.pick-units') }}" +
                            "?warehouse_id=" + encodeURIComponent(warehouseId) +
                            "&product_id=" + encodeURIComponent(productId) +
                            "&condition=" + encodeURIComponent(condition) +
                            "&rack_id=" + encodeURIComponent(rackId);

                        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
                            .then(r => r.json())
                            .then(function(res){
                                if(!res || !res.success){
                                    var msg = (res && res.message) ? res.message : 'Failed to load items.';
                                    tbPick.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">'+msg+'</td></tr>';
                                    return;
                                }

                                var data = res.data || [];
                                if(!data.length){
                                    tbPick.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No available units found.</td></tr>';
                                    return;
                                }

                                tbPick.innerHTML = '';
                                data.forEach(function(it){
                                    var tr = document.createElement('tr');
                                    tr.innerHTML =
                                        '<td class="text-center align-middle"><input type="checkbox" class="js-pick-cb" value="'+it.id+'"></td>' +
                                        '<td class="align-middle">ID#'+it.id+'</td>' +
                                        '<td class="align-middle">'+ (it.label || '') +'</td>';
                                    tbPick.appendChild(tr);
                                });

                                updatePickCount();
                            })
                            .catch(function(){
                                tbPick.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">Failed to load items (network error).</td></tr>';
                            });
                    }

                    function updatePickCount(){
                        var cbs = tbPick.querySelectorAll('.js-pick-cb');
                        var cnt = 0;
                        cbs.forEach(function(cb){ if(cb.checked) cnt++; });
                        pickSelectedEl.textContent = String(cnt);
                    }

                    function openModalForRow(row){
                        var rowEl = getRowEl(row);
                        if(!rowEl) return;

                        currentRowIndex = row;

                        var condEl = rowEl.querySelector('.adj-condition');
                        var typeEl = rowEl.querySelector('.adj-type');
                        var qtyEl  = rowEl.querySelector('.adj-qty');

                        var cond = String(condEl.value||'good').toLowerCase();
                        var typ  = String(typeEl.value||'add').toLowerCase();
                        var qty  = asInt(qtyEl.value||0);

                        var productName = rowEl.querySelector('.font-weight-bold') ? rowEl.querySelector('.font-weight-bold').textContent : 'Item';

                        modalTitle.textContent = 'Per-Unit / Pick — ' + productName;
                        modalSub.textContent = 'Type: ' + typ.toUpperCase() + ' | Condition: ' + cond.toUpperCase() + ' | Qty: ' + qty;

                        hideAllWrap();

                        if(cond === 'good'){
                            modalHint.textContent = 'GOOD: tidak butuh per-unit.';
                            if(saveBtn) saveBtn.disabled = true;
                            return;
                        }

                        if(saveBtn) saveBtn.disabled = false;

                        if(typ === 'add' && cond === 'defect'){
                            currentMode = 'add_defect';
                            modalHint.textContent = 'Wajib isi defect type di setiap row.';
                            wrapAddDefect.style.display = '';
                            buildAddDefectRows(rowEl, qty);

                            var existing = parseJson(rowEl.querySelector('.adj-defects-json').value || '[]');
                            if(existing.length === qty){
                                var trs = tbAddDefect.querySelectorAll('tr');
                                trs.forEach(function(tr, i){
                                    var u = existing[i] || {};
                                    tr.querySelector('.js-def-rack').value = u.rack_id || '';
                                    tr.querySelector('.js-def-type').value = u.defect_type || '';
                                    tr.querySelector('.js-def-desc').value = u.description || '';
                                });
                            }
                            return;
                        }

                        if(typ === 'add' && cond === 'damaged'){
                            currentMode = 'add_damaged';
                            modalHint.textContent = 'Wajib isi damaged reason di setiap row.';
                            wrapAddDamaged.style.display = '';
                            buildAddDamagedRows(rowEl, qty);

                            var existing2 = parseJson(rowEl.querySelector('.adj-damaged-json').value || '[]');
                            if(existing2.length === qty){
                                var trs2 = tbAddDamaged.querySelectorAll('tr');
                                trs2.forEach(function(tr, i){
                                    var u = existing2[i] || {};
                                    tr.querySelector('.js-dm-rack').value = u.rack_id || '';
                                    tr.querySelector('.js-dm-reason').value = u.reason || '';
                                });
                            }
                            return;
                        }

                        if(typ === 'sub' && (cond === 'defect' || cond === 'damaged')){
                            currentMode = (cond === 'defect') ? 'sub_defect' : 'sub_damaged';
                            modalHint.textContent = 'Wajib pick unit IDs sesuai Qty.';
                            wrapSubPick.style.display = '';
                            loadPickItems(rowEl, qty, cond);

                            setTimeout(function(){
                                var key = (cond === 'defect') ? '.adj-defect-pick' : '.adj-damaged-pick';
                                var picked = parseJson(rowEl.querySelector(key).value || '[]').map(function(x){ return String(x); });
                                var cbs = tbPick.querySelectorAll('.js-pick-cb');
                                cbs.forEach(function(cb){
                                    if(picked.includes(String(cb.value))) cb.checked = true;
                                });
                                updatePickCount();
                            }, 350);

                            return;
                        }

                        modalHint.textContent = 'Unsupported mode.';
                        if(saveBtn) saveBtn.disabled = true;
                    }

                    function saveModal(){
                        if(currentRowIndex === null) return;

                        var rowEl = getRowEl(currentRowIndex);
                        if(!rowEl) return;

                        var qty = asInt(rowEl.querySelector('.adj-qty').value || 0);

                        function resetPayload(){
                            rowEl.querySelector('.adj-defects-json').value = '[]';
                            rowEl.querySelector('.adj-damaged-json').value = '[]';
                            rowEl.querySelector('.adj-defect-pick').value = '[]';
                            rowEl.querySelector('.adj-damaged-pick').value = '[]';
                        }

                        if(currentMode === 'add_defect'){
                            var out = [];
                            var trs = tbAddDefect.querySelectorAll('tr');

                            if(trs.length !== qty){
                                alert('Row count mismatch with Qty.');
                                return;
                            }

                            for(var i=0;i<trs.length;i++){
                                var tr = trs[i];
                                var rackId = tr.querySelector('.js-def-rack').value || '';
                                var defectType = String(tr.querySelector('.js-def-type').value || '').trim();
                                var desc = String(tr.querySelector('.js-def-desc').value || '').trim();

                                if(defectType === ''){
                                    alert('Defect Type is required (row #' + (i+1) + ').');
                                    return;
                                }

                                out.push({
                                    rack_id: rackId ? asInt(rackId) : null,
                                    defect_type: defectType,
                                    description: desc
                                });
                            }

                            resetPayload();
                            rowEl.querySelector('.adj-defects-json').value = toJson(out);
                            refreshRowHint(rowEl);
                            hideModal();
                            return;
                        }

                        if(currentMode === 'add_damaged'){
                            var out2 = [];
                            var trs2 = tbAddDamaged.querySelectorAll('tr');

                            if(trs2.length !== qty){
                                alert('Row count mismatch with Qty.');
                                return;
                            }

                            for(var j=0;j<trs2.length;j++){
                                var tr2 = trs2[j];
                                var rackId2 = tr2.querySelector('.js-dm-rack').value || '';
                                var reason = String(tr2.querySelector('.js-dm-reason').value || '').trim();

                                if(reason === ''){
                                    alert('Damaged Reason is required (row #' + (j+1) + ').');
                                    return;
                                }

                                out2.push({
                                    rack_id: rackId2 ? asInt(rackId2) : null,
                                    reason: reason
                                });
                            }

                            resetPayload();
                            rowEl.querySelector('.adj-damaged-json').value = toJson(out2);
                            refreshRowHint(rowEl);
                            hideModal();
                            return;
                        }

                        if(currentMode === 'sub_defect' || currentMode === 'sub_damaged'){
                            var picked = [];
                            var cbs = tbPick.querySelectorAll('.js-pick-cb');
                            cbs.forEach(function(cb){ if(cb.checked) picked.push(asInt(cb.value)); });

                            if(picked.length !== qty){
                                alert('Picked must match Qty. Picked=' + picked.length + ' Qty=' + qty);
                                return;
                            }

                            var uniq = Array.from(new Set(picked));
                            if(uniq.length !== picked.length){
                                alert('Picked IDs must be unique.');
                                return;
                            }

                            resetPayload();
                            if(currentMode === 'sub_defect'){
                                rowEl.querySelector('.adj-defect-pick').value = toJson(uniq);
                            } else {
                                rowEl.querySelector('.adj-damaged-pick').value = toJson(uniq);
                            }

                            refreshRowHint(rowEl);
                            hideModal();
                            return;
                        }
                    }

                    // bind per row (safe rebind)
                    function bindRows(){
                        document.querySelectorAll('.adj-card').forEach(function(card){
                            if(card.dataset.bound === '1') return;
                            card.dataset.bound = '1';

                            var condEl = card.querySelector('.adj-condition');
                            var typeEl = card.querySelector('.adj-type');
                            var qtyEl  = card.querySelector('.adj-qty');

                            [condEl, typeEl, qtyEl].forEach(function(el){
                                if(!el) return;
                                el.addEventListener('change', function(){ refreshRowHint(card); });
                                el.addEventListener('input', function(){ refreshRowHint(card); });
                            });

                            refreshRowHint(card);

                            var btn = card.querySelector('.btn-perunit');
                            if(btn){
                                btn.addEventListener('click', function(){
                                    var row = btn.getAttribute('data-row');
                                    openModalForRow(row);
                                    showModal();
                                });
                            }
                        });
                    }

                    // pick table interactions
                    document.addEventListener('change', function(e){
                        if(e.target && e.target.classList.contains('js-pick-cb')){
                            updatePickCount();
                        }
                    });

                    if(pickToggleAll){
                        pickToggleAll.addEventListener('change', function(){
                            var cbs = tbPick.querySelectorAll('.js-pick-cb');
                            cbs.forEach(function(cb){ cb.checked = pickToggleAll.checked; });
                            updatePickCount();
                        });
                    }

                    if(saveBtn){
                        saveBtn.addEventListener('click', saveModal);
                    }

                    // initial bind
                    setTimeout(bindRows, 50);

                    // ✅ IMPORTANT: Livewire rerender hook (so Open button still works)
                    document.addEventListener('livewire:load', function(){
                        setTimeout(bindRows, 50);

                        try{
                            if(window.Livewire && window.Livewire.hook){
                                window.Livewire.hook('message.processed', function(){
                                    // reset bound marker then bind again
                                    document.querySelectorAll('.adj-card').forEach(function(card){
                                        card.dataset.bound = '0';
                                    });
                                    setTimeout(bindRows, 30);
                                });
                            }
                        }catch(e){}
                    });

                })();
            </script>
        @endpush

    @else
        {{-- =========================
            QUALITY MODE (biarkan versi kamu)
           ========================= --}}
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th style="width:60px">#</th>
                    <th>Product</th>
                    <th style="width:160px">Code</th>
                    <th style="width:140px" class="text-center">Stock</th>
                    <th style="width:220px">Rack</th>
                    <th style="width:90px" class="text-center">Action</th>
                </tr>
                </thead>
                <tbody>
                @if(!empty($products))
                    @foreach($products as $key => $product)
                        @php
                            $stockLabel = (string)($product['stock_label'] ?? 'GOOD');
                            $availableQty = (int)($product['available_qty'] ?? 0);

                            $badgeClass = 'badge-success';
                            if ($stockLabel === 'DEFECT') $badgeClass = 'badge-warning';
                            if ($stockLabel === 'DAMAGED') $badgeClass = 'badge-danger';

                            $selectedRackId = isset($product['rack_id']) ? (int)$product['rack_id'] : null;
                        @endphp
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $product['product_name'] }}</td>
                            <td>{{ $product['product_code'] }}</td>
                            <td class="text-center">
                                <span class="badge {{ $badgeClass }}">
                                    {{ $stockLabel }}: {{ $availableQty }}
                                </span>
                            </td>
                            <td>
                                <select class="form-control" name="rack_id" wire:model="products.{{ $key }}.rack_id" required>
                                    <option value="">-- Select Rack --</option>
                                    @foreach(($rackOptions ?? []) as $opt)
                                        <option value="{{ $opt['id'] }}" {{ (int)$opt['id'] === (int)$selectedRackId ? 'selected' : '' }}>
                                            {{ $opt['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Required (Quality)</small>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-danger" wire:click="removeProduct({{ $key }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="6" class="text-center text-danger">Please search & select products!</td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    @endif
</div>

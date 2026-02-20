<div>
    @if (session()->has('message'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="alert-body">
                <span>{{ session('message') }}</span>

                {{-- BS4 close button (FIX X ga boleh btn-close) --}}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>
    @endif

    <h5 class="mb-2">Receive Details (per item)</h5>
    <div class="alert alert-light border mb-3">
        <div class="d-flex align-items-start">
            <div>
                <div class="font-weight-bold mb-1">Rule Validasi</div>
                <div class="text-muted">
                    Isi qty yang akan ditambahkan per produk:
                    <strong>Total = Good + Defect + Damaged</strong>.
                </div>
                <div class="text-muted mt-2">
                    <small>
                        - <strong>GOOD</strong> bisa dibagi ke beberapa rack (split).<br>
                        - <strong>Defect/Damaged</strong> dicatat <strong>per unit</strong> (tiap baris qty = 1) dan tiap unit wajib pilih rack.<br>
                        - Foto opsional, tapi sangat disarankan untuk audit.
                    </small>
                </div>
            </div>
        </div>
    </div>

    @if(empty($products))
        <div class="alert alert-danger">
            Please search &amp; select products!
        </div>
    @else

        <div class="table-responsive">
            <table class="table table-bordered table-sm table-modern" id="adjustment-add-table">
                <thead>
                    <tr>
                        <th style="min-width: 320px;">Product</th>
                        <th class="text-center" style="width: 90px;">Total</th>
                        <th class="text-center" style="width: 110px;">Good</th>
                        <th class="text-center" style="width: 110px;">Defect</th>
                        <th class="text-center" style="width: 110px;">Damaged</th>

                        <th class="text-center" style="width: 180px;">Rack Allocation</th>
                        <th class="text-center" style="width: 170px;">Per-Unit Notes</th>
                        <th class="text-center" style="width: 140px;">Status</th>
                        <th class="text-center" style="width: 70px;">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($products as $idx => $product)
                        @php
                            $pid  = (int) ($product['id'] ?? 0);
                            $pname = (string) ($product['product_name'] ?? '-');
                            $pcode = (string) ($product['product_code'] ?? '');
                            $stockQty = (int) ($product['stock_qty'] ?? 0);
                            $unit = (string) ($product['product_unit'] ?? '');

                            $oldGood = (int) old("items.$idx.qty_good", (int)($product['qty_good'] ?? 0));
                            $oldDef  = (int) old("items.$idx.qty_defect", (int)($product['qty_defect'] ?? 0));
                            $oldDam  = (int) old("items.$idx.qty_damaged", (int)($product['qty_damaged'] ?? 0));

                            $oldGoodAlloc = old("items.$idx.good_allocations", (array)($product['good_allocations'] ?? []));
                            $oldDefects   = old("items.$idx.defects", (array)($product['defects'] ?? []));
                            $oldDamages   = old("items.$idx.damaged_items", (array)($product['damaged_items'] ?? []));
                        @endphp

                        <tr class="adj-receive-row"
                            data-index="{{ $idx }}"
                            data-product="{{ $pid }}">
                            <td class="align-middle">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <div class="font-weight-bold">{{ $pname }}</div>
                                        <div class="text-muted"><small>{{ $pcode }}</small></div>
                                        <div class="text-muted"><small>Stock: <b>{{ number_format($stockQty) }} {{ $unit }}</b></small></div>
                                    </div>
                                    <span class="badge badge-pill badge-light border px-2 py-1">
                                        PID: {{ $pid }}
                                    </span>
                                </div>

                                <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $pid }}">
                            </td>

                            <td class="text-center align-middle">
                                <span class="badge badge-primary adj-total-badge" data-idx="{{ $idx }}">0</span>
                            </td>

                            <td class="text-center align-middle">
                                <input type="number"
                                       min="0"
                                       step="1"
                                       class="form-control form-control-sm text-center qty-input qty-good"
                                       name="items[{{ $idx }}][qty_good]"
                                       value="{{ $oldGood }}"
                                       required>
                            </td>

                            <td class="text-center align-middle">
                                <input type="number"
                                       min="0"
                                       step="1"
                                       class="form-control form-control-sm text-center qty-input qty-defect"
                                       name="items[{{ $idx }}][qty_defect]"
                                       value="{{ $oldDef }}"
                                       required>
                            </td>

                            <td class="text-center align-middle">
                                <input type="number"
                                       min="0"
                                       step="1"
                                       class="form-control form-control-sm text-center qty-input qty-damaged"
                                       name="items[{{ $idx }}][qty_damaged]"
                                       value="{{ $oldDam }}"
                                       required>
                            </td>

                            <td class="text-center align-middle">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-good-rack"
                                        data-target="#goodAllocWrap-{{ $idx }}">
                                    Good Racks
                                </button>
                            </td>

                            <td class="text-center align-middle">
                                <button type="button"
                                        class="btn btn-sm btn-notes"
                                        data-target="#perUnitWrap-{{ $idx }}">
                                    Notes
                                    <span class="ml-2 badge badge-pill badge-defect badge-defect-count">0</span>
                                    <span class="ml-1 badge badge-pill badge-damaged badge-damaged-count">0</span>
                                </button>
                            </td>

                            <td class="text-center align-middle">
                                <span class="badge badge-secondary row-status">CHECK</span>
                                <div class="small text-muted mt-1 row-hint"></div>
                            </td>

                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-sm btn-danger" wire:click="removeProduct({{ $idx }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>

                        {{-- GOOD ALLOCATION --}}
                        <tr class="goodalloc-row" id="goodAllocWrap-{{ $idx }}" style="display:none;">
                            <td colspan="9" class="perunit-td">
                                <div class="perunit-card">
                                    <div class="perunit-card-header">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="font-weight-bold">
                                                Good Rack Allocation — {{ $pname }}
                                            </div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary btn-close-goodalloc"
                                                    data-target="#goodAllocWrap-{{ $idx }}">
                                                Close
                                            </button>
                                        </div>
                                        <div class="text-muted mt-1">
                                            <small>
                                                Total qty pada tabel ini harus sama dengan nilai <b>Good</b>.
                                                Kamu bisa split Good ke beberapa rack.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="perunit-card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="text-muted">
                                                <small>Rack wajib dipilih hanya untuk baris yang qty &gt; 0.</small>
                                            </div>
                                            <button type="button"
                                                    class="btn btn-sm btn-primary btn-add-goodalloc"
                                                    data-idx="{{ $idx }}">
                                                + Add Row
                                            </button>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0 section-table">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" style="width: 55px;">#</th>
                                                        <th style="width: 260px;">To Rack *</th>
                                                        <th class="text-center" style="width: 160px;">Qty</th>
                                                        <th class="text-center" style="width: 90px;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="goodalloc-tbody" data-idx="{{ $idx }}">
                                                    {{-- built by JS --}}
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <th colspan="2" class="text-right">Total</th>
                                                        <th class="text-center">
                                                            <span class="goodalloc-total" data-idx="{{ $idx }}">0</span>
                                                        </th>
                                                        <th></th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        <textarea class="d-none old-goodalloc-json" data-idx="{{ $idx }}">{{ json_encode($oldGoodAlloc) }}</textarea>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        {{-- PER-UNIT NOTES --}}
                        <tr class="perunit-row" id="perUnitWrap-{{ $idx }}" style="display:none;">
                            <td colspan="9" class="perunit-td">
                                <div class="perunit-card">
                                    <div class="perunit-card-header">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="font-weight-bold">
                                                Per-Unit Notes — {{ $pname }}
                                            </div>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary btn-close-notes"
                                                    data-target="#perUnitWrap-{{ $idx }}">
                                                Close
                                            </button>
                                        </div>
                                        <div class="text-muted mt-1">
                                            <small>
                                                Defect/Damaged disimpan <b>per unit</b> (masing-masing baris qty = 1),
                                                jadi tiap unit bisa punya catatan + foto + rack sendiri.
                                            </small>
                                        </div>
                                    </div>

                                    <div class="perunit-card-body">
                                        <div class="row">
                                            {{-- DEFECT --}}
                                            <div class="col-lg-6">
                                                <div class="section-title defect-title">
                                                    Defect Items (<span class="defect-count-text">0</span>)
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0 section-table">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 55px;" class="text-center">#</th>
                                                                <th style="width: 190px;">To Rack *</th>
                                                                <th style="min-width: 160px;">Defect Type *</th>
                                                                <th>Defect Description</th>
                                                                <th style="width: 190px;">Photo (optional)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="defect-tbody">
                                                            {{-- built by JS --}}
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="text-muted mt-2">
                                                    <small>Contoh defect type: bubble, retak ringan, baret, distorsi.</small>
                                                </div>
                                            </div>

                                            {{-- DAMAGED --}}
                                            <div class="col-lg-6 mt-3 mt-lg-0">
                                                <div class="section-title damaged-title">
                                                    Damaged Items (<span class="damaged-count-text">0</span>)
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered mb-0 section-table">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 55px;" class="text-center">#</th>
                                                                <th style="width: 190px;">To Rack *</th>
                                                                <th style="width: 160px;">Damaged Type *</th>
                                                                <th>Damage Description *</th>
                                                                <th style="width: 190px;">Photo (optional)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="damaged-tbody">
                                                            {{-- built by JS --}}
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="text-muted mt-2">
                                                    <small>Damaged Type untuk sekarang: <b>damaged</b> / <b>missing</b>.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-muted mt-3">
                                            <small>
                                                Tips: kalau defect/damaged tidak ada, biarkan qty = 0 dan notes tidak perlu diisi.
                                            </small>
                                        </div>

                                        <textarea class="d-none old-defects-json" data-idx="{{ $idx }}">{{ json_encode($oldDefects) }}</textarea>
                                        <textarea class="d-none old-damages-json" data-idx="{{ $idx }}">{{ json_encode($oldDamages) }}</textarea>
                                    </div>
                                </div>
                            </td>
                        </tr>

                    @endforeach
                </tbody>
            </table>
        </div>

        @push('page_scripts')
            <script>
                // =========================================================
                //  JS copied & adapted from transfer::confirm
                // =========================================================
                (function(){
                    function asInt(val){ const n = parseInt(val,10); return isNaN(n)?0:n; }
                    function safeJsonParse(text){ try{ if(!text) return []; return JSON.parse(text);}catch(e){ return []; } }

                    function getWarehouseId(){
                        const whSelect = document.getElementById('warehouse_id_stock');
                        return whSelect ? whSelect.value : null;
                    }

                    function buildRackOptionsHtml(whId, selectedValue){
                        let html = `<option value="">-- Select Rack --</option>`;
                        if(!whId || !window.RACKS_BY_WAREHOUSE || !window.RACKS_BY_WAREHOUSE[whId]) return html;

                        window.RACKS_BY_WAREHOUSE[whId].forEach(r => {
                            const sel = (selectedValue && String(selectedValue) === String(r.id)) ? 'selected' : '';
                            html += `<option value="${r.id}" ${sel}>${r.label}</option>`;
                        });
                        return html;
                    }

                    // ==========================
                    // GOOD ALLOCATION
                    // ==========================
                    function rebuildGoodAlloc(idx, keepOld=true){
                        const whId = getWarehouseId();
                        const wrap = document.getElementById('goodAllocWrap-' + idx);
                        if(!wrap) return;

                        const tbody = wrap.querySelector('.goodalloc-tbody');
                        let allocations = [];

                        if(keepOld){
                            const oldText = wrap.querySelector('.old-goodalloc-json')?.value || wrap.querySelector('.old-goodalloc-json')?.textContent || '';
                            allocations = safeJsonParse(oldText);
                        }

                        if(!Array.isArray(allocations) || allocations.length === 0){
                            allocations = [{ to_rack_id:'', qty:0 }];
                        }

                        tbody.innerHTML = '';
                        allocations.forEach((a,i) => {
                            const rackVal = a.to_rack_id ?? '';
                            const qtyVal  = a.qty ?? 0;
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="text-center align-middle">${i+1}</td>
                                <td class="align-middle">
                                    <select class="form-control form-control-sm goodalloc-rack"
                                            name="items[${idx}][good_allocations][${i}][to_rack_id]">
                                        ${buildRackOptionsHtml(whId, rackVal)}
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <input type="number" min="0" step="1"
                                           class="form-control form-control-sm text-center goodalloc-qty"
                                           name="items[${idx}][good_allocations][${i}][qty]"
                                           value="${asInt(qtyVal)}">
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-sm btn-danger btn-remove-goodalloc"
                                            data-idx="${idx}" data-row="${i}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });

                        tbody.querySelectorAll('select, input').forEach(el => {
                            el.addEventListener('change', ()=>syncGoodAllocTotal(idx));
                            el.addEventListener('input',  ()=>syncGoodAllocTotal(idx));
                        });

                        tbody.querySelectorAll('.btn-remove-goodalloc').forEach(btn => {
                            btn.addEventListener('click', ()=>{
                                removeGoodAllocRow(idx, asInt(btn.getAttribute('data-row')));
                            });
                        });

                        syncGoodAllocTotal(idx);
                    }

                    function readGoodAllocations(idx){
                        const wrap = document.getElementById('goodAllocWrap-' + idx);
                        if(!wrap) return [];

                        const rows = wrap.querySelectorAll('.goodalloc-tbody tr');
                        const result = [];
                        rows.forEach(tr => {
                            const rack = tr.querySelector('select.goodalloc-rack')?.value || '';
                            const qty  = asInt(tr.querySelector('input.goodalloc-qty')?.value || 0);
                            result.push({ to_rack_id:rack, qty:qty });
                        });
                        return result;
                    }

                    function syncGoodAllocTotal(idx){
                        const wrap = document.getElementById('goodAllocWrap-' + idx);
                        if(!wrap) return;

                        const totalSpan = wrap.querySelector('.goodalloc-total');
                        const allocations = readGoodAllocations(idx);

                        let total = 0;
                        allocations.forEach(a => total += asInt(a.qty || 0));
                        if(totalSpan) totalSpan.textContent = String(total);

                        const row = document.querySelector(`.adj-receive-row[data-index="${idx}"]`);
                        if(row) updateRowStatus(row);
                    }

                    function addGoodAllocRow(idx){
                        const wrap = document.getElementById('goodAllocWrap-' + idx);
                        if(!wrap) return;

                        const tbody = wrap.querySelector('.goodalloc-tbody');
                        const rowCount = tbody.querySelectorAll('tr').length;
                        const whId = getWarehouseId();

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="text-center align-middle">${rowCount+1}</td>
                            <td class="align-middle">
                                <select class="form-control form-control-sm goodalloc-rack"
                                        name="items[${idx}][good_allocations][${rowCount}][to_rack_id]">
                                    ${buildRackOptionsHtml(whId, '')}
                                </select>
                            </td>
                            <td class="align-middle">
                                <input type="number" min="0" step="1"
                                       class="form-control form-control-sm text-center goodalloc-qty"
                                       name="items[${idx}][good_allocations][${rowCount}][qty]"
                                       value="0">
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-sm btn-danger btn-remove-goodalloc"
                                        data-idx="${idx}" data-row="${rowCount}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);

                        rebuildGoodAlloc(idx, false);
                    }

                    function removeGoodAllocRow(idx, rowIndex){
                        const wrap = document.getElementById('goodAllocWrap-' + idx);
                        if(!wrap) return;

                        const tbody = wrap.querySelector('.goodalloc-tbody');
                        const rows = Array.from(tbody.querySelectorAll('tr'));
                        if(rows.length <= 1) return;

                        if(rows[rowIndex]) rows[rowIndex].remove();
                        rebuildGoodAlloc(idx, false);
                    }

                    // ==========================
                    // PER-UNIT TABLES (DEFECT/DAMAGED)
                    // ==========================
                    function ensurePerUnitTablesBuilt(row){
                        const idx = row.dataset.index;
                        const perWrap = document.getElementById('perUnitWrap-' + idx);
                        if(!perWrap) return;

                        const whId = getWarehouseId();

                        const defectInput = row.querySelector('.qty-defect');
                        const damagedInput = row.querySelector('.qty-damaged');

                        const defectCount = asInt(defectInput.value);
                        const damagedCount = asInt(damagedInput.value);

                        const defectTbody = perWrap.querySelector('.defect-tbody');
                        const damagedTbody = perWrap.querySelector('.damaged-tbody');

                        const defectCountText = perWrap.querySelector('.defect-count-text');
                        const damagedCountText = perWrap.querySelector('.damaged-count-text');

                        const snapshotCurrentDomToDataset = () => {
                            const currentDef = [];
                            perWrap.querySelectorAll('.defect-tbody tr').forEach(tr => {
                                currentDef.push({
                                    to_rack_id: tr.querySelector('select.defect-rack-select')?.value || '',
                                    defect_type: tr.querySelector('input.defect-type-input')?.value || '',
                                    defect_description: tr.querySelector('textarea.defect-desc-input')?.value || ''
                                });
                            });

                            const currentDam = [];
                            perWrap.querySelectorAll('.damaged-tbody tr').forEach(tr => {
                                currentDam.push({
                                    to_rack_id: tr.querySelector('select.damaged-rack-select')?.value || '',
                                    damaged_type: tr.querySelector('select.damaged-type-select')?.value || '',
                                    damage_description: tr.querySelector('textarea.damage-desc-input')?.value || ''
                                });
                            });

                            perWrap.dataset.oldDefects = JSON.stringify(currentDef);
                            perWrap.dataset.oldDamages = JSON.stringify(currentDam);
                        };

                        if(perWrap.dataset.hydrated === '1'){
                            snapshotCurrentDomToDataset();
                        }

                        const currentDefRows = defectTbody ? defectTbody.querySelectorAll('tr').length : 0;
                        const currentDamRows = damagedTbody ? damagedTbody.querySelectorAll('tr').length : 0;

                        const needRebuild =
                            (perWrap.dataset.hydrated !== '1') ||
                            (currentDefRows !== defectCount) ||
                            (currentDamRows !== damagedCount);

                        if(!needRebuild){
                            if(defectCountText) defectCountText.textContent = defectCount;
                            if(damagedCountText) damagedCountText.textContent = damagedCount;

                            const btn = row.querySelector('.btn-notes');
                            if(btn){
                                btn.querySelector('.badge-defect-count').textContent = defectCount;
                                btn.querySelector('.badge-damaged-count').textContent = damagedCount;
                            }
                            return;
                        }

                        if(perWrap.dataset.hydrated !== '1'){
                            const oldDefText = perWrap.querySelector('.old-defects-json')?.value || perWrap.querySelector('.old-defects-json')?.textContent || '';
                            const oldDamText = perWrap.querySelector('.old-damages-json')?.value || perWrap.querySelector('.old-damages-json')?.textContent || '';
                            perWrap.dataset.oldDefects = JSON.stringify(safeJsonParse(oldDefText) || []);
                            perWrap.dataset.oldDamages = JSON.stringify(safeJsonParse(oldDamText) || []);
                            perWrap.dataset.hydrated = '1';
                        }

                        const oldDefects = safeJsonParse(perWrap.dataset.oldDefects || '[]');
                        const oldDamages = safeJsonParse(perWrap.dataset.oldDamages || '[]');

                        // DEFECT rows
                        defectTbody.innerHTML = '';
                        for(let i=0;i<defectCount;i++){
                            const prev = oldDefects[i] || {};
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="text-center align-middle">${i+1}</td>
                                <td class="align-middle">
                                    <select class="form-control form-control-sm defect-rack-select"
                                            name="items[${idx}][defects][${i}][to_rack_id]" required>
                                        ${buildRackOptionsHtml(whId, prev.to_rack_id || '')}
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <input type="text" class="form-control form-control-sm defect-type-input"
                                           name="items[${idx}][defects][${i}][defect_type]"
                                           value="${String(prev.defect_type || '').replace(/"/g,'&quot;')}"
                                           placeholder="contoh: bubble / retak ringan"
                                           required>
                                </td>
                                <td class="align-middle">
                                    <textarea class="form-control form-control-sm defect-desc-input"
                                              name="items[${idx}][defects][${i}][defect_description]"
                                              rows="2"
                                              placeholder="keterangan defect (optional)">${String(prev.defect_description || '')}</textarea>
                                </td>
                                <td class="align-middle">
                                    <input type="file" accept="image/*" class="photo-input"
                                           name="items[${idx}][defects][${i}][photo]">
                                    <div class="text-muted mt-1"><small>jpg/png/webp (opsional)</small></div>
                                </td>
                            `;
                            defectTbody.appendChild(tr);
                        }

                        // DAMAGED rows
                        damagedTbody.innerHTML = '';
                        for(let i=0;i<damagedCount;i++){
                            const prev = oldDamages[i] || {};
                            const tr = document.createElement('tr');
                            const prevType = (prev.damaged_type === 'missing') ? 'missing' : 'damaged';

                            tr.innerHTML = `
                                <td class="text-center align-middle">${i+1}</td>
                                <td class="align-middle">
                                    <select class="form-control form-control-sm damaged-rack-select"
                                            name="items[${idx}][damaged_items][${i}][to_rack_id]" required>
                                        ${buildRackOptionsHtml(whId, prev.to_rack_id || '')}
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <select class="form-control form-control-sm damaged-type-select"
                                            name="items[${idx}][damaged_items][${i}][damaged_type]" required>
                                        <option value="damaged" ${prevType==='damaged'?'selected':''}>damaged</option>
                                        <option value="missing" ${prevType==='missing'?'selected':''}>missing</option>
                                    </select>
                                </td>
                                <td class="align-middle">
                                    <textarea class="form-control form-control-sm damage-desc-input"
                                              name="items[${idx}][damaged_items][${i}][damage_description]"
                                              rows="2"
                                              placeholder="wajib isi deskripsi"
                                              required>${String(prev.damage_description || '')}</textarea>
                                </td>
                                <td class="align-middle">
                                    <input type="file" accept="image/*" class="photo-input"
                                           name="items[${idx}][damaged_items][${i}][photo]">
                                    <div class="text-muted mt-1"><small>jpg/png/webp (opsional)</small></div>
                                </td>
                            `;
                            damagedTbody.appendChild(tr);
                        }

                        if(defectCountText) defectCountText.textContent = defectCount;
                        if(damagedCountText) damagedCountText.textContent = damagedCount;

                        const btn = row.querySelector('.btn-notes');
                        if(btn){
                            btn.querySelector('.badge-defect-count').textContent = defectCount;
                            btn.querySelector('.badge-damaged-count').textContent = damagedCount;
                        }

                        perWrap.querySelectorAll('input, textarea, select').forEach(el => {
                            el.addEventListener('input', ()=>{ snapshotCurrentDomToDataset(); updateRowStatus(row); });
                            el.addEventListener('change', ()=>{ snapshotCurrentDomToDataset(); updateRowStatus(row); });
                        });

                        snapshotCurrentDomToDataset();
                    }

                    function toggleSection(targetSelector, show){
                        const el = document.querySelector(targetSelector);
                        if(!el) return;
                        el.style.display = show ? '' : 'none';
                    }

                    // ==========================
                    // VALIDATION STATUS (ADD)
                    // ==========================
                    function updateRowStatus(row){
                        const idx = row.dataset.index;

                        const statusBadge = row.querySelector('.row-status');
                        const hint = row.querySelector('.row-hint');

                        const good   = asInt(row.querySelector('.qty-good').value);
                        const defect = asInt(row.querySelector('.qty-defect').value);
                        const damaged= asInt(row.querySelector('.qty-damaged').value);
                        const total  = good + defect + damaged;

                        // update total badge
                        const totalBadge = row.querySelector('.adj-total-badge');
                        if(totalBadge) totalBadge.textContent = String(total);

                        if(good < 0 || defect < 0 || damaged < 0){
                            statusBadge.className = 'badge badge-danger row-status';
                            statusBadge.textContent = 'INVALID';
                            hint.textContent = 'Qty tidak boleh negatif.';
                            return false;
                        }

                        if(total <= 0){
                            statusBadge.className = 'badge badge-warning row-status';
                            statusBadge.textContent = 'NEED INFO';
                            hint.textContent = 'Isi minimal salah satu qty (Good/Defect/Damaged).';
                            return false;
                        }

                        // GOOD > 0 => allocations sum must equal good, rack for qty>0 required
                        if(good > 0){
                            const allocs = readGoodAllocations(idx);
                            let sumAlloc = 0;
                            for(const a of allocs){
                                const q = asInt(a.qty);
                                sumAlloc += q;
                                if(q > 0 && (!a.to_rack_id || String(a.to_rack_id).trim()==='')){
                                    statusBadge.className = 'badge badge-warning row-status';
                                    statusBadge.textContent = 'NEED INFO';
                                    hint.textContent = 'GOOD > 0: setiap baris allocation yang qty > 0 wajib pilih rack.';
                                    return false;
                                }
                            }
                            if(sumAlloc !== good){
                                statusBadge.className = 'badge badge-warning row-status';
                                statusBadge.textContent = 'NEED INFO';
                                hint.textContent = `Total rack allocation (${sumAlloc}) harus sama dengan GOOD (${good}).`;
                                return false;
                            }
                        }

                        // DEFECT per unit
                        const perWrap = document.getElementById('perUnitWrap-' + idx);
                        if(defect > 0){
                            if(!perWrap){
                                statusBadge.className = 'badge badge-warning row-status';
                                statusBadge.textContent = 'NEED INFO';
                                hint.textContent = 'Defect > 0, tapi per-unit section tidak ditemukan.';
                                return false;
                            }
                            const defectRows = perWrap.querySelectorAll('.defect-tbody tr');
                            if(defectRows.length !== defect){
                                statusBadge.className = 'badge badge-warning row-status';
                                statusBadge.textContent = 'NEED INFO';
                                hint.textContent = `Defect = ${defect}, tapi detail defect belum lengkap.`;
                                return false;
                            }
                            for(let i=0;i<defectRows.length;i++){
                                const rackSel = defectRows[i].querySelector('select.defect-rack-select');
                                const typeInput = defectRows[i].querySelector('input.defect-type-input');
                                if(!rackSel || !rackSel.value){
                                    statusBadge.className = 'badge badge-warning row-status';
                                    statusBadge.textContent = 'NEED INFO';
                                    hint.textContent = 'To Rack wajib dipilih untuk setiap defect unit.';
                                    return false;
                                }
                                if(!typeInput || !typeInput.value.trim()){
                                    statusBadge.className = 'badge badge-warning row-status';
                                    statusBadge.textContent = 'NEED INFO';
                                    hint.textContent = 'Defect Type wajib diisi untuk setiap defect item.';
                                    return false;
                                }
                            }
                        }

                        // DAMAGED per unit
                        if(damaged > 0){
                            if(!perWrap){
                                statusBadge.className = 'badge badge-warning row-status';
                                statusBadge.textContent = 'NEED INFO';
                                hint.textContent = 'Damaged > 0, tapi per-unit section tidak ditemukan.';
                                return false;
                            }
                            const damagedRows = perWrap.querySelectorAll('.damaged-tbody tr');
                            if(damagedRows.length !== damaged){
                                statusBadge.className = 'badge badge-warning row-status';
                                statusBadge.textContent = 'NEED INFO';
                                hint.textContent = `Damaged = ${damaged}, tapi detail damaged belum lengkap.`;
                                return false;
                            }
                            for(let i=0;i<damagedRows.length;i++){
                                const rackSel = damagedRows[i].querySelector('select.damaged-rack-select');
                                const typeSel = damagedRows[i].querySelector('select.damaged-type-select');
                                const descArea= damagedRows[i].querySelector('textarea.damage-desc-input');

                                if(!rackSel || !rackSel.value){
                                    statusBadge.className = 'badge badge-warning row-status';
                                    statusBadge.textContent = 'NEED INFO';
                                    hint.textContent = 'To Rack wajib dipilih untuk setiap damaged unit.';
                                    return false;
                                }
                                if(!typeSel || !typeSel.value){
                                    statusBadge.className = 'badge badge-warning row-status';
                                    statusBadge.textContent = 'NEED INFO';
                                    hint.textContent = 'Damaged Type wajib dipilih.';
                                    return false;
                                }
                                if(!descArea || !descArea.value.trim()){
                                    statusBadge.className = 'badge badge-warning row-status';
                                    statusBadge.textContent = 'NEED INFO';
                                    hint.textContent = 'Damage Description wajib diisi.';
                                    return false;
                                }
                            }
                        }

                        statusBadge.className = 'badge badge-success row-status';
                        statusBadge.textContent = 'OK';
                        hint.textContent = `Total = ${total}`;
                        return true;
                    }

                    function initRow(row){
                        const idx = row.dataset.index;

                        rebuildGoodAlloc(idx, true);
                        syncGoodAllocTotal(idx);

                        ensurePerUnitTablesBuilt(row);
                        updateRowStatus(row);

                        row.querySelectorAll('.qty-input').forEach(el => {
                            el.addEventListener('input', function(){
                                ensurePerUnitTablesBuilt(row);
                                syncGoodAllocTotal(idx);
                                updateRowStatus(row);
                            });
                        });

                        const btnNotes = row.querySelector('.btn-notes');
                        if(btnNotes){
                            btnNotes.addEventListener('click', ()=>{
                                const target = btnNotes.getAttribute('data-target');
                                ensurePerUnitTablesBuilt(row);
                                toggleSection(target, true);
                            });
                        }

                        const btnGood = row.querySelector('.btn-good-rack');
                        if(btnGood){
                            btnGood.addEventListener('click', ()=>{
                                const target = btnGood.getAttribute('data-target');
                                rebuildGoodAlloc(idx, true);
                                toggleSection(target, true);
                            });
                        }
                    }

                    window.validateAllAdjustmentRows = function(){
                        let ok = true;
                        document.querySelectorAll('.adj-receive-row').forEach(row => {
                            ensurePerUnitTablesBuilt(row);
                            const rowOk = updateRowStatus(row);
                            if(!rowOk) ok = false;
                        });
                        return ok;
                    };

                    document.addEventListener('DOMContentLoaded', function(){
                        const whSelect = document.getElementById('warehouse_id_stock');
                        if(whSelect){
                            whSelect.addEventListener('change', ()=>{
                                document.querySelectorAll('.adj-receive-row').forEach(row => {
                                    const idx = row.dataset.index;
                                    rebuildGoodAlloc(idx, false);
                                    ensurePerUnitTablesBuilt(row);
                                    updateRowStatus(row);
                                });
                            });
                        }

                        document.querySelectorAll('.adj-receive-row').forEach(row => initRow(row));

                        document.querySelectorAll('.btn-close-notes').forEach(btn => {
                            btn.addEventListener('click', ()=>{
                                const target = btn.getAttribute('data-target');
                                toggleSection(target, false);
                            });
                        });

                        document.querySelectorAll('.btn-close-goodalloc').forEach(btn => {
                            btn.addEventListener('click', ()=>{
                                const target = btn.getAttribute('data-target');
                                toggleSection(target, false);
                            });
                        });

                        document.querySelectorAll('.btn-add-goodalloc').forEach(btn => {
                            btn.addEventListener('click', ()=>{
                                addGoodAllocRow(asInt(btn.getAttribute('data-idx')));
                            });
                        });
                    });

                    document.addEventListener('livewire:load', function(){
                        try{
                            if(window.Livewire && window.Livewire.hook){
                                window.Livewire.hook('message.processed', function(){
                                    document.querySelectorAll('.adj-receive-row').forEach(row => initRow(row));
                                });
                            }
                        }catch(e){}
                    });
                })();
            </script>
        @endpush

    @endif
</div>
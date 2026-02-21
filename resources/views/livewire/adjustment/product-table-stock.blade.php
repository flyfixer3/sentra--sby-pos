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
                    - Isi qty yang akan ditambahkan per produk: <b>Total = Good + Defect + Damaged</b>.<br>
                    - GOOD bisa dibagi ke beberapa rack (split).<br>
                    - Defect/Damaged dicatat per unit (tiap baris qty = 1) dan tiap unit wajib pilih rack.<br>
                    - Foto opsional, tapi sangat disarankan untuk audit.
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive position-relative">
        <table class="table table-bordered table-modern" id="adjustment-add-table">
            <thead>
            <tr>
                <th style="width: 320px;">Product</th>
                <th class="text-center" style="width: 90px;">Total</th>
                <th class="text-center" style="width: 110px;">Good</th>
                <th class="text-center" style="width: 110px;">Defect</th>
                <th class="text-center" style="width: 110px;">Damaged</th>
                <th class="text-center" style="width: 170px;">Rack Allocation</th>
                <th class="text-center" style="width: 160px;">Per-Unit Notes</th>
                <th class="text-center" style="width: 140px;">Status</th>
                <th class="text-center" style="width: 90px;">Action</th>
            </tr>
            </thead>
            <tbody>
            @if(!empty($products))
                @foreach($products as $idx => $row)
                    @php
                        $pid = (int)($row['id'] ?? 0);
                        $pname = (string)($row['product_name'] ?? '-');
                        $pcode = (string)($row['product_code'] ?? '-');

                        $qtyGood = (int)($row['qty_good'] ?? 0);
                        $qtyDef  = (int)($row['qty_defect'] ?? 0);
                        $qtyDam  = (int)($row['qty_damaged'] ?? 0);
                        $qtyTotal = $qtyGood + $qtyDef + $qtyDam;

                        $stockQty = (int)($row['stock_qty'] ?? 0);

                        $oldDefects = $row['defects'] ?? [];
                        $oldDamaged = $row['damaged_items'] ?? [];
                        $oldGoodAlloc = $row['good_allocations'] ?? [];

                        $defCount = is_array($oldDefects) ? count($oldDefects) : 0;
                        $damCount = is_array($oldDamaged) ? count($oldDamaged) : 0;
                        $gaCount  = is_array($oldGoodAlloc) ? count($oldGoodAlloc) : 0;
                    @endphp

                    {{-- MAIN ROW --}}
                    <tr class="adj-receive-row" data-index="{{ $idx }}" data-product-id="{{ $pid }}">
                        <td>
                            <div class="font-weight-bold">{{ $pname }}</div>
                            <div class="text-muted small">{{ $pcode }}</div>
                            <div class="text-muted small mt-1">Stock: {{ number_format($stockQty) }} Unit</div>

                            {{-- hidden input mapping to form --}}
                            <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $pid }}">
                        </td>

                        <td class="text-center align-middle">
                            <span class="badge badge-pill badge-light border px-2 py-1">
                                <span class="row-total" data-idx="{{ $idx }}">{{ $qtyTotal }}</span>
                            </span>
                        </td>

                        <td class="text-center align-middle">
                            <input type="number"
                                   min="0"
                                   class="form-control form-control-sm text-center qty-input qty-good"
                                   name="items[{{ $idx }}][qty_good]"
                                   value="{{ $qtyGood }}">
                        </td>

                        <td class="text-center align-middle">
                            <input type="number"
                                   min="0"
                                   class="form-control form-control-sm text-center qty-input qty-defect"
                                   name="items[{{ $idx }}][qty_defect]"
                                   value="{{ $qtyDef }}">
                        </td>

                        <td class="text-center align-middle">
                            <input type="number"
                                   min="0"
                                   class="form-control form-control-sm text-center qty-input qty-damaged"
                                   name="items[{{ $idx }}][qty_damaged]"
                                   value="{{ $qtyDam }}">
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
                                <span class="badge badge-primary ml-1 notes-badge-def" data-idx="{{ $idx }}">{{ $defCount }}</span>
                                <span class="badge badge-danger ml-1 notes-badge-dam" data-idx="{{ $idx }}">{{ $damCount }}</span>
                            </button>
                        </td>

                        <td class="text-center align-middle">
                            <span class="badge row-status badge-secondary" data-idx="{{ $idx }}">
                                NEED INFO
                            </span>
                            <div class="text-muted small mt-1 row-status-text" data-idx="{{ $idx }}"></div>
                        </td>

                        <td class="text-center align-middle">
                            <button type="button"
                                    class="btn btn-sm btn-danger"
                                    wire:click="removeProduct({{ $idx }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- GOOD ALLOCATION --}}
                    <tr class="goodalloc-row" id="goodAllocWrap-{{ $idx }}" style="display:none;">
                        <td colspan="9" class="perunit-td">
                            <div class="perunit-card">
                                <div class="perunit-card-header d-flex align-items-center justify-content-between">
                                    <div class="font-weight-bold">
                                        Rack Allocation — GOOD ({{ $pname }})
                                    </div>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary btn-close-goodalloc"
                                            data-target="#goodAllocWrap-{{ $idx }}">
                                        Close
                                    </button>
                                </div>

                                <div class="perunit-card-body">
                                    <div class="mb-2">
                                        <span class="badge badge-success">GOOD</span>
                                        <span class="text-muted small ml-2">Split GOOD ke beberapa rack. Total split harus sama dengan qty GOOD.</span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered section-table mb-0">
                                            <thead>
                                            <tr>
                                                <th style="width: 60px;" class="text-center">#</th>
                                                <th>To Rack *</th>
                                                <th style="width: 120px;" class="text-center">Qty *</th>
                                                <th style="width: 90px;" class="text-center">Action</th>
                                            </tr>
                                            </thead>
                                            <tbody class="goodalloc-tbody"></tbody>
                                            <tfoot>
                                            <tr>
                                                <td colspan="2" class="text-right font-weight-bold">Total</td>
                                                <td class="text-center font-weight-bold">
                                                    <span class="goodalloc-total" data-idx="{{ $idx }}">0</span>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary btn-add-goodalloc"
                                                            data-idx="{{ $idx }}">
                                                        + Add
                                                    </button>
                                                </td>
                                            </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <textarea class="old-goodalloc-json d-none">{{ json_encode($oldGoodAlloc) }}</textarea>
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
                                </div>

                                <div class="perunit-card-body">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="section-title defect-title mb-2">
                                                DEFECT (Qty: <span class="defect-qty" data-idx="{{ $idx }}">0</span>)
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered section-table mb-0 defect-table" data-idx="{{ $idx }}">
                                                    <thead>
                                                    <tr>
                                                        <th style="width:60px" class="text-center">#</th>
                                                        <th>Defect Type *</th>
                                                        <th>Description (optional)</th>
                                                        <th style="width:180px">Photo (optional)</th>
                                                        <th style="width:220px">Rack *</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody class="defect-tbody"></tbody>
                                                </table>
                                            </div>

                                            <textarea class="old-defects-json d-none">{{ json_encode($oldDefects) }}</textarea>
                                        </div>

                                        <div class="col-lg-6">
                                            <div class="section-title damaged-title mb-2">
                                                DAMAGED (Qty: <span class="damaged-qty" data-idx="{{ $idx }}">0</span>)
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered section-table mb-0 damaged-table" data-idx="{{ $idx }}">
                                                    <thead>
                                                    <tr>
                                                        <th style="width:60px" class="text-center">#</th>
                                                        <th>Reason *</th>
                                                        <th>Description (optional)</th>
                                                        <th style="width:180px">Photo (optional)</th>
                                                        <th style="width:220px">Rack *</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody class="damaged-tbody"></tbody>
                                                </table>
                                            </div>

                                            <textarea class="old-damaged-json d-none">{{ json_encode($oldDamaged) }}</textarea>
                                        </div>
                                    </div>

                                    <div class="text-muted mt-2">
                                        <small>
                                            - Defect/Damaged dicatat per unit: qty=1 per baris.<br>
                                            - Rack wajib dipilih untuk setiap unit supaya stock rack tetap konsisten.<br>
                                            - Foto opsional (max 5MB).
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                @endforeach
            @else
                <tr>
                    <td colspan="9" class="text-center text-muted">
                        Please search &amp; select products!
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    {{-- ==========================
        Scripts: toggle + build per-unit tables + validate
       ========================== --}}
    @once
        @push('page_scripts')
        <script>
        (function(){
            // ==========================
            // helpers
            // ==========================
            function asInt(v){
                v = parseInt(v, 10);
                return isNaN(v) ? 0 : v;
            }

            function safeJsonParse(str){
                try{
                    if(!str) return [];
                    const x = JSON.parse(str);
                    return Array.isArray(x) ? x : [];
                }catch(e){
                    return [];
                }
            }

            function getWarehouseId(){
                const el = document.getElementById('warehouse_id_stock');
                if(!el) return null;
                const v = parseInt(el.value, 10);
                return isNaN(v) ? null : v;
            }

            function buildRackOptions(selectedValue){
                const whId = getWarehouseId();
                let html = `<option value="">-- Select Rack --</option>`;
                if(!whId || !window.RACKS_BY_WAREHOUSE || !window.RACKS_BY_WAREHOUSE[whId]) return html;

                window.RACKS_BY_WAREHOUSE[whId].forEach(r => {
                    const sel = (String(r.id) === String(selectedValue)) ? 'selected' : '';
                    html += `<option value="${r.id}" ${sel}>${r.label}</option>`;
                });

                return html;
            }

            function toggleSection(targetSelector, show){
                const el = document.querySelector(targetSelector);
                if(!el) return;
                el.style.display = show ? '' : 'none';
            }

            // ==========================
            // snapshot per-unit ke dataset
            // ==========================
            function snapshotCurrentDomToDataset(perWrap){
                if(!perWrap) return;

                const currentDef = [];
                perWrap.querySelectorAll('tbody.defect-tbody tr').forEach(tr => {
                    const idx = asInt(tr.getAttribute('data-row'));
                    const defect_type = tr.querySelector('input.defect-type')?.value || '';
                    const description = tr.querySelector('textarea.defect-desc')?.value || '';
                    const rack_id = tr.querySelector('select.defect-rack')?.value || '';
                    currentDef.push({
                        row: idx,
                        defect_type: defect_type,
                        description: description,
                        rack_id: rack_id
                    });
                });

                const currentDam = [];
                perWrap.querySelectorAll('tbody.damaged-tbody tr').forEach(tr => {
                    const idx = asInt(tr.getAttribute('data-row'));
                    const reason = tr.querySelector('input.damaged-reason')?.value || '';
                    const description = tr.querySelector('textarea.damaged-desc')?.value || '';
                    const rack_id = tr.querySelector('select.damaged-rack')?.value || '';
                    currentDam.push({
                        row: idx,
                        reason: reason,
                        description: description,
                        rack_id: rack_id
                    });
                });

                perWrap.dataset.defects = JSON.stringify(currentDef);
                perWrap.dataset.damages = JSON.stringify(currentDam);
            }

            // ==========================
            // build per-unit rows by qty
            // ==========================
            function buildDefectRows(idx, qty, old){
                const wrap = document.getElementById('perUnitWrap-' + idx);
                if(!wrap) return;

                const tbody = wrap.querySelector('tbody.defect-tbody');
                if(!tbody) return;

                tbody.innerHTML = '';
                qty = asInt(qty);

                for(let i=0;i<qty;i++){
                    const prev = old.find(x => asInt(x.row) === i) || {};
                    const rackVal = prev.rack_id || '';
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr data-row="${i}">
                            <td class="text-center">${i+1}</td>
                            <td>
                                <input type="text"
                                       class="form-control form-control-sm defect-type"
                                       name="items[${idx}][defects][${i}][defect_type]"
                                       value="${(prev.defect_type || '').replace(/"/g,'&quot;')}"
                                       placeholder="bubble / scratch"
                                       required>
                            </td>
                            <td>
                                <textarea class="form-control form-control-sm defect-desc"
                                          name="items[${idx}][defects][${i}][defect_description]">${(prev.description || '')}</textarea>
                            </td>
                            <td>
                                <input type="file"
                                       class="form-control form-control-sm photo-input"
                                       name="items[${idx}][defects][${i}][photo]">
                            </td>
                            <td>
                                <select class="form-control form-control-sm defect-rack"
                                        name="items[${idx}][defects][${i}][to_rack_id]"
                                        required>
                                    ${buildRackOptions(rackVal)}
                                </select>
                            </td>
                        </tr>
                    `);
                }
            }

            function buildDamagedRows(idx, qty, old){
                const wrap = document.getElementById('perUnitWrap-' + idx);
                if(!wrap) return;

                const tbody = wrap.querySelector('tbody.damaged-tbody');
                if(!tbody) return;

                tbody.innerHTML = '';
                qty = asInt(qty);

                for(let i=0;i<qty;i++){
                    const prev = old.find(x => asInt(x.row) === i) || {};
                    const rackVal = prev.rack_id || '';
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr data-row="${i}">
                            <td class="text-center">${i+1}</td>
                            <td>
                                <input type="text"
                                       class="form-control form-control-sm damaged-reason"
                                       name="items[${idx}][damaged_items][${i}][damage_description]"
                                       value="${(prev.reason || '').replace(/"/g,'&quot;')}"
                                       placeholder="pecah sudut"
                                       required>
                            </td>
                            <td>
                                <textarea class="form-control form-control-sm damaged-desc"
                                          name="items[${idx}][damaged_items][${i}][description]">${(prev.description || '')}</textarea>
                            </td>
                            <td>
                                <input type="file"
                                       class="form-control form-control-sm photo-input"
                                       name="items[${idx}][damaged_items][${i}][photo]">
                            </td>
                            <td>
                                <select class="form-control form-control-sm damaged-rack"
                                        name="items[${idx}][damaged_items][${i}][to_rack_id]"
                                        required>
                                    ${buildRackOptions(rackVal)}
                                </select>
                            </td>
                        </tr>
                    `);
                }
            }

            function ensurePerUnitTablesBuilt(row){
                const idx = row.dataset.index;
                const defQty = asInt(row.querySelector('input.qty-defect')?.value);
                const damQty = asInt(row.querySelector('input.qty-damaged')?.value);

                const perWrap = document.getElementById('perUnitWrap-' + idx);
                if(!perWrap) return;

                let oldDef = [];
                let oldDam = [];

                // jika sudah pernah hydrate, ambil dari dataset snapshot
                if(perWrap.dataset.hydrated === '1'){
                    oldDef = safeJsonParse(perWrap.dataset.defects || '[]');
                    oldDam = safeJsonParse(perWrap.dataset.damages || '[]');
                }else{
                    // first load dari textarea hidden
                    oldDef = safeJsonParse(perWrap.querySelector('.old-defects-json')?.value || perWrap.querySelector('.old-defects-json')?.textContent || '');
                    oldDam = safeJsonParse(perWrap.querySelector('.old-damaged-json')?.value || perWrap.querySelector('.old-damaged-json')?.textContent || '');
                    // normalisasi key
                    oldDef = oldDef.map((x, i) => ({
                        row: i,
                        defect_type: x.defect_type || x.type || x.defectType || '',
                        description: x.defect_description || x.description || '',
                        rack_id: x.to_rack_id || x.rack_id || ''
                    }));
                    oldDam = oldDam.map((x, i) => ({
                        row: i,
                        reason: x.damage_description || x.reason || '',
                        description: x.description || '',
                        rack_id: x.to_rack_id || x.rack_id || ''
                    }));
                }

                // update badge qty in header
                const dq = document.querySelector('.defect-qty[data-idx="'+idx+'"]');
                const mq = document.querySelector('.damaged-qty[data-idx="'+idx+'"]');
                if(dq) dq.textContent = defQty;
                if(mq) mq.textContent = damQty;

                // build rows
                buildDefectRows(idx, defQty, oldDef);
                buildDamagedRows(idx, damQty, oldDam);

                // mark hydrated and snapshot
                perWrap.dataset.hydrated = '1';
                snapshotCurrentDomToDataset(perWrap);

                // update badges
                const bd = document.querySelector('.notes-badge-def[data-idx="'+idx+'"]');
                const bm = document.querySelector('.notes-badge-dam[data-idx="'+idx+'"]');
                if(bd) bd.textContent = defQty;
                if(bm) bm.textContent = damQty;
            }

            // ==========================
            // GOOD allocations
            // ==========================
            function rebuildGoodAlloc(idx, keepOld=true){
                const whId = getWarehouseId();
                const wrap = document.getElementById('goodAllocWrap-' + idx);
                if(!wrap) return;

                const tbody = wrap.querySelector('.goodalloc-tbody');
                let allocations = [];

                if(keepOld){
                    const oldText =
                        wrap.querySelector('.old-goodalloc-json')?.value ||
                        wrap.querySelector('.old-goodalloc-json')?.textContent ||
                        '';
                    allocations = safeJsonParse(oldText);
                }

                if(!Array.isArray(allocations) || allocations.length === 0){
                    allocations = [{ to_rack_id:'', qty:0 }];
                }

                tbody.innerHTML = '';

                allocations.forEach((a, rowNo) => {
                    const rackVal = a.to_rack_id || '';
                    const qtyVal  = asInt(a.qty || 0);

                    tbody.insertAdjacentHTML('beforeend', `
                        <tr data-row="${rowNo}">
                            <td class="text-center">${rowNo+1}</td>
                            <td>
                                <select class="form-control form-control-sm goodalloc-rack"
                                        name="items[${idx}][good_allocations][${rowNo}][to_rack_id]"
                                        required>
                                    ${buildRackOptions(rackVal)}
                                </select>
                            </td>
                            <td class="text-center">
                                <input type="number"
                                       min="0"
                                       class="form-control form-control-sm text-center goodalloc-qty"
                                       name="items[${idx}][good_allocations][${rowNo}][qty]"
                                       value="${qtyVal}">
                            </td>
                            <td class="text-center">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger btn-remove-goodalloc"
                                        data-idx="${idx}"
                                        data-row="${rowNo}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
            }

            function syncGoodAllocTotal(idx){
                const row = document.querySelector('.adj-receive-row[data-index="'+idx+'"]');
                const wrap = document.getElementById('goodAllocWrap-' + idx);
                if(!row || !wrap) return;

                const goodQty = asInt(row.querySelector('input.qty-good')?.value);
                let sum = 0;
                wrap.querySelectorAll('input.goodalloc-qty').forEach(inp => {
                    sum += asInt(inp.value);
                });

                const totalEl = document.querySelector('.goodalloc-total[data-idx="'+idx+'"]');
                if(totalEl) totalEl.textContent = sum;

                // update status
                updateRowStatus(row);
            }

            function addGoodAllocRow(idx){
                const wrap = document.getElementById('goodAllocWrap-' + idx);
                if(!wrap) return;

                const oldText = wrap.querySelector('.old-goodalloc-json')?.value || wrap.querySelector('.old-goodalloc-json')?.textContent || '';
                let allocations = safeJsonParse(oldText);

                if(!Array.isArray(allocations)) allocations = [];
                allocations.push({ to_rack_id:'', qty:0 });

                wrap.querySelector('.old-goodalloc-json').value = JSON.stringify(allocations);
                rebuildGoodAlloc(idx, true);
                syncGoodAllocTotal(idx);
            }

            function removeGoodAllocRow(idx, rowNo){
                const wrap = document.getElementById('goodAllocWrap-' + idx);
                if(!wrap) return;

                const oldText = wrap.querySelector('.old-goodalloc-json')?.value || wrap.querySelector('.old-goodalloc-json')?.textContent || '';
                let allocations = safeJsonParse(oldText);

                allocations = allocations.filter((_, i) => i !== rowNo);
                if(allocations.length === 0){
                    allocations = [{ to_rack_id:'', qty:0 }];
                }

                wrap.querySelector('.old-goodalloc-json').value = JSON.stringify(allocations);
                rebuildGoodAlloc(idx, true);
                syncGoodAllocTotal(idx);
            }

            // ==========================
            // Status rules
            // ==========================
            function updateRowStatus(row){
                if(!row) return;

                const idx = row.dataset.index;

                const good = asInt(row.querySelector('input.qty-good')?.value);
                const def  = asInt(row.querySelector('input.qty-defect')?.value);
                const dam  = asInt(row.querySelector('input.qty-damaged')?.value);

                const total = good + def + dam;

                // update total badge
                const totalEl = row.querySelector('.row-total[data-idx="'+idx+'"]');
                if(totalEl) totalEl.textContent = total;

                // compute
                let ok = true;
                let msg = [];

                if(total <= 0){
                    ok = false;
                    msg.push('Isi minimal salah satu qty (Good/Defect/Damaged).');
                }

                // good split must match good qty (if good > 0)
                if(good > 0){
                    const wrap = document.getElementById('goodAllocWrap-' + idx);
                    let sum = 0;
                    if(wrap){
                        wrap.querySelectorAll('input.goodalloc-qty').forEach(inp => sum += asInt(inp.value));
                    }
                    if(sum !== good){
                        ok = false;
                        msg.push(`Good split total (${sum}) harus sama dengan Good (${good}).`);
                    }

                    // require rack for each allocation row where qty>0
                    if(wrap){
                        wrap.querySelectorAll('tbody.goodalloc-tbody tr').forEach(tr => {
                            const qtyVal = asInt(tr.querySelector('input.goodalloc-qty')?.value);
                            const rackVal = tr.querySelector('select.goodalloc-rack')?.value || '';
                            if(qtyVal > 0 && String(rackVal).trim() === ''){
                                ok = false;
                                msg.push('GOOD allocation: Rack wajib dipilih jika qty > 0.');
                            }
                        });
                    }
                }

                // per-unit must have rack if defect/damaged > 0
                if(def > 0 || dam > 0){
                    const perWrap = document.getElementById('perUnitWrap-' + idx);
                    if(perWrap && perWrap.dataset.hydrated === '1'){
                        // validate each row
                        perWrap.querySelectorAll('tbody.defect-tbody tr').forEach(tr => {
                            const rackVal = tr.querySelector('select.defect-rack')?.value || '';
                            const typeVal = tr.querySelector('input.defect-type')?.value || '';
                            if(def > 0){
                                if(String(typeVal).trim() === ''){
                                    ok = false;
                                    msg.push('DEFECT: Defect Type wajib diisi per unit.');
                                }
                                if(String(rackVal).trim() === ''){
                                    ok = false;
                                    msg.push('DEFECT: Rack wajib dipilih per unit.');
                                }
                            }
                        });

                        perWrap.querySelectorAll('tbody.damaged-tbody tr').forEach(tr => {
                            const rackVal = tr.querySelector('select.damaged-rack')?.value || '';
                            const reasonVal = tr.querySelector('input.damaged-reason')?.value || '';
                            if(dam > 0){
                                if(String(reasonVal).trim() === ''){
                                    ok = false;
                                    msg.push('DAMAGED: Reason wajib diisi per unit.');
                                }
                                if(String(rackVal).trim() === ''){
                                    ok = false;
                                    msg.push('DAMAGED: Rack wajib dipilih per unit.');
                                }
                            }
                        });
                    }else{
                        ok = false;
                        msg.push('Isi Per-Unit Notes untuk Defect/Damaged.');
                    }
                }

                const badge = row.querySelector('.row-status[data-idx="'+idx+'"]');
                const text  = row.querySelector('.row-status-text[data-idx="'+idx+'"]');

                if(badge){
                    badge.classList.remove('badge-success','badge-danger','badge-warning','badge-secondary');
                    badge.classList.add(ok ? 'badge-success' : 'badge-warning');
                    badge.textContent = ok ? 'OK' : 'NEED INFO';
                }
                if(text){
                    text.textContent = ok ? '' : msg[0] || '';
                }
            }

            function initRow(row){
                if(!row) return;

                const idx = row.dataset.index;

                // rebuild good alloc based on stored json
                rebuildGoodAlloc(idx, true);
                syncGoodAllocTotal(idx);

                // initial status
                updateRowStatus(row);
            }

            function initAllRows(){
                document.querySelectorAll('tr.adj-receive-row').forEach(r => initRow(r));
            }

            // expose validate function to window (dipakai create.blade.php)
            window.validateAllAdjustmentRows = function(){
                let ok = true;
                document.querySelectorAll('tr.adj-receive-row').forEach(row => {
                    updateRowStatus(row);
                    const badge = row.querySelector('.row-status');
                    if(badge && badge.textContent.trim() !== 'OK'){
                        ok = false;
                    }
                });
                return ok;
            };

            // ==========================
            // events (delegation)
            // ==========================
            function bindDelegation(){
                // ✅ guard: jangan bind berkali-kali (Livewire re-render)
                if(window.__adj_stock_bindDelegation === true) return;
                window.__adj_stock_bindDelegation = true;

                const table = document.getElementById('adjustment-add-table');
                if(!table) return;

                table.addEventListener('input', function(e){
                    const t = e.target;

                    // cari row dulu (fix scope)
                    const row = t.closest('tr.adj-receive-row') ||
                        (t.closest('tr.goodalloc-row')
                            ? document.querySelector(`.adj-receive-row[data-index="${t.closest('tr.goodalloc-row').id.replace('goodAllocWrap-','')}"]`)
                            : null);

                    if(row){
                        const perWrap = document.getElementById('perUnitWrap-' + row.dataset.index);
                        if(perWrap && perWrap.dataset.hydrated === '1'){
                            snapshotCurrentDomToDataset(perWrap);
                        }
                    }

                    if(t.classList && t.classList.contains('qty-input')){
                        if(!row) return;
                        ensurePerUnitTablesBuilt(row);
                        syncGoodAllocTotal(row.dataset.index);
                        updateRowStatus(row);
                        return;
                    }

                    if(t.classList && (t.classList.contains('goodalloc-qty') || t.classList.contains('goodalloc-rack'))){
                        if(!row) return;
                        // sync total + status
                        const idx = row.dataset.index;
                        // update stored json in hidden textarea (so server side has it)
                        const wrap = document.getElementById('goodAllocWrap-' + idx);
                        if(wrap){
                            const alloc = [];
                            wrap.querySelectorAll('tbody.goodalloc-tbody tr').forEach(tr => {
                                alloc.push({
                                    to_rack_id: tr.querySelector('select.goodalloc-rack')?.value || '',
                                    qty: asInt(tr.querySelector('input.goodalloc-qty')?.value)
                                });
                            });
                            wrap.querySelector('.old-goodalloc-json').value = JSON.stringify(alloc);
                        }
                        syncGoodAllocTotal(idx);
                        return;
                    }

                    if(t.classList && (t.classList.contains('defect-type') || t.classList.contains('defect-desc') || t.classList.contains('defect-rack') ||
                        t.classList.contains('damaged-reason') || t.classList.contains('damaged-desc') || t.classList.contains('damaged-rack'))){
                        if(!row) return;
                        const perWrap = document.getElementById('perUnitWrap-' + row.dataset.index);
                        if(perWrap){
                            snapshotCurrentDomToDataset(perWrap);
                        }
                        updateRowStatus(row);
                        return;
                    }
                });

                document.addEventListener('click', function(e){
                    const btn = e.target.closest('button');
                    if(!btn) return;

                    if(btn.classList.contains('btn-notes')){
                        const row = btn.closest('tr.adj-receive-row');
                        if(!row) return;
                        ensurePerUnitTablesBuilt(row);
                        toggleSection(btn.getAttribute('data-target'), true);
                        updateRowStatus(row);
                        return;
                    }

                    if(btn.classList.contains('btn-good-rack')){
                        const row = btn.closest('tr.adj-receive-row');
                        if(!row) return;
                        rebuildGoodAlloc(row.dataset.index, true);
                        syncGoodAllocTotal(row.dataset.index);
                        toggleSection(btn.getAttribute('data-target'), true);
                        return;
                    }

                    if(btn.classList.contains('btn-close-notes') || btn.classList.contains('btn-close-goodalloc')){
                        toggleSection(btn.getAttribute('data-target'), false);
                        return;
                    }

                    if(btn.classList.contains('btn-add-goodalloc')){
                        addGoodAllocRow(asInt(btn.getAttribute('data-idx')));
                        return;
                    }

                    if(btn.classList.contains('btn-remove-goodalloc')){
                        removeGoodAllocRow(asInt(btn.getAttribute('data-idx')), asInt(btn.getAttribute('data-row')));
                        return;
                    }
                });

                const wh = document.getElementById('warehouse_id_stock');
                if(wh){
                    wh.addEventListener('change', function(){
                        initAllRows();
                    });
                }
            }

            // ==========================
            // BOOTSTRAP INIT (tahan timing DOMContentLoaded / livewire:load)
            // ==========================
            function boot(){
                bindDelegation();
                initAllRows();
            }

            // Kalau script ini kebetulan dieksekusi setelah DOMContentLoaded,
            // listener DOMContentLoaded tidak akan kepanggil. Jadi kita handle dua kondisi.
            if(document.readyState === 'loading'){
                document.addEventListener('DOMContentLoaded', function(){
                    boot();
                });
            }else{
                boot();
            }

            // Livewire re-render: pastikan recalculation jalan terus.
            // - Kalau Livewire sudah siap duluan, hook bisa langsung dipakai.
            // - Kalau belum, kita pasang via event livewire:load.
            function registerLivewireHook(){
                if(window.Livewire && typeof window.Livewire.hook === 'function'){
                    window.Livewire.hook('message.processed', function(){
                        // jangan bind ulang event delegation (sudah di-guard)
                        initAllRows();
                    });
                }
            }

            registerLivewireHook();
            document.addEventListener('livewire:load', function(){
                registerLivewireHook();
                // safety: setelah livewire siap, re-init sekali lagi
                initAllRows();
            });

        })();
        </script>
        @endpush
    @endonce
</div>
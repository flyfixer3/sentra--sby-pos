<div>

    <div class="mb-3">
        <div class="d-flex align-items-start justify-content-between">
            <div>
                <div class="font-weight-bold">Stock SUB (Reduce Stock)</div>
                <div class="text-muted small">
                    Flow: Pick Items (Warehouse/Rack/Condition) → Auto total selected must match Expected.
                </div>
            </div>

            <span class="badge badge-light border px-3 py-2">
                <i class="bi bi-arrow-down-circle"></i> Mode: SUB
            </span>
        </div>
    </div>

    {{-- Step cards --}}
    <div class="row mb-3">
        <div class="col-lg-4 mb-2">
            <div class="border rounded p-3 bg-white">
                <div class="d-flex align-items-center">
                    <span class="badge badge-pill badge-light border mr-2">1</span>
                    <div>
                        <div class="font-weight-bold">Pick Items per Product</div>
                        <div class="text-muted small">Set Warehouse/Rack/Condition di modal.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-2">
            <div class="border rounded p-3 bg-white">
                <div class="d-flex align-items-center">
                    <span class="badge badge-pill badge-light border mr-2">2</span>
                    <div>
                        <div class="font-weight-bold">GOOD pakai Qty</div>
                        <div class="text-muted small">GOOD bisa &gt; 1 (input qty, max = stock).</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-2">
            <div class="border rounded p-3 bg-white">
                <div class="d-flex align-items-center">
                    <span class="badge badge-pill badge-light border mr-2">3</span>
                    <div>
                        <div class="font-weight-bold">DEFECT/DAMAGED 1 PC</div>
                        <div class="text-muted small">Pick ID (tiap ID = 1 pc).</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Rules --}}
    <div class="alert alert-info border">
        <div class="font-weight-bold mb-1">
            <i class="bi bi-info-circle"></i> Rules
        </div>
        <ul class="mb-0 small">
            <li>Total selected (GOOD qty + DEFECT IDs + DAMAGED IDs) wajib sama dengan Expected.</li>
            <li>GOOD: input qty per rack (allocation otomatis terbentuk).</li>
            <li>DEFECT/DAMAGED: pilih unit ID (1 ID = 1 pc).</li>
            <li>Warehouse dropdown di modal hanya untuk filter / fokus (optional).</li>
        </ul>
    </div>

    {{-- Alert global kalau ada invalid --}}
    <div class="alert alert-danger border mt-3" style="display:none;" id="subGlobalInvalidAlert">
        <div class="font-weight-bold mb-1">
            <i class="bi bi-exclamation-triangle"></i> Masih ada item yang belum valid.
        </div>
        <div class="small text-muted">Buka "Pick Items" per item, lalu pastikan total selected sama dengan Expected.</div>
    </div>

    {{-- LIST PRODUCT --}}
    <div class="mt-3">
        @if(empty($products))
            <div class="alert alert-light border">
                <div class="font-weight-bold mb-1">No product selected</div>
                <div class="text-muted small">Pilih product dari Search Product di atas untuk mulai.</div>
            </div>
        @else
            @foreach($products as $idx => $p)
                @php
                    $expected = (int) ($p['expected_qty'] ?? 1);
                    $sel = $selections[$idx] ?? [
                        'good_allocations' => [],
                        'defect_ids' => [],
                        'damaged_ids' => [],
                        'note' => '',
                    ];

                    $goodTotal = 0;
                    foreach(($sel['good_allocations'] ?? []) as $ga){
                        $goodTotal += (int) ($ga['qty'] ?? 0);
                    }

                    $defTotal = is_array($sel['defect_ids'] ?? null) ? count($sel['defect_ids']) : 0;
                    $damTotal = is_array($sel['damaged_ids'] ?? null) ? count($sel['damaged_ids']) : 0;
                    $totalSelected = $goodTotal + $defTotal + $damTotal;

                    $isValid = ($totalSelected === $expected) && trim((string)($sel['note'] ?? '')) !== '';
                @endphp

                <div class="card mb-3 {{ $isValid ? 'border' : 'border-danger' }}" data-sub-row="{{ $idx }}">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <div class="font-weight-bold">
                                    {{ $p['product_name'] ?? 'Product' }}
                                </div>
                                <div class="text-muted small">
                                    Code: {{ $p['product_code'] ?? '-' }}
                                    &nbsp; <span class="text-muted">|</span> &nbsp;
                                    product_id: {{ (int) ($p['product_id'] ?? 0) }}
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-muted small mb-1">Expected</div>
                                <span class="badge badge-secondary px-3 py-2">{{ $expected }}</span>
                            </div>
                        </div>

                        <hr>

                        {{-- badges summary --}}
                        <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                            <span class="badge badge-light border">Selected: <b>{{ $totalSelected }}</b> / {{ $expected }}</span>
                            <span class="badge badge-light border">GOOD: <b>{{ $goodTotal }}</b></span>
                            <span class="badge badge-light border">DEFECT: <b>{{ $defTotal }}</b> (1 pc/ID)</span>
                            <span class="badge badge-light border">DAMAGED: <b>{{ $damTotal }}</b> (1 pc/ID)</span>
                        </div>

                        <div class="row mt-3">
                            <div class="col-lg-8">
                                {{-- Preview allocations --}}
                                <div class="small text-muted mb-1">GOOD Allocations (auto):</div>
                                <div class="small">
                                    @if($goodTotal <= 0)
                                        <div class="text-muted">-</div>
                                    @else
                                        @foreach(($sel['good_allocations'] ?? []) as $ga)
                                            <div>
                                                - WH: <b>{{ $ga['warehouse_label'] ?? ('#'.(int)($ga['warehouse_id'] ?? 0)) }}</b>
                                                | Rack: <b>{{ $ga['rack_label'] ?? ('#'.(int)($ga['from_rack_id'] ?? 0)) }}</b>
                                                | Qty: <b>{{ (int)($ga['qty'] ?? 0) }}</b>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                                <div class="small text-muted mt-2 mb-1">Selected DEFECT IDs:</div>
                                <div class="small">
                                    @if($defTotal <= 0)
                                        <div class="text-muted">-</div>
                                    @else
                                        <div>{{ implode(', ', array_map('intval', $sel['defect_ids'])) }}</div>
                                    @endif
                                </div>

                                <div class="small text-muted mt-2 mb-1">Selected DAMAGED IDs:</div>
                                <div class="small">
                                    @if($damTotal <= 0)
                                        <div class="text-muted">-</div>
                                    @else
                                        <div>{{ implode(', ', array_map('intval', $sel['damaged_ids'])) }}</div>
                                    @endif
                                </div>

                                <div class="mt-3">
                                    <label class="font-weight-bold mb-1">Item Note <span class="text-danger">*</span></label>
                                    <textarea
                                        class="form-control"
                                        rows="2"
                                        placeholder="Wajib isi alasan / catatan untuk SUB item ini..."
                                        wire:model.defer="selections.{{ $idx }}.note"
                                        data-note="{{ $idx }}"
                                    ></textarea>
                                    <small class="text-muted">Catatan ini masuk ke mutation note & tampil di detail adjustment.</small>
                                </div>
                            </div>

                            <div class="col-lg-4 d-flex flex-column justify-content-between">
                                <div class="mb-2">
                                    @if($isValid)
                                        <span class="badge badge-success row-status px-3 py-2">OK</span>
                                    @else
                                        <span class="badge badge-danger row-status px-3 py-2">Qty mismatch / Need note</span>
                                    @endif
                                </div>

                                <button type="button"
                                        class="btn btn-secondary"
                                        onclick="openSubPickModal({{ $idx }}, {{ (int)($p['product_id'] ?? 0) }})">
                                    <i class="bi bi-grid"></i> Pick Items
                                </button>
                            </div>
                        </div>

                        {{-- Hidden inputs injected for form submission --}}
                        <div data-hidden-wrap="{{ $idx }}"></div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- =========================
        MODAL PICK ITEMS (SUB)
    ========================== --}}
    <div class="modal fade" id="subPickModal" tabindex="-1" role="dialog" aria-labelledby="subPickModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content" style="border-radius:12px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold" id="subPickModalLabel">Pick Items</h5>
                        <div class="text-muted small">
                            Filter berdasarkan <b>Warehouse / Rack / Condition</b>.
                            <span class="ml-2 badge badge-light border">GOOD: input qty</span>
                            <span class="ml-1 badge badge-light border">DEFECT: 1 pc / ID</span>
                            <span class="ml-1 badge badge-light border">DAMAGED: 1 pc / ID</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center" style="gap:10px;">
                        <span class="badge badge-light border px-3 py-2" id="subModalSelectedCounter">Selected 0 / 0</span>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>

                <div class="modal-body">
                    {{-- filters --}}
                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="row">
                            <div class="col-lg-4 mb-2">
                                <label class="small text-muted mb-1">Warehouse</label>
                                <select class="form-control" id="subFilterWarehouse">
                                    <option value="">All warehouse</option>
                                    @foreach($warehouseOptions as $w)
                                        <option value="{{ (int)$w['id'] }}">{{ $w['label'] }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Warehouse ini hanya untuk filter (optional).</small>
                            </div>

                            <div class="col-lg-4 mb-2">
                                <label class="small text-muted mb-1">Racks</label>
                                <select class="form-control" id="subFilterRack">
                                    <option value="">All Racks</option>
                                </select>
                                <small class="text-muted">Rack filter aktif jika warehouse dipilih.</small>
                            </div>

                            <div class="col-lg-4 mb-2">
                                <label class="small text-muted mb-1">Conditions</label>
                                <select class="form-control" id="subFilterCondition">
                                    <option value="">All Conditions</option>
                                    <option value="good">GOOD</option>
                                    <option value="defect">DEFECT</option>
                                    <option value="damaged">DAMAGED</option>
                                </select>
                                <div class="mt-2 d-flex justify-content-end" style="gap:8px;">
                                    <button type="button" class="btn btn-light border" onclick="resetSubFilters()">Reset</button>
                                    <button type="button" class="btn btn-primary" onclick="applySubFilters()">Apply</button>
                                </div>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">
                            Required Total: <b id="subRequiredTotal">0</b>
                            &nbsp; • &nbsp; GOOD: <b id="subGoodTotal">0</b>
                            &nbsp; • &nbsp; DEFECT: <b id="subDefTotal">0</b>
                            &nbsp; • &nbsp; DAMAGED: <b id="subDamTotal">0</b>
                        </div>
                    </div>

                    {{-- list container --}}
                    <div id="subPickListWrap">
                        <div class="text-muted">Loading...</div>
                    </div>

                    <div class="text-muted small mt-2">
                        * Save akan menyimpan pilihan ke card.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-secondary" onclick="saveSubSelection()">Save Selection</button>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================
        JS (SUB PICK MODAL)
    ========================== --}}
    <script>
        (function(){
            // ====== endpoints ======
            // kamu bisa ganti URL ini sesuai route kamu:
            // contoh ideal: route('adjustments.stockSubPickerData')
            const SUB_PICK_URL = @json($stockSubPickerUrl);

            // ====== runtime state ======
            let currentRowIndex = null;
            let currentProductId = null;
            let currentExpected = 0;

            // selections inside modal
            let modalGoodAlloc = []; // [{warehouse_id, warehouse_label, from_rack_id, rack_label, qty}]
            let modalDefectIds = []; // [id]
            let modalDamagedIds = []; // [id]

            // data cache by warehouse
            let cache = {}; // wid => {racks, stock_by_rack, defect_units, damaged_units, wh_label}
            let activeWarehouseId = ''; // filter
            let activeRackId = '';
            let activeCond = '';

            // ====== helpers ======
            function uniqInt(arr){
                arr = Array.isArray(arr) ? arr : [];
                const s = new Set();
                arr.forEach(x => {
                    const v = parseInt(x || 0);
                    if(v > 0) s.add(v);
                });
                return Array.from(s.values());
            }

            function sumGoodAlloc(){
                let t = 0;
                (modalGoodAlloc||[]).forEach(x => t += parseInt(x.qty||0));
                return t;
            }

            function updateCounters(){
                const good = sumGoodAlloc();
                const defc = (modalDefectIds||[]).length;
                const damg = (modalDamagedIds||[]).length;
                const total = good + defc + damg;

                document.getElementById('subRequiredTotal').innerText = currentExpected;
                document.getElementById('subGoodTotal').innerText = good;
                document.getElementById('subDefTotal').innerText = defc;
                document.getElementById('subDamTotal').innerText = damg;

                const el = document.getElementById('subModalSelectedCounter');
                if(el) el.innerText = `Selected ${total} / ${currentExpected}`;
            }

            function buildRackOptions(wid){
                const rackSel = document.getElementById('subFilterRack');
                rackSel.innerHTML = `<option value="">All Racks</option>`;

                if(!wid) return;
                const d = cache[wid];
                if(!d || !Array.isArray(d.racks)) return;

                d.racks.forEach(r => {
                    rackSel.insertAdjacentHTML('beforeend',
                        `<option value="${r.id}">${escapeHtml(r.label)}</option>`
                    );
                });
            }

            function escapeHtml(str){
                str = (str ?? '').toString();
                return str
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function fetchWarehouseData(wid){
                if(!wid) return null;
                if(cache[wid]) return cache[wid];

                const url = new URL(SUB_PICK_URL, window.location.origin);
                url.searchParams.set('warehouse_id', wid);
                url.searchParams.set('product_id', currentProductId);

                const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const json = await res.json();

                if(!json || !json.success){
                    throw new Error(json?.message || 'Failed to load picker data.');
                }

                const data = json.data || {};
                cache[wid] = {
                    wh_id: parseInt(wid),
                    racks: data.racks || [],
                    stock_by_rack: data.stock_by_rack || {},
                    defect_units: data.defect_units || [],
                    damaged_units: data.damaged_units || [],
                };

                return cache[wid];
            }

            function renderList(){
                const wrap = document.getElementById('subPickListWrap');
                if(!wrap) return;

                // decide warehouses to render:
                const wids = [];
                const whSel = document.getElementById('subFilterWarehouse').value || '';
                if(whSel){
                    wids.push(whSel);
                }else{
                    // all from dropdown options
                    const opts = document.querySelectorAll('#subFilterWarehouse option');
                    opts.forEach(o => {
                        const v = o.value;
                        if(v) wids.push(v);
                    });
                }

                // build UI
                let html = '';
                html += `<div class="small text-muted mb-2">List item sesuai filter (GOOD pakai qty, DEFECT/DAMAGED 1pc per ID).</div>`;

                if(wids.length <= 0){
                    html += `<div class="alert alert-light border">No warehouse option.</div>`;
                    wrap.innerHTML = html;
                    return;
                }

                // render per warehouse section
                wids.forEach(wid => {
                    const d = cache[wid];
                    if(!d){
                        html += `<div class="mb-3"><b>Warehouse #${wid}</b> <span class="text-muted">(not loaded)</span></div>`;
                        return;
                    }

                    const whLabel = document.querySelector(`#subFilterWarehouse option[value="${wid}"]`)?.textContent || `Warehouse #${wid}`;

                    html += `
                        <div class="border rounded mb-3 bg-white">
                            <div class="px-3 py-2 border-bottom font-weight-bold">
                                ${escapeHtml(whLabel)}
                            </div>
                            <div class="p-2">
                                ${renderRowsForWarehouse(wid, d)}
                            </div>
                        </div>
                    `;
                });

                wrap.innerHTML = html;

                // bind inputs
                bindDynamicEvents();
                updateCounters();
            }

            function renderRowsForWarehouse(wid, d){
                const rackFilter = document.getElementById('subFilterRack').value || '';
                const condFilter = document.getElementById('subFilterCondition').value || '';

                // build rack label map
                const rackMap = {};
                (d.racks||[]).forEach(r => rackMap[r.id] = r.label);

                let rowsHtml = '';

                // build rack IDs present in stock_by_rack
                const stockByRack = d.stock_by_rack || {};
                const rackIds = Object.keys(stockByRack).map(x => parseInt(x)).filter(x => x > 0);

                // If no rack stock but there are defect/damaged units, we still want show racks from units
                const racksFromDef = (d.defect_units||[]).map(u => parseInt(u.rack_id||0)).filter(x => x>0);
                const racksFromDam = (d.damaged_units||[]).map(u => parseInt(u.rack_id||0)).filter(x => x>0);

                const allRackIds = Array.from(new Set([...rackIds, ...racksFromDef, ...racksFromDam])).sort((a,b)=>a-b);

                if(allRackIds.length <= 0){
                    return `<div class="text-muted px-2 py-2">No stock found.</div>`;
                }

                allRackIds.forEach(rid => {
                    if(rackFilter && parseInt(rackFilter) !== rid) return;

                    const rackLabel = rackMap[rid] || `Rack #${rid}`;

                    const stock = stockByRack[rid] || { good:0, defect:0, damaged:0, total:0 };
                    const availGood = parseInt(stock.good||0);

                    const defUnits = (d.defect_units||[]).filter(u => parseInt(u.rack_id||0) === rid);
                    const damUnits = (d.damaged_units||[]).filter(u => parseInt(u.rack_id||0) === rid);

                    // condition filter
                    const showGood = (!condFilter || condFilter === 'good');
                    const showDef  = (!condFilter || condFilter === 'defect');
                    const showDam  = (!condFilter || condFilter === 'damaged');

                    rowsHtml += `
                        <div class="border rounded mb-2">
                            <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center justify-content-between">
                                <div class="font-weight-bold">${escapeHtml(rackLabel)}</div>
                                <div class="text-muted small">
                                    Stock: GOOD ${availGood} | DEF ${defUnits.length} | DAM ${damUnits.length}
                                </div>
                            </div>

                            <div class="p-3">
                                ${showGood ? renderGoodRow(wid, rackLabel, rid, availGood) : ''}

                                ${showDef ? renderDefectRows(wid, rid, defUnits, rackLabel) : ''}

                                ${showDam ? renderDamagedRows(wid, rid, damUnits, rackLabel) : ''}
                            </div>
                        </div>
                    `;
                });

                return rowsHtml;
            }

            function renderGoodRow(wid, rackLabel, rid, availGood){
                // find existing alloc qty
                const found = (modalGoodAlloc||[]).find(x => parseInt(x.warehouse_id)===parseInt(wid) && parseInt(x.from_rack_id)===parseInt(rid));
                const val = found ? parseInt(found.qty||0) : 0;

                return `
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <div class="font-weight-bold">GOOD</div>
                            <div class="text-muted small">
                                Warehouse: ${escapeHtml(document.querySelector(`#subFilterWarehouse option[value="${wid}"]`)?.textContent || ('#'+wid))}
                                | ${escapeHtml(rackLabel)}
                                | Avail: <span class="badge badge-light border"> ${availGood} </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center" style="gap:10px;">
                            <input type="number"
                                   class="form-control"
                                   style="width:120px;"
                                   min="0"
                                   max="${availGood}"
                                   value="${val}"
                                   data-good-input="1"
                                   data-wid="${wid}"
                                   data-rid="${rid}"
                                   placeholder="0">
                            <span class="badge badge-light border">GOOD</span>
                        </div>
                    </div>
                `;
            }

            function renderDefectRows(wid, rid, units, rackLabel){
                if(!units || units.length<=0){
                    return `
                        <div class="text-muted small mb-2">DEFECT: -</div>
                    `;
                }

                let html = `<div class="mt-2 mb-2"><span class="badge badge-primary">DEFECT</span> <span class="text-muted small">(pick ID = 1 pc)</span></div>`;
                units.forEach(u => {
                    const id = parseInt(u.id||0);
                    const checked = (modalDefectIds||[]).includes(id) ? 'checked' : '';
                    const label = `ID: ${id} | ${u.defect_type || ''} ${u.description ? ('| '+u.description) : ''}`;

                    html += `
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="def_${wid}_${rid}_${id}"
                                   data-defect-id="${id}"
                                   ${checked}>
                            <label class="custom-control-label small" for="def_${wid}_${rid}_${id}">
                                ${escapeHtml(label)}
                            </label>
                        </div>
                    `;
                });

                return html;
            }

            function renderDamagedRows(wid, rid, units, rackLabel){
                if(!units || units.length<=0){
                    return `
                        <div class="text-muted small mb-2">DAMAGED: -</div>
                    `;
                }

                let html = `<div class="mt-2 mb-2"><span class="badge badge-danger">DAMAGED</span> <span class="text-muted small">(pick ID = 1 pc)</span></div>`;
                units.forEach(u => {
                    const id = parseInt(u.id||0);
                    const checked = (modalDamagedIds||[]).includes(id) ? 'checked' : '';
                    const label = `ID: ${id} | ${u.reason || ''}`;

                    html += `
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="dam_${wid}_${rid}_${id}"
                                   data-damaged-id="${id}"
                                   ${checked}>
                            <label class="custom-control-label small" for="dam_${wid}_${rid}_${id}">
                                ${escapeHtml(label)}
                            </label>
                        </div>
                    `;
                });

                return html;
            }

            function bindDynamicEvents(){
                // GOOD input
                document.querySelectorAll('input[data-good-input="1"]').forEach(inp => {
                    inp.addEventListener('input', function(){
                        const wid = this.getAttribute('data-wid');
                        const rid = this.getAttribute('data-rid');
                        let v = parseInt(this.value || 0);
                        if(v < 0) v = 0;

                        // enforce max
                        const mx = parseInt(this.getAttribute('max')||0);
                        if(v > mx) v = mx;
                        this.value = v;

                        // update alloc array (remove if 0)
                        modalGoodAlloc = (modalGoodAlloc||[]).filter(x => !(parseInt(x.warehouse_id)===parseInt(wid) && parseInt(x.from_rack_id)===parseInt(rid)));

                        if(v > 0){
                            const whLabel = document.querySelector(`#subFilterWarehouse option[value="${wid}"]`)?.textContent || ('Warehouse #'+wid);
                            // rack label from cache
                            const d = cache[wid];
                            const rackLabel = (d?.racks||[]).find(r => parseInt(r.id)===parseInt(rid))?.label || ('Rack #'+rid);

                            modalGoodAlloc.push({
                                warehouse_id: parseInt(wid),
                                warehouse_label: whLabel,
                                from_rack_id: parseInt(rid),
                                rack_label: rackLabel,
                                qty: v,
                            });
                        }

                        updateCounters();
                    });
                });

                // DEFECT checkbox
                document.querySelectorAll('input[data-defect-id]').forEach(chk => {
                    chk.addEventListener('change', function(){
                        const id = parseInt(this.getAttribute('data-defect-id')||0);
                        if(id<=0) return;

                        if(this.checked){
                            modalDefectIds.push(id);
                        }else{
                            modalDefectIds = (modalDefectIds||[]).filter(x => parseInt(x)!==id);
                        }
                        modalDefectIds = uniqInt(modalDefectIds);

                        updateCounters();
                    });
                });

                // DAMAGED checkbox
                document.querySelectorAll('input[data-damaged-id]').forEach(chk => {
                    chk.addEventListener('change', function(){
                        const id = parseInt(this.getAttribute('data-damaged-id')||0);
                        if(id<=0) return;

                        if(this.checked){
                            modalDamagedIds.push(id);
                        }else{
                            modalDamagedIds = (modalDamagedIds||[]).filter(x => parseInt(x)!==id);
                        }
                        modalDamagedIds = uniqInt(modalDamagedIds);

                        updateCounters();
                    });
                });
            }

            async function ensureLoadedAndRender(){
                const wid = document.getElementById('subFilterWarehouse').value || '';

                // if choose specific warehouse => load it and build rack options
                if(wid){
                    await fetchWarehouseData(wid);
                    buildRackOptions(wid);
                }else{
                    // load all warehouses for first render (only once)
                    const opts = document.querySelectorAll('#subFilterWarehouse option');
                    for(const o of opts){
                        if(!o.value) continue;
                        await fetchWarehouseData(o.value);
                    }
                    // rack options empty because wid empty
                    buildRackOptions('');
                }

                renderList();
            }

            // ====== exposed functions ======
            window.openSubPickModal = async function(rowIndex, productId){
                currentRowIndex = rowIndex;
                currentProductId = productId;

                // expected qty comes from server-rendered card badge
                const rowCard = document.querySelector(`[data-sub-row="${rowIndex}"]`);
                const expectedText = rowCard?.querySelector('.badge-secondary')?.textContent || '0';
                currentExpected = parseInt(expectedText || 0);

                // reset cache per open (biar aman kalau product berubah)
                cache = {};

                // load existing selection from hidden wrap if exists:
                // we keep state in Livewire too, tapi supaya modal re-open aman,
                // kita ambil dari DOM hidden inputs jika ada.
                modalGoodAlloc = [];
                modalDefectIds = [];
                modalDamagedIds = [];

                // try read from dataset stored by blade injection:
                const existing = window.__subSelections?.[rowIndex];
                if(existing){
                    modalGoodAlloc = Array.isArray(existing.good_allocations) ? existing.good_allocations : [];
                    modalDefectIds = uniqInt(existing.defect_ids || []);
                    modalDamagedIds = uniqInt(existing.damaged_ids || []);
                }

                // init filters
                resetSubFilters(true);

                // show modal
                $('#subPickModal').modal('show');

                // load + render
                try{
                    await ensureLoadedAndRender();
                }catch(e){
                    document.getElementById('subPickListWrap').innerHTML =
                        `<div class="alert alert-danger border">${escapeHtml(e.message || 'Load error')}</div>`;
                }

                updateCounters();
            }

            window.resetSubFilters = function(skipRender){
                document.getElementById('subFilterWarehouse').value = '';
                document.getElementById('subFilterRack').innerHTML = `<option value="">All Racks</option>`;
                document.getElementById('subFilterCondition').value = '';
                if(!skipRender){
                    renderList();
                }
            }

            window.applySubFilters = async function(){
                // when warehouse chosen, ensure loaded and update rack dropdown
                const wid = document.getElementById('subFilterWarehouse').value || '';
                if(wid){
                    await fetchWarehouseData(wid);
                    buildRackOptions(wid);
                }else{
                    buildRackOptions('');
                }
                renderList();
            }

            window.saveSubSelection = function(){
                const good = sumGoodAlloc();
                const defc = (modalDefectIds||[]).length;
                const damg = (modalDamagedIds||[]).length;
                const total = good + defc + damg;

                if(total !== currentExpected){
                    alert(`Total selected harus sama dengan Expected.\nExpected=${currentExpected}, Selected=${total}`);
                    return;
                }

                // save to global memory (for re-open modal without re-query Livewire)
                window.__subSelections = window.__subSelections || {};
                window.__subSelections[currentRowIndex] = {
                    good_allocations: modalGoodAlloc,
                    defect_ids: modalDefectIds,
                    damaged_ids: modalDamagedIds,
                };

                // inject hidden inputs into the row wrapper
                const wrap = document.querySelector(`[data-hidden-wrap="${currentRowIndex}"]`);
                if(!wrap) return;

                // build hidden HTML
                let html = '';

                // items[idx][product_id]
                html += `<input type="hidden" name="items[${currentRowIndex}][product_id]" value="${currentProductId}">`;

                // items[idx][qty] expected
                html += `<input type="hidden" name="items[${currentRowIndex}][qty]" value="${currentExpected}">`;

                // GOOD allocations
                // items[idx][good_allocations][k][warehouse_id], [from_rack_id], [qty]
                (modalGoodAlloc||[]).forEach((ga, k) => {
                    html += `<input type="hidden" name="items[${currentRowIndex}][good_allocations][${k}][warehouse_id]" value="${parseInt(ga.warehouse_id||0)}">`;
                    html += `<input type="hidden" name="items[${currentRowIndex}][good_allocations][${k}][from_rack_id]" value="${parseInt(ga.from_rack_id||0)}">`;
                    html += `<input type="hidden" name="items[${currentRowIndex}][good_allocations][${k}][qty]" value="${parseInt(ga.qty||0)}">`;
                });

                // DEFECT ids
                (modalDefectIds||[]).forEach((id, k) => {
                    html += `<input type="hidden" name="items[${currentRowIndex}][selected_defect_ids][${k}]" value="${parseInt(id)}">`;
                });

                // DAMAGED ids
                (modalDamagedIds||[]).forEach((id, k) => {
                    html += `<input type="hidden" name="items[${currentRowIndex}][selected_damaged_ids][${k}]" value="${parseInt(id)}">`;
                });

                wrap.innerHTML = html;

                // close modal
                $('#subPickModal').modal('hide');

                // re-check global invalid alert
                if(typeof window.validateAllAdjustmentRows === 'function'){
                    window.validateAllAdjustmentRows();
                }else{
                    // fallback: show global alert if any mismatch exists
                    recomputeGlobalInvalid();
                }

                // trigger livewire refresh (note + badges)
                if(window.Livewire){
                    Livewire.emit('subSelectionSaved', currentRowIndex, window.__subSelections[currentRowIndex]);
                }
            }

            function recomputeGlobalInvalid(){
                const cards = document.querySelectorAll('[data-sub-row]');
                let invalid = false;
                cards.forEach(c => {
                    const selectedText = c.querySelector('.badge-light.border')?.textContent || '';
                    // this is weak fallback; main validation uses validateAllAdjustmentRows()
                });
                document.getElementById('subGlobalInvalidAlert').style.display = invalid ? 'block' : 'none';
            }

            // expose validator for parent submit button (dipakai create.blade kamu)
            window.validateAllAdjustmentRows = function(){
                let invalid = false;

                document.querySelectorAll('[data-sub-row]').forEach(card => {
                    const idx = parseInt(card.getAttribute('data-sub-row')||0);

                    const expected = parseInt(card.querySelector('.badge-secondary')?.textContent || '0');
                    const sel = window.__subSelections?.[idx];

                    let good = 0;
                    if(sel?.good_allocations){
                        sel.good_allocations.forEach(x => good += parseInt(x.qty||0));
                    }
                    const defc = Array.isArray(sel?.defect_ids) ? sel.defect_ids.length : 0;
                    const damg = Array.isArray(sel?.damaged_ids) ? sel.damaged_ids.length : 0;

                    const total = good + defc + damg;

                    // note required
                    const noteEl = card.querySelector(`textarea[data-note="${idx}"]`);
                    const noteVal = (noteEl?.value || '').trim();

                    const ok = (total === expected) && (noteVal !== '');
                    card.classList.toggle('border-danger', !ok);
                    card.classList.toggle('border', ok);

                    const statusBadge = card.querySelector('.row-status');
                    if(statusBadge){
                        if(ok){
                            statusBadge.className = 'badge badge-success row-status px-3 py-2';
                            statusBadge.innerText = 'OK';
                        }else{
                            statusBadge.className = 'badge badge-danger row-status px-3 py-2';
                            statusBadge.innerText = 'Qty mismatch / Need note';
                        }
                    }

                    if(!ok) invalid = true;

                    // sync note to hidden input
                    const wrap = card.querySelector(`[data-hidden-wrap="${idx}"]`);
                    if(wrap){
                        // ensure note is submitted
                        // we do not remove others; just append/replace note hidden
                        const existing = wrap.querySelector(`input[name="items[${idx}][note]"]`);
                        if(existing){
                            existing.value = noteVal;
                        }else{
                            wrap.insertAdjacentHTML('beforeend', `<input type="hidden" name="items[${idx}][note]" value="${escapeHtml(noteVal)}">`);
                        }
                    }
                });

                document.getElementById('subGlobalInvalidAlert').style.display = invalid ? 'block' : 'none';
                return !invalid;
            }

            // keep global selection state at first load
            window.__subSelections = window.__subSelections || {};
            @foreach($selections as $i => $sel)
                window.__subSelections[{{ $i }}] = {
                    good_allocations: @json($sel['good_allocations'] ?? []),
                    defect_ids: @json($sel['defect_ids'] ?? []),
                    damaged_ids: @json($sel['damaged_ids'] ?? []),
                };
            @endforeach

            // when warehouse filter changes, rebuild rack dropdown
            document.addEventListener('DOMContentLoaded', function(){
                const wh = document.getElementById('subFilterWarehouse');
                if(wh){
                    wh.addEventListener('change', async function(){
                        const wid = this.value || '';
                        if(wid){
                            try{
                                await fetchWarehouseData(wid);
                                buildRackOptions(wid);
                            }catch(e){
                                // ignore
                            }
                        }else{
                            buildRackOptions('');
                        }
                    });
                }

                // validate on typing note
                document.querySelectorAll('textarea[data-note]').forEach(t => {
                    t.addEventListener('input', function(){
                        if(typeof window.validateAllAdjustmentRows === 'function'){
                            window.validateAllAdjustmentRows();
                        }
                    });
                });
            });

        })();
    </script>
</div>
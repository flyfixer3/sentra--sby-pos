{{-- FILE: resources/views/livewire/adjustment/product-table-stock-sub.blade.php --}}

<div>

    <div class="mb-3">
        <div class="d-flex align-items-start justify-content-between">
            <div>
                <div class="font-weight-bold">Stock SUB (Reduce Stock)</div>
                <div class="text-muted small">
                    Flow: Pick Items (Warehouse/Rack/Condition) → Total selected must match Expected.
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
            <div class="border rounded p-3 bg-white h-100">
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
            <div class="border rounded p-3 bg-white h-100">
                <div class="d-flex align-items-center">
                    <span class="badge badge-pill badge-light border mr-2">2</span>
                    <div>
                        <div class="font-weight-bold">GOOD pakai Qty</div>
                        <div class="text-muted small">GOOD bisa &gt; 1 (max = stock di rack).</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-2">
            <div class="border rounded p-3 bg-white h-100">
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
            <li><b>Note per item wajib diisi</b> untuk SUB.</li>
        </ul>
    </div>

    {{-- Alert global kalau ada invalid --}}
    <div class="alert alert-danger border mt-3" style="display:none;" id="subGlobalInvalidAlert">
        <div class="font-weight-bold mb-1">
            <i class="bi bi-exclamation-triangle"></i> Masih ada item yang belum valid.
        </div>
        <div class="small text-muted">Buka "Pick Items" per item, lalu pastikan total selected sama dengan Expected + note sudah diisi.</div>
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
                    $expected = (int) ($products[$idx]['expected_qty'] ?? 1);
                    if($expected <= 0) $expected = 1;

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

                        {{-- Top area --}}
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="pr-3">
                                <div class="font-weight-bold">
                                    {{ $p['product_name'] ?? 'Product' }}
                                </div>
                                <div class="text-muted small">
                                    Code: {{ $p['product_code'] ?? '-' }}
                                    &nbsp; <span class="text-muted">|</span> &nbsp;
                                    product_id: {{ (int) ($p['product_id'] ?? 0) }}
                                </div>
                            </div>

                            {{-- ✅ Right area (1 row: Expected + Badge + Button) --}}
                            <div class="d-flex align-items-center justify-content-end flex-wrap"
                                 style="gap:10px; min-width:520px;">
                                <div class="d-flex align-items-center" style="gap:10px;">
                                    <div class="text-left">
                                        <div class="text-muted small mb-1">Expected</div>
                                        <input type="number"
                                               class="form-control form-control-sm text-right"
                                               style="width:110px;"
                                               min="1"
                                               wire:model.debounce.300ms="products.{{ $idx }}.expected_qty"
                                               data-expected-input="{{ $idx }}"
                                               oninput="window.onSubExpectedChanged && window.onSubExpectedChanged({{ $idx }})">
                                    </div>

                                    <div class="d-flex align-items-center" style="gap:10px;">
                                        <div class="text-left">
                                            <div class="text-muted small mb-1">Status</div>
                                            @if($isValid)
                                                <span class="badge badge-success row-status px-3 py-2">OK</span>
                                            @else
                                                <span class="badge badge-danger row-status px-3 py-2">Qty mismatch / Need note</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center" style="gap:10px;">
                                    <div class="text-left">
                                        <div class="text-muted small mb-1">Action</div>
                                        <button type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                onclick="openSubPickModal({{ $idx }}, {{ (int)($p['product_id'] ?? 0) }})">
                                            <i class="bi bi-grid"></i> Pick Items
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- badges summary --}}
                        <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                            <span class="badge badge-light border">
                                Selected: <b data-selected-preview="{{ $idx }}">{{ $totalSelected }}</b>
                                / <span data-expected-preview="{{ $idx }}">{{ $expected }}</span>
                            </span>
                            <span class="badge badge-light border">GOOD: <b>{{ $goodTotal }}</b></span>
                            <span class="badge badge-light border">DEFECT: <b>{{ $defTotal }}</b> (1 pc/ID)</span>
                            <span class="badge badge-light border">DAMAGED: <b>{{ $damTotal }}</b> (1 pc/ID)</span>
                        </div>

                        <div class="row mt-3">
                            <div class="col-lg-12">

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

                                {{-- ✅ Note --}}
                                <div class="mt-3">
                                    <label class="font-weight-bold mb-1">Item Note <span class="text-danger">*</span></label>
                                    <textarea
                                        class="form-control"
                                        rows="2"
                                        placeholder="Wajib isi alasan / catatan untuk SUB item ini..."
                                        wire:model.debounce.400ms="selections.{{ $idx }}.note"
                                        data-note="{{ $idx }}"
                                        data-note-input="{{ $idx }}"
                                    ></textarea>
                                    <small class="text-muted">Catatan ini masuk ke mutation note & tampil di detail adjustment.</small>
                                </div>

                                {{-- Hidden inputs for form submission --}}
                                <div data-hidden-wrap="{{ $idx }}" class="mt-2">

                                    {{-- base hidden ALWAYS present --}}
                                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ (int)($p['product_id'] ?? 0) }}" data-base="1">
                                    <input type="hidden" name="items[{{ $idx }}][qty]" value="{{ (int)($products[$idx]['expected_qty'] ?? 1) }}" data-hidden-qty="{{ $idx }}" data-base="1">
                                    <input type="hidden" name="items[{{ $idx }}][note]" value="{{ e((string)($sel['note'] ?? '')) }}" data-hidden-note="{{ $idx }}" data-base="1">

                                    {{-- ✅ FIX: selection hidden inputs MUST be server-rendered from Livewire state --}}
                                    @foreach(($sel['good_allocations'] ?? []) as $k => $ga)
                                        <input type="hidden" name="items[{{ $idx }}][good_allocations][{{ $k }}][warehouse_id]" value="{{ (int)($ga['warehouse_id'] ?? 0) }}">
                                        <input type="hidden" name="items[{{ $idx }}][good_allocations][{{ $k }}][from_rack_id]" value="{{ (int)($ga['from_rack_id'] ?? 0) }}">
                                        <input type="hidden" name="items[{{ $idx }}][good_allocations][{{ $k }}][qty]" value="{{ (int)($ga['qty'] ?? 0) }}">
                                    @endforeach

                                    @foreach(($sel['defect_ids'] ?? []) as $k => $id)
                                        <input type="hidden" name="items[{{ $idx }}][selected_defect_ids][{{ $k }}]" value="{{ (int)$id }}">
                                    @endforeach

                                    @foreach(($sel['damaged_ids'] ?? []) as $k => $id)
                                        <input type="hidden" name="items[{{ $idx }}][selected_damaged_ids][{{ $k }}]" value="{{ (int)$id }}">
                                    @endforeach

                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- MODAL PICK ITEMS (SUB) --}}
    <div class="modal fade" id="subPickModal" tabindex="-1" role="dialog" aria-labelledby="subPickModalLabel" aria-hidden="true" wire:ignore.self>
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

    {{-- JS (SUB PICK MODAL) --}}
    @once
    <script>
        (function(){
            const SUB_PICK_URL = @json($stockSubPickerUrl);

            let currentRowIndex = null;
            let currentProductId = null;
            let currentExpected = 0;

            let modalGoodAlloc = [];
            let modalDefectIds = [];
            let modalDamagedIds = [];

            let cache = {};

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

            function escapeHtml(str){
                str = (str ?? '').toString();
                return str
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
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

                const wids = [];
                const whSel = document.getElementById('subFilterWarehouse').value || '';
                if(whSel){
                    wids.push(whSel);
                }else{
                    const opts = document.querySelectorAll('#subFilterWarehouse option');
                    opts.forEach(o => { if(o.value) wids.push(o.value); });
                }

                let html = '';
                html += `<div class="small text-muted mb-2">List item sesuai filter (GOOD pakai qty, DEFECT/DAMAGED 1pc per ID).</div>`;

                if(wids.length <= 0){
                    html += `<div class="alert alert-light border">No warehouse option.</div>`;
                    wrap.innerHTML = html;
                    return;
                }

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

                bindDynamicEvents();
                updateCounters();
            }

            function renderRowsForWarehouse(wid, d){
                const rackFilter = document.getElementById('subFilterRack').value || '';
                const condFilter = document.getElementById('subFilterCondition').value || '';

                const rackMap = {};
                (d.racks||[]).forEach(r => rackMap[r.id] = r.label);

                const stockByRack = d.stock_by_rack || {};
                const rackIds = Object.keys(stockByRack).map(x => parseInt(x)).filter(x => x > 0);

                const racksFromDef = (d.defect_units||[]).map(u => parseInt(u.rack_id||0)).filter(x => x>0);
                const racksFromDam = (d.damaged_units||[]).map(u => parseInt(u.rack_id||0)).filter(x => x>0);

                const allRackIds = Array.from(new Set([...rackIds, ...racksFromDef, ...racksFromDam])).sort((a,b)=>a-b);

                if(allRackIds.length <= 0){
                    return `<div class="text-muted px-2 py-2">No stock found.</div>`;
                }

                let rowsHtml = '';

                allRackIds.forEach(rid => {
                    if(rackFilter && parseInt(rackFilter) !== rid) return;

                    const rackLabel = rackMap[rid] || `Rack #${rid}`;

                    const stock = stockByRack[rid] || { good:0, defect:0, damaged:0, total:0 };
                    const availGood = parseInt(stock.good||0);

                    const defUnits = (d.defect_units||[]).filter(u => parseInt(u.rack_id||0) === rid);
                    const damUnits = (d.damaged_units||[]).filter(u => parseInt(u.rack_id||0) === rid);

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
                                ${showDef ? renderDefectRows(wid, rid, defUnits) : ''}
                                ${showDam ? renderDamagedRows(wid, rid, damUnits) : ''}
                            </div>
                        </div>
                    `;
                });

                return rowsHtml;
            }

            function renderGoodRow(wid, rackLabel, rid, availGood){
                const found = (modalGoodAlloc||[]).find(x => parseInt(x.warehouse_id)===parseInt(wid) && parseInt(x.from_rack_id)===parseInt(rid));
                const val = found ? parseInt(found.qty||0) : 0;

                const whLabel = document.querySelector(`#subFilterWarehouse option[value="${wid}"]`)?.textContent || ('Warehouse #'+wid);

                return `
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div>
                            <div class="font-weight-bold">GOOD</div>
                            <div class="text-muted small">
                                Warehouse: ${escapeHtml(whLabel)}
                                | ${escapeHtml(rackLabel)}
                                | Avail: <span class="badge badge-light border">${availGood}</span>
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

            function renderDefectRows(wid, rid, units){
                if(!units || units.length<=0){
                    return `<div class="text-muted small mb-2">DEFECT: -</div>`;
                }

                let html = `<div class="mt-2 mb-2"><span class="badge badge-primary">DEFECT</span> <span class="text-muted small">(pick ID = 1 pc)</span></div>`;
                units.forEach(u => {
                    const id = parseInt(u.id||0);
                    const checked = (modalDefectIds||[]).includes(id) ? 'checked' : '';

                    const dt = (u.defect_type || '').trim();
                    const ds = (u.description || '').trim();
                    const label = `ID: ${id}` + (dt ? ` | Type: ${dt}` : '') + (ds ? ` | Desc: ${ds}` : '');

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

            function renderDamagedRows(wid, rid, units){
                if(!units || units.length<=0){
                    return `<div class="text-muted small mb-2">DAMAGED: -</div>`;
                }

                let html = `<div class="mt-2 mb-2"><span class="badge badge-danger">DAMAGED</span> <span class="text-muted small">(pick ID = 1 pc)</span></div>`;
                units.forEach(u => {
                    const id = parseInt(u.id||0);
                    const checked = (modalDamagedIds||[]).includes(id) ? 'checked' : '';

                    const tp = ((u.damage_type || 'damaged') + '').trim();
                    const rs = (u.reason || '').trim();
                    const label = `ID: ${id} | Type: ${tp}` + (rs ? ` | Reason: ${rs}` : '');

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
                document.querySelectorAll('input[data-good-input="1"]').forEach(inp => {
                    inp.addEventListener('input', function(){
                        const wid = this.getAttribute('data-wid');
                        const rid = this.getAttribute('data-rid');
                        let v = parseInt(this.value || 0);
                        if(v < 0) v = 0;

                        const mx = parseInt(this.getAttribute('max')||0);
                        if(v > mx) v = mx;
                        this.value = v;

                        modalGoodAlloc = (modalGoodAlloc||[]).filter(x => !(parseInt(x.warehouse_id)===parseInt(wid) && parseInt(x.from_rack_id)===parseInt(rid)));

                        if(v > 0){
                            const whLabel = document.querySelector(`#subFilterWarehouse option[value="${wid}"]`)?.textContent || ('Warehouse #'+wid);
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

                document.querySelectorAll('input[data-defect-id]').forEach(chk => {
                    chk.addEventListener('change', function(){
                        const id = parseInt(this.getAttribute('data-defect-id')||0);
                        if(id<=0) return;

                        if(this.checked) modalDefectIds.push(id);
                        else modalDefectIds = (modalDefectIds||[]).filter(x => parseInt(x)!==id);

                        modalDefectIds = uniqInt(modalDefectIds);
                        updateCounters();
                    });
                });

                document.querySelectorAll('input[data-damaged-id]').forEach(chk => {
                    chk.addEventListener('change', function(){
                        const id = parseInt(this.getAttribute('data-damaged-id')||0);
                        if(id<=0) return;

                        if(this.checked) modalDamagedIds.push(id);
                        else modalDamagedIds = (modalDamagedIds||[]).filter(x => parseInt(x)!==id);

                        modalDamagedIds = uniqInt(modalDamagedIds);
                        updateCounters();
                    });
                });
            }

            async function ensureLoadedAndRender(){
                const wid = document.getElementById('subFilterWarehouse').value || '';

                if(wid){
                    await fetchWarehouseData(wid);
                    buildRackOptions(wid);
                }else{
                    const opts = document.querySelectorAll('#subFilterWarehouse option');
                    for(const o of opts){
                        if(!o.value) continue;
                        await fetchWarehouseData(o.value);
                    }
                    buildRackOptions('');
                }

                renderList();
            }

            window.openSubPickModal = async function(rowIndex, productId){
                currentRowIndex = rowIndex;
                currentProductId = productId;

                const rowCard = document.querySelector(`[data-sub-row="${rowIndex}"]`);
                const expectedInput = rowCard?.querySelector(`input[data-expected-input="${rowIndex}"]`);
                currentExpected = parseInt(expectedInput?.value || '0') || 0;

                cache = {};
                modalGoodAlloc = [];
                modalDefectIds = [];
                modalDamagedIds = [];

                const existing = window.__subSelections?.[rowIndex];
                if(existing){
                    modalGoodAlloc = Array.isArray(existing.good_allocations) ? existing.good_allocations : [];
                    modalDefectIds = uniqInt(existing.defect_ids || []);
                    modalDamagedIds = uniqInt(existing.damaged_ids || []);
                }

                resetSubFilters(true);

                $('#subPickModal').modal('show');

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
                if(!skipRender) renderList();
            }

            window.applySubFilters = async function(){
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

                window.__subSelections = window.__subSelections || {};
                window.__subSelections[currentRowIndex] = {
                    good_allocations: modalGoodAlloc,
                    defect_ids: modalDefectIds,
                    damaged_ids: modalDamagedIds,
                };

                // ✅ FIX: jangan inject hidden inputs via JS (akan hilang kena Livewire rerender)
                // cukup kirim ke Livewire state, nanti Blade yang render hidden inputs
                if(window.Livewire){
                    Livewire.emit('subSelectionSaved', currentRowIndex, window.__subSelections[currentRowIndex]);
                }

                $('#subPickModal').modal('hide');

                if(typeof window.validateAllAdjustmentRows === 'function'){
                    window.validateAllAdjustmentRows();
                }
            }

            window.validateAllAdjustmentRows = function(){
                let invalid = false;

                document.querySelectorAll('[data-sub-row]').forEach(card => {
                    const idx = parseInt(card.getAttribute('data-sub-row')||0);

                    const expected = parseInt(card.querySelector(`input[data-expected-input="${idx}"]`)?.value || '0') || 0;

                    const sel = window.__subSelections?.[idx];

                    let good = 0;
                    if(sel?.good_allocations){
                        sel.good_allocations.forEach(x => good += parseInt(x.qty||0));
                    }
                    const defc = Array.isArray(sel?.defect_ids) ? sel.defect_ids.length : 0;
                    const damg = Array.isArray(sel?.damaged_ids) ? sel.damaged_ids.length : 0;

                    const total = good + defc + damg;

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

                    const expPreview = card.querySelector(`[data-expected-preview="${idx}"]`);
                    if(expPreview) expPreview.textContent = expected;

                    const selPreview = card.querySelector(`[data-selected-preview="${idx}"]`);
                    if(selPreview) selPreview.textContent = total;

                    // keep base hidden sync (qty/note)
                    const qtyHidden = card.querySelector(`input[data-hidden-qty="${idx}"]`);
                    if(qtyHidden) qtyHidden.value = expected;

                    const noteHidden = card.querySelector(`input[data-hidden-note="${idx}"]`);
                    if(noteHidden) noteHidden.value = noteVal;

                    if(!ok) invalid = true;
                });

                document.getElementById('subGlobalInvalidAlert').style.display = invalid ? 'block' : 'none';
                return !invalid;
            }

            window.onSubExpectedChanged = function(idx){
                if(typeof window.validateAllAdjustmentRows === 'function'){
                    window.validateAllAdjustmentRows();
                }
            }

            // seed selections (for modal prefill)
            window.__subSelections = window.__subSelections || {};
            @foreach($selections as $i => $sel)
                window.__subSelections[{{ $i }}] = {
                    good_allocations: @json($sel['good_allocations'] ?? []),
                    defect_ids: @json($sel['defect_ids'] ?? []),
                    damaged_ids: @json($sel['damaged_ids'] ?? []),
                };
            @endforeach

            function bindRowListeners(){
                document.querySelectorAll('textarea[data-note-input]').forEach(t => {
                    t.removeEventListener('input', t.__subInputHandler || function(){});
                    t.__subInputHandler = function(){
                        if(typeof window.validateAllAdjustmentRows === 'function'){
                            window.validateAllAdjustmentRows();
                        }
                    };
                    t.addEventListener('input', t.__subInputHandler);
                });

                document.querySelectorAll('input[data-expected-input]').forEach(inp => {
                    inp.addEventListener('change', function(){
                        const idx = parseInt(this.getAttribute('data-expected-input')||'0');
                        if(window.onSubExpectedChanged) window.onSubExpectedChanged(idx);
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function(){
                const wh = document.getElementById('subFilterWarehouse');
                if(wh){
                    wh.addEventListener('change', async function(){
                        const wid = this.value || '';
                        if(wid){
                            try{
                                await fetchWarehouseData(wid);
                                buildRackOptions(wid);
                            }catch(e){}
                        }else{
                            buildRackOptions('');
                        }
                    });
                }

                bindRowListeners();

                // ✅ Rebind setelah Livewire rerender
                if (window.Livewire && Livewire.hook) {
                    Livewire.hook('message.processed', () => {
                        bindRowListeners();
                        if(typeof window.validateAllAdjustmentRows === 'function'){
                            window.validateAllAdjustmentRows();
                        }
                    });
                }
            });

        })();
    </script>
    @endonce

</div>

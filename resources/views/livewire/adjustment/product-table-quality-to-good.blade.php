{{-- FILE: resources/views/livewire/adjustment/product-table-quality-to-good.blade.php --}}

<div>

    <div class="mb-3">
        <div class="d-flex align-items-start justify-content-between">
            <div>
                <div class="font-weight-bold">Quality Issue → GOOD (Pick Unit IDs)</div>
                <div class="text-muted small">
                    Flow: Pick Items (Warehouse/Rack) → Total selected must match Expected.
                </div>
            </div>

            <span class="badge badge-light border px-3 py-2">
                <i class="bi bi-shield-check"></i> Mode: TO-GOOD
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
                        <div class="text-muted small">Pilih unit IDs di modal (bisa lintas warehouse/rack).</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-2">
            <div class="border rounded p-3 bg-white h-100">
                <div class="d-flex align-items-center">
                    <span class="badge badge-pill badge-light border mr-2">2</span>
                    <div>
                        <div class="font-weight-bold">Expected = Qty</div>
                        <div class="text-muted small">Isi berapa unit yang mau direclass ke GOOD.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-2">
            <div class="border rounded p-3 bg-white h-100">
                <div class="d-flex align-items-center">
                    <span class="badge badge-pill badge-light border mr-2">3</span>
                    <div>
                        <div class="font-weight-bold">1 ID = 1 pc</div>
                        <div class="text-muted small">Total checkbox harus sama dengan Expected.</div>
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
            <li>Total selected (unit IDs) wajib sama dengan Expected.</li>
            <li>Modal bisa filter Warehouse / Rack (optional) untuk mempermudah cari unit.</li>
            <li>Untuk mode ini <b>tidak ada GOOD qty input</b> — hanya pick unit IDs.</li>
        </ul>
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
                        'unit_ids' => [],
                    ];

                    $pickedTotal = is_array($sel['unit_ids'] ?? null) ? count($sel['unit_ids']) : 0;
                    $isValid = ($pickedTotal === $expected);
                    $pid = (int) ($p['product_id'] ?? 0);
                @endphp

                <div
                    class="card mb-3 {{ $isValid ? 'border' : 'border-danger' }}"
                    data-qtg-row="{{ $idx }}"
                    wire:key="qtg-row-{{ $idx }}-pid-{{ $pid }}"
                >
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
                                    product_id: {{ $pid }}
                                </div>
                            </div>

                            {{-- Right area (Expected + Status + Button) --}}
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
                                               data-qtg-expected-input="{{ $idx }}"
                                               oninput="window.onQtgExpectedChanged && window.onQtgExpectedChanged({{ $idx }})">
                                    </div>

                                    <div class="text-left">
                                        <div class="text-muted small mb-1">Status</div>
                                        @if($isValid)
                                            <span class="badge badge-success qtg-row-status px-3 py-2">OK</span>
                                        @else
                                            <span class="badge badge-danger qtg-row-status px-3 py-2">Qty mismatch</span>
                                        @endif
                                    </div>
                                </div>

                               <div class="text-left">
                                    <div class="text-muted small mb-1">Action</div>

                                    <div class="d-flex" style="gap:8px;">
                                        <button type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                onclick="openQtgPickModal({{ $idx }}, {{ $pid }})">
                                            <i class="bi bi-grid"></i> Pick Items
                                        </button>

                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm"
                                                wire:click="removeProduct({{ $idx }})"
                                                onclick="return confirm('Remove product ini dari list?')">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- badges summary --}}
                        <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                            <span class="badge badge-light border">
                                Selected: <b data-qtg-selected-preview="{{ $idx }}">{{ $pickedTotal }}</b>
                                / <span data-qtg-expected-preview="{{ $idx }}">{{ $expected }}</span>
                            </span>
                            <span class="badge badge-light border">
                                Condition: <b id="qtgConditionBadgeText">{{ strtoupper($condition ?? 'DEFECT') }}</b>
                            </span>
                        </div>

                        <div class="small text-muted mt-2 mb-1">Selected Unit IDs:</div>
                        <div class="small">
                            @if($pickedTotal <= 0)
                                <div class="text-muted">-</div>
                            @else
                                <div>{{ implode(', ', array_map('intval', $sel['unit_ids'])) }}</div>
                            @endif
                        </div>

                        <div class="mt-3">
                            <label class="small text-muted mb-1">Item Note <span class="text-danger">*</span></label>
                            <textarea class="form-control"
                                    rows="2"
                                    placeholder="Contoh: alasan reclass / info QC..."
                                    wire:model.defer="products.{{ $idx }}.user_note"
                                    oninput="window.onQtgExpectedChanged && window.onQtgExpectedChanged({{ $idx }})"></textarea>
                            <small class="text-muted">
                                Note wajib untuk Issue → GOOD (per item).
                            </small>
                        </div>

                        {{-- Hidden inputs for form submission --}}
                        <div data-qtg-hidden-wrap="{{ $idx }}" class="mt-2">

                            <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $pid }}" data-qtg-base="1">
                            <input type="hidden" name="items[{{ $idx }}][qty]" value="{{ (int)($products[$idx]['expected_qty'] ?? 1) }}" data-qtg-hidden-qty="{{ $idx }}" data-qtg-base="1">

                            <input type="hidden"
                                name="items[{{ $idx }}][user_note]"
                                value="{{ (string)($products[$idx]['user_note'] ?? '') }}"
                                data-qtg-hidden-note="{{ $idx }}"
                                data-qtg-base="1">
                            @foreach(($sel['unit_ids'] ?? []) as $k => $id)
                                <input type="hidden" name="items[{{ $idx }}][selected_unit_ids][{{ $k }}]" value="{{ (int)$id }}">
                            @endforeach

                        </div>

                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- MODAL PICK ITEMS (QUALITY TO GOOD) --}}
    <div class="modal fade" id="qtgPickModal" tabindex="-1" role="dialog" aria-labelledby="qtgPickModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content" style="border-radius:12px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold" id="qtgPickModalLabel">Pick Items</h5>
                        <div class="text-muted small">
                            Filter berdasarkan <b>Warehouse / Rack</b>.
                            <span class="ml-2 badge badge-light border" id="qtgCondHint">DEFECT: 1 pc / ID</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center" style="gap:10px;">
                        <span class="badge badge-light border px-3 py-2" id="qtgModalSelectedCounter">Selected 0 / 0</span>
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
                                <select class="form-control" id="qtgFilterWarehouse">
                                    <option value="">All warehouse</option>
                                    @foreach($warehouseOptions as $w)
                                        <option value="{{ (int)$w['id'] }}">{{ $w['label'] }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Warehouse ini hanya untuk filter (optional).</small>
                            </div>

                            <div class="col-lg-4 mb-2">
                                <label class="small text-muted mb-1">Racks</label>
                                <select class="form-control" id="qtgFilterRack">
                                    <option value="">All Racks</option>
                                </select>
                                <small class="text-muted">Rack filter aktif jika warehouse dipilih.</small>
                            </div>

                            <div class="col-lg-4 mb-2">
                                <label class="small text-muted mb-1">Actions</label>
                                <div class="d-flex" style="gap:8px;">
                                    <button type="button" class="btn btn-light border" onclick="resetQtgFilters()">Reset</button>
                                    <button type="button" class="btn btn-primary" onclick="applyQtgFilters()">Apply</button>
                                </div>
                            </div>
                        </div>

                        <div class="small text-muted mt-2">
                            Required Total: <b id="qtgRequiredTotal">0</b>
                            &nbsp; • &nbsp; Picked: <b id="qtgPickedTotal">0</b>
                        </div>
                    </div>

                    <div id="qtgPickListWrap">
                        <div class="text-muted">Loading...</div>
                    </div>

                    <div class="text-muted small mt-2">
                        * Save akan menyimpan pilihan ke card.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-secondary" onclick="saveQtgSelection()">Save Selection</button>
                </div>
            </div>
        </div>
    </div>

    {{-- JS --}}
    @once
    <script>
        (function(){
            const QTG_PICK_URL = @json($qualityToGoodPickerUrl);

            let currentRowIndex = null;
            let currentProductId = null;
            let currentExpected = 0;

            // condition ditentukan dari Quality type (defect_to_good / damaged_to_good)
            let currentCondition = @json($condition ?? 'defect'); // default
            let modalUnitIds = [];

            let cache = {}; // per warehouse

            function uniqInt(arr){
                arr = Array.isArray(arr) ? arr : [];
                const s = new Set();
                arr.forEach(x => {
                    const v = parseInt(x || 0);
                    if(v > 0) s.add(v);
                });
                return Array.from(s.values());
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
                const picked = (modalUnitIds||[]).length;

                document.getElementById('qtgRequiredTotal').innerText = currentExpected;
                document.getElementById('qtgPickedTotal').innerText = picked;

                const el = document.getElementById('qtgModalSelectedCounter');
                if(el) el.innerText = `Selected ${picked} / ${currentExpected}`;
            }

            function setCondHint(){
                const hint = document.getElementById('qtgCondHint');
                const badge = document.getElementById('qtgConditionBadgeText');
                const text = (currentCondition || 'defect').toUpperCase();
                if(hint) hint.textContent = `${text}: 1 pc / ID`;
                if(badge) badge.textContent = text;
            }

            function buildRackOptions(wid){
                const rackSel = document.getElementById('qtgFilterRack');
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

                const url = new URL(QTG_PICK_URL, window.location.origin);
                url.searchParams.set('warehouse_id', wid);
                url.searchParams.set('product_id', currentProductId);
                url.searchParams.set('condition', currentCondition);

                const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const json = await res.json();

                if(!json || !json.success){
                    throw new Error(json?.message || 'Failed to load picker data.');
                }

                const data = json.data || {};
                cache[wid] = {
                    wh_id: parseInt(wid),
                    racks: data.racks || [],
                    units: data.units || [], // unified list
                };

                return cache[wid];
            }

            function renderList(){
                const wrap = document.getElementById('qtgPickListWrap');
                if(!wrap) return;

                const wids = [];
                const whSel = document.getElementById('qtgFilterWarehouse').value || '';
                if(whSel){
                    wids.push(whSel);
                }else{
                    const opts = document.querySelectorAll('#qtgFilterWarehouse option');
                    opts.forEach(o => { if(o.value) wids.push(o.value); });
                }

                let html = '';
                html += `<div class="small text-muted mb-2">List item sesuai filter (${escapeHtml((currentCondition||'defect').toUpperCase())} 1pc per ID).</div>`;

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

                    const whLabel = document.querySelector(`#qtgFilterWarehouse option[value="${wid}"]`)?.textContent || `Warehouse #${wid}`;

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
                const rackFilter = document.getElementById('qtgFilterRack').value || '';

                const rackMap = {};
                (d.racks||[]).forEach(r => rackMap[r.id] = r.label);

                const byRack = {};
                (d.units||[]).forEach(u => {
                    const rid = parseInt(u.rack_id||0);
                    if(rid <= 0) return;
                    byRack[rid] = byRack[rid] || [];
                    byRack[rid].push(u);
                });

                const rackIds = Object.keys(byRack).map(x => parseInt(x)).filter(x => x>0).sort((a,b)=>a-b);

                if(rackIds.length <= 0){
                    return `<div class="text-muted px-2 py-2">No unit found.</div>`;
                }

                let rowsHtml = '';

                rackIds.forEach(rid => {
                    if(rackFilter && parseInt(rackFilter) !== rid) return;

                    const rackLabel = rackMap[rid] || `Rack #${rid}`;
                    const units = byRack[rid] || [];

                    rowsHtml += `
                        <div class="border rounded mb-2">
                            <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center justify-content-between">
                                <div class="font-weight-bold">${escapeHtml(rackLabel)}</div>
                                <div class="text-muted small">
                                    Units: ${units.length}
                                </div>
                            </div>

                            <div class="p-3">
                                ${renderUnitRows(wid, rid, units)}
                            </div>
                        </div>
                    `;
                });

                return rowsHtml;
            }

            function renderUnitRows(wid, rid, units){
                if(!units || units.length<=0){
                    return `<div class="text-muted small mb-2">-</div>`;
                }

                let html = '';
                units.forEach(u => {
                    const id = parseInt(u.id||0);
                    const checked = (modalUnitIds||[]).includes(id) ? 'checked' : '';

                    const extra = (u.info || '').toString();
                    const label = `ID: ${id}` + (extra ? ` | ${extra}` : '');

                    html += `
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="qtg_${wid}_${rid}_${id}"
                                   data-qtg-unit-id="${id}"
                                   ${checked}>
                            <label class="custom-control-label small" for="qtg_${wid}_${rid}_${id}">
                                ${escapeHtml(label)}
                            </label>
                        </div>
                    `;
                });

                return html;
            }

            function bindDynamicEvents(){
                document.querySelectorAll('input[data-qtg-unit-id]').forEach(chk => {
                    chk.addEventListener('change', function(){
                        const id = parseInt(this.getAttribute('data-qtg-unit-id')||0);
                        if(id<=0) return;

                        if(this.checked) modalUnitIds.push(id);
                        else modalUnitIds = (modalUnitIds||[]).filter(x => parseInt(x)!==id);

                        modalUnitIds = uniqInt(modalUnitIds);

                        if(modalUnitIds.length > currentExpected){
                            this.checked = false;
                            modalUnitIds = modalUnitIds.filter(x => x !== id);
                            alert('Jumlah picked melebihi Expected.');
                        }

                        updateCounters();
                    });
                });
            }

            async function ensureLoadedAndRender(){
                const wid = document.getElementById('qtgFilterWarehouse').value || '';

                if(wid){
                    await fetchWarehouseData(wid);
                    buildRackOptions(wid);
                }else{
                    const opts = document.querySelectorAll('#qtgFilterWarehouse option');
                    for(const o of opts){
                        if(!o.value) continue;
                        await fetchWarehouseData(o.value);
                    }
                    buildRackOptions('');
                }

                renderList();
            }

            window.openQtgPickModal = async function(rowIndex, productId){
                currentRowIndex = rowIndex;
                currentProductId = productId;

                const qType = document.getElementById('quality_type')?.value || 'defect_to_good';
                currentCondition = (qType === 'damaged_to_good') ? 'damaged' : 'defect';
                setCondHint();

                const rowCard = document.querySelector(`[data-qtg-row="${rowIndex}"]`);
                const expectedInput = rowCard?.querySelector(`input[data-qtg-expected-input="${rowIndex}"]`);
                currentExpected = parseInt(expectedInput?.value || '0') || 0;

                cache = {};
                modalUnitIds = [];

                const existing = window.__qtgSelections?.[rowIndex];
                if(existing){
                    modalUnitIds = uniqInt(existing.unit_ids || []);
                }

                resetQtgFilters(true);

                $('#qtgPickModal').modal('show');

                try{
                    await ensureLoadedAndRender();
                }catch(e){
                    document.getElementById('qtgPickListWrap').innerHTML =
                        `<div class="alert alert-danger border">${escapeHtml(e.message || 'Load error')}</div>`;
                }

                updateCounters();
            }

            window.resetQtgFilters = function(skipRender){
                document.getElementById('qtgFilterWarehouse').value = '';
                document.getElementById('qtgFilterRack').innerHTML = `<option value="">All Racks</option>`;
                if(!skipRender) renderList();
            }

            window.applyQtgFilters = async function(){
                const wid = document.getElementById('qtgFilterWarehouse').value || '';
                if(wid){
                    await fetchWarehouseData(wid);
                    buildRackOptions(wid);
                }else{
                    buildRackOptions('');
                }
                renderList();
            }

            window.saveQtgSelection = function(){
                const total = (modalUnitIds||[]).length;

                if(total !== currentExpected){
                    alert(`Total selected harus sama dengan Expected.\nExpected=${currentExpected}, Selected=${total}`);
                    return;
                }

                window.__qtgSelections = window.__qtgSelections || {};
                window.__qtgSelections[currentRowIndex] = {
                    unit_ids: modalUnitIds,
                };

                if(window.Livewire){
                    Livewire.emit('qualityToGoodSelectionSaved', currentRowIndex, window.__qtgSelections[currentRowIndex]);
                }

                $('#qtgPickModal').modal('hide');

                if(typeof window.validateAllQtgRows === 'function'){
                    window.validateAllQtgRows();
                }
            }

            window.validateAllQtgRows = function(){
                let invalid = false;

                document.querySelectorAll('[data-qtg-row]').forEach(card => {
                    const idx = parseInt(card.getAttribute('data-qtg-row')||0);

                    const expected = parseInt(card.querySelector(`input[data-qtg-expected-input="${idx}"]`)?.value || '0') || 0;
                    const sel = window.__qtgSelections?.[idx];
                    const total = Array.isArray(sel?.unit_ids) ? sel.unit_ids.length : 0;

                    const ok = (total === expected);

                    card.classList.toggle('border-danger', !ok);
                    card.classList.toggle('border', ok);

                    const statusBadge = card.querySelector('.qtg-row-status');
                    if(statusBadge){
                        if(ok){
                            statusBadge.className = 'badge badge-success qtg-row-status px-3 py-2';
                            statusBadge.innerText = 'OK';
                        }else{
                            statusBadge.className = 'badge badge-danger qtg-row-status px-3 py-2';
                            statusBadge.innerText = 'Qty mismatch';
                        }
                    }

                    const expPreview = card.querySelector(`[data-qtg-expected-preview="${idx}"]`);
                    if(expPreview) expPreview.textContent = expected;

                    const selPreview = card.querySelector(`[data-qtg-selected-preview="${idx}"]`);
                    if(selPreview) selPreview.textContent = total;

                    const qtyHidden = card.querySelector(`input[data-qtg-hidden-qty="${idx}"]`);
                    if(qtyHidden) qtyHidden.value = expected;

                    const noteHidden = card.querySelector(`input[data-qtg-hidden-note="${idx}"]`);
                    if(noteHidden){
                    // ambil textarea note yang ada di card
                    const noteEl = card.querySelector('textarea');
                    noteHidden.value = (noteEl?.value || '').toString();
                    }

                    if(!ok) invalid = true;
                });

                return !invalid;
            }

            window.onQtgExpectedChanged = function(idx){
                if(typeof window.validateAllQtgRows === 'function'){
                    window.validateAllQtgRows();
                }
            }

            window.__qtgSelections = window.__qtgSelections || {};
            @foreach($selections as $i => $sel)
                window.__qtgSelections[{{ $i }}] = {
                    unit_ids: @json($sel['unit_ids'] ?? []),
                };
            @endforeach

            function bindRowListeners(){
                document.querySelectorAll('input[data-qtg-expected-input]').forEach(inp => {
                    inp.addEventListener('change', function(){
                        const idx = parseInt(this.getAttribute('data-qtg-expected-input')||'0');
                        if(window.onQtgExpectedChanged) window.onQtgExpectedChanged(idx);
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', function(){
                const wh = document.getElementById('qtgFilterWarehouse');
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

                setCondHint();
                bindRowListeners();

                if (window.Livewire && Livewire.hook) {
                    Livewire.hook('message.processed', () => {
                        bindRowListeners();
                        if(typeof window.validateAllQtgRows === 'function'){
                            window.validateAllQtgRows();
                        }
                    });
                }
            });

        })();
    </script>
    @endonce

</div>
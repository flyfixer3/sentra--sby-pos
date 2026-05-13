<div>
    @if (session()->has('message'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="alert-body">
                <span>{{ session('message') }}</span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        </div>
    @endif

    <div class="table-responsive position-relative">
        <div wire:loading.flex class="col-12 position-absolute justify-content-center align-items-center"
             style="top:0;right:0;left:0;bottom:0;background-color: rgba(255,255,255,0.5);z-index: 99;">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr class="align-middle">
                    <th class="align-middle">#</th>
                    <th class="align-middle">Product Name</th>
                    <th class="align-middle">Code</th>

                    <th class="align-middle text-center">Stock (From Rack)</th>

                    <th class="align-middle text-center" style="width: 160px;">Condition</th>
                    <th class="align-middle" style="width: 160px;">Quantity</th>
                    <th class="align-middle text-center" style="width: 90px;">Action</th>
                </tr>
            </thead>

            <tbody>
                @if(!empty($products) && count($products) > 0)
                    @foreach($products as $key => $p)
                        @php
                            $productId = (int)($p['id'] ?? 0);
                            $name      = $p['product_name'] ?? '-';
                            $code      = $p['product_code'] ?? '-';
                            $unit      = stock_unit_label($p['product_unit'] ?? '');

                            $stockRack = (int)($p['stock_qty'] ?? 0);
                            $qtyInput  = (int)($p['quantity'] ?? 1);
                            if ($qtyInput < 1) $qtyInput = 1;

                            $cond = strtolower((string)($p['condition'] ?? 'good'));
                            if (!in_array($cond, ['good','defect','damaged'], true)) $cond = 'good';
                        @endphp

                        <tr data-rack-move-row="{{ $key }}"
                            data-product-id="{{ $productId }}"
                            data-product-code="{{ e($code) }}"
                            data-product-name="{{ e($name) }}"
                            data-condition="{{ $cond }}">
                            <td class="align-middle">{{ $key + 1 }}</td>
                            <td class="align-middle">{{ $name }}</td>
                            <td class="align-middle">{{ $code }}</td>

                            <td class="align-middle text-center">
                                <span class="badge badge-primary">
                                    {{ (int)$stockRack }} {{ $unit }}
                                </span>

                                <div class="small text-muted mt-1">
                                    G: {{ (int)($p['stock_good'] ?? 0) }} |
                                    Df: {{ (int)($p['stock_defect'] ?? 0) }} |
                                    Dm: {{ (int)($p['stock_damaged'] ?? 0) }}
                                </div>
                            </td>

                            {{-- hidden product id for controller --}}
                            <input type="hidden" name="product_ids[]" value="{{ $productId }}">

                            <td class="align-middle text-center">
                                <select
                                    class="form-control"
                                    style="min-width:140px;"
                                    wire:change="updateCondition({{ $key }}, $event.target.value)"
                                >
                                    <option value="good"   {{ $cond==='good' ? 'selected' : '' }}>GOOD</option>
                                    <option value="defect" {{ $cond==='defect' ? 'selected' : '' }}>DEFECT</option>
                                    <option value="damaged"{{ $cond==='damaged' ? 'selected' : '' }}>DAMAGED</option>
                                </select>
                                <input type="hidden" name="conditions[]" value="{{ $cond }}">
                            </td>

                            <td class="align-middle">
                                @if($cond === 'good')
                                    <input
                                        type="number"
                                        name="quantities[]"
                                        min="1"
                                        max="{{ max(0, (int)$stockRack) }}"
                                        class="form-control"
                                        value="{{ $qtyInput }}"
                                        wire:model.lazy="products.{{ $key }}.quantity"
                                    >
                                    <input type="hidden" name="defect_item_ids[]" value="[]">
                                    <input type="hidden" name="damaged_item_ids[]" value="[]">
                                    <div class="small text-muted mt-1">
                                        GOOD stock moves by quantity. Max: {{ (int)$stockRack }} {{ $unit }}
                                    </div>
                                @else
                                    <input
                                        type="number"
                                        class="form-control rack-move-quality-qty"
                                        value="0"
                                        readonly
                                        data-quality-qty="{{ $key }}"
                                    >
                                    <input type="hidden" name="quantities[]" value="0" data-quality-qty-hidden="{{ $key }}">
                                    <input type="hidden" name="defect_item_ids[]" value="[]" data-defect-ids-hidden="{{ $key }}">
                                    <input type="hidden" name="damaged_item_ids[]" value="[]" data-damaged-ids-hidden="{{ $key }}">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm mt-2 js-rack-move-pick-items"
                                        data-row-index="{{ $key }}"
                                        data-condition="{{ $cond }}"
                                        data-product-id="{{ $productId }}"
                                        data-product-code="{{ e($code) }}"
                                        data-product-name="{{ e($name) }}"
                                    >
                                        <i class="bi bi-grid"></i> Pick Items
                                    </button>
                                    <div class="small text-muted mt-1">
                                        {{ strtoupper($cond) }} moves by selected item IDs. Selected:
                                        <span data-selected-count="{{ $key }}">0</span>
                                    </div>
                                    <div class="small text-muted text-break" data-selected-ids-label="{{ $key }}">IDs: -</div>
                                @endif
                            </td>

                            <td class="align-middle text-center">
                                <div class="btn-group">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary"
                                        title="Add same product with another condition"
                                        wire:click="splitProduct({{ $key }})"
                                    >
                                        <i class="bi bi-plus-lg"></i>
                                    </button>

                                    <button type="button" class="btn btn-danger" wire:click="removeProduct({{ $key }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="7" class="text-center">
                            <span class="text-danger">Please search & select products!</span>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="rackMovePickItemsModal" tabindex="-1" role="dialog" aria-labelledby="rackMovePickItemsModalLabel" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content" style="border-radius:12px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title font-weight-bold" id="rackMovePickItemsModalLabel">Pick Items</h5>
                        <div class="text-muted small">
                            Filter berdasarkan <b>Warehouse / Rack / Condition</b>.
                            <span class="ml-2 badge badge-light border">GOOD: input qty</span>
                            <span class="ml-1 badge badge-light border">DEFECT: 1 pc / ID</span>
                            <span class="ml-1 badge badge-light border">DAMAGED: 1 pc / ID</span>
                        </div>
                        <div class="small text-muted mt-1" id="rackMovePickItemsSubtitle">Select item IDs to move.</div>
                    </div>
                    <div class="d-flex align-items-center" style="gap:10px;">
                        <span class="badge badge-light border px-3 py-2" id="rackMovePickCounter">Selected 0</span>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="row">
                            <div class="col-lg-3 mb-2">
                                <label class="small text-muted mb-1">Warehouse</label>
                                <select class="form-control" id="rackMoveFilterWarehouse"></select>
                                <small class="text-muted">Locked to selected source.</small>
                            </div>
                            <div class="col-lg-3 mb-2">
                                <label class="small text-muted mb-1">Rack</label>
                                <select class="form-control" id="rackMoveFilterRack"></select>
                                <small class="text-muted">Locked to selected source rack.</small>
                            </div>
                            <div class="col-lg-3 mb-2">
                                <label class="small text-muted mb-1">Condition</label>
                                <select class="form-control" id="rackMoveFilterCondition"></select>
                                <small class="text-muted">Locked to this movement row.</small>
                            </div>
                            <div class="col-lg-3 mb-2">
                                <label class="small text-muted mb-1">Search</label>
                                <input type="text" class="form-control" id="rackMovePickSearch" placeholder="ID, code, type, note">
                                <div class="mt-2 d-flex justify-content-end" style="gap:8px;">
                                    <button type="button" class="btn btn-light border" id="rackMoveResetFilters">Reset</button>
                                    <button type="button" class="btn btn-primary" id="rackMoveApplyFilters">Apply</button>
                                </div>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">
                            Required Total: <b id="rackMoveRequiredTotal">0</b>
                            &nbsp; &bull; &nbsp; GOOD: <b id="rackMoveGoodTotal">0</b>
                            &nbsp; &bull; &nbsp; DEFECT: <b id="rackMoveDefTotal">0</b>
                            &nbsp; &bull; &nbsp; DAMAGED: <b id="rackMoveDamTotal">0</b>
                        </div>
                    </div>

                    <div id="rackMovePickList">
                        <div class="text-muted">No data loaded.</div>
                    </div>

                    <div class="text-muted small mt-2">
                        * Save akan menyimpan pilihan item ID ke baris Rack Movement. Backend tetap memvalidasi source rack saat submit.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-secondary" id="rackMovePickApply">Save Selection</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            if (window.__rackMovePickerBooted) return;
            window.__rackMovePickerBooted = true;

            const pickerUrl = @json(route('inventory.rack-movements.picker-data'));
            window.__rackMoveSelections = window.__rackMoveSelections || {};
            let currentRow = null;
            let currentCondition = null;
            let currentItems = [];
            let currentStockSummary = null;

            function selectedSet(row) {
                window.__rackMoveSelections[row] = window.__rackMoveSelections[row] || [];
                return new Set((window.__rackMoveSelections[row] || []).map(Number));
            }

            function setSelection(row, ids) {
                window.__rackMoveSelections[row] = Array.from(new Set((ids || []).map(Number).filter(Boolean)));
                syncRow(row);
            }

            function syncRow(row) {
                const ids = window.__rackMoveSelections[row] || [];
                const qty = ids.length;
                const qtyVisible = document.querySelector(`[data-quality-qty="${row}"]`);
                const qtyHidden = document.querySelector(`[data-quality-qty-hidden="${row}"]`);
                const defectHidden = document.querySelector(`[data-defect-ids-hidden="${row}"]`);
                const damagedHidden = document.querySelector(`[data-damaged-ids-hidden="${row}"]`);
                const countEl = document.querySelector(`[data-selected-count="${row}"]`);
                const labelEl = document.querySelector(`[data-selected-ids-label="${row}"]`);
                const rowEl = document.querySelector(`[data-rack-move-row="${row}"]`);
                const condition = rowEl ? rowEl.getAttribute('data-condition') : '';

                if (qtyVisible) qtyVisible.value = qty;
                if (qtyHidden) qtyHidden.value = qty;
                if (defectHidden) defectHidden.value = condition === 'defect' ? JSON.stringify(ids) : '[]';
                if (damagedHidden) damagedHidden.value = condition === 'damaged' ? JSON.stringify(ids) : '[]';
                if (countEl) countEl.textContent = qty;
                if (labelEl) labelEl.textContent = ids.length ? ('IDs: ' + ids.join(', ')) : 'IDs: -';
            }

            function currentFilter() {
                const el = document.getElementById('rackMovePickSearch');
                return ((el && el.value) || '').trim().toLowerCase();
            }

            function currentConditionFilter() {
                const el = document.getElementById('rackMoveFilterCondition');
                const selected = ((el && el.value) || '').trim().toLowerCase();
                return selected || currentCondition;
            }

            function renderList() {
                const wrap = document.getElementById('rackMovePickList');
                if (!wrap) return;

                const filter = currentFilter();
                const conditionFilter = currentConditionFilter();
                const selected = selectedSet(currentRow);
                const rows = currentItems.filter(function (item) {
                    if (conditionFilter && String(item.condition || '').toLowerCase() !== conditionFilter) {
                        return false;
                    }
                    if (!filter) return true;
                    return [
                        item.id,
                        item.product_code,
                        item.product_name,
                        item.condition,
                        item.quality_text,
                        item.description,
                        item.warehouse,
                        item.rack
                    ].join(' ').toLowerCase().includes(filter);
                });

                const stock = currentStockSummary || {
                    warehouse_label: 'Source Warehouse',
                    rack_label: 'Source Rack',
                    good: 0,
                    defect: 0,
                    damaged: 0
                };

                let html = `
                    <div class="small text-muted mb-2">
                        List item sesuai source rack. GOOD tetap dipindahkan dari input qty pada baris utama; DEFECT/DAMAGED dipilih 1 pc per ID.
                    </div>
                    <div class="border rounded mb-3 bg-white">
                        <div class="px-3 py-2 border-bottom d-flex flex-wrap align-items-center justify-content-between" style="gap:8px;">
                            <div>
                                <div class="font-weight-bold">${escapeHtml(stock.warehouse_label || 'Source Warehouse')}</div>
                                <div class="small text-muted">${escapeHtml(stock.rack_label || 'Source Rack')}</div>
                            </div>
                            <div class="d-flex flex-wrap" style="gap:6px;">
                                <span class="badge badge-success">GOOD ${Number(stock.good || 0)}</span>
                                <span class="badge badge-warning">DEFECT ${Number(stock.defect || 0)}</span>
                                <span class="badge badge-danger">DAMAGED ${Number(stock.damaged || 0)}</span>
                            </div>
                        </div>
                        <div class="p-3">
                `;

                if (!rows.length) {
                    html += '<div class="text-muted px-2 py-2">No available items found for this filter.</div>';
                } else {
                    html += `
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <span class="badge ${currentCondition === 'defect' ? 'badge-warning' : 'badge-danger'}">${String(currentCondition || '').toUpperCase()}</span>
                                <span class="text-muted small ml-1">(pick ID = 1 pc)</span>
                            </div>
                            <label class="small mb-0">
                                <input type="checkbox" id="rackMovePickAll"> Select visible
                            </label>
                        </div>
                    `;

                    rows.forEach(function (item) {
                        const photo = item.photo_url
                            ? `<a href="${item.photo_url}" target="_blank" rel="noopener">View</a>`
                            : '<span class="text-muted">No photo</span>';
                        const checked = selected.has(Number(item.id)) ? 'checked' : '';
                        const checkboxId = `rack_move_unit_${currentRow}_${item.id}`;
                        const badgeClass = String(item.condition || '').toLowerCase() === 'defect' ? 'badge-warning' : 'badge-danger';

                        html += `
                            <div class="custom-control custom-checkbox mb-2 border rounded px-3 py-2" data-pick-row="${item.id}">
                                <input type="checkbox"
                                       class="custom-control-input js-rack-move-unit-check"
                                       id="${checkboxId}"
                                       value="${item.id}"
                                       ${checked}>
                                <label class="custom-control-label w-100" for="${checkboxId}">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between" style="gap:8px;">
                                        <div>
                                            <div class="font-weight-bold">
                                                ID: ${Number(item.id || 0)}
                                                <span class="badge ${badgeClass} ml-1">${escapeHtml(item.condition || '-')}</span>
                                            </div>
                                            <div class="small text-muted">
                                                ${escapeHtml(item.product_code || '-')} - ${escapeHtml(item.product_name || '-')}
                                            </div>
                                            <div class="small">
                                                <b>Type/Reason:</b> ${escapeHtml(item.quality_text || '-')}
                                                ${item.description ? ` | <b>Note:</b> ${escapeHtml(item.description)}` : ''}
                                            </div>
                                            <div class="small text-muted">
                                                ${escapeHtml(item.warehouse || '-')} | ${escapeHtml(item.rack || '-')}
                                            </div>
                                        </div>
                                        <div class="small">${photo}</div>
                                    </div>
                                </label>
                            </div>
                        `;
                    });
                }

                html += '</div></div>';
                wrap.innerHTML = html;
                updateCounter();
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function updateCounter() {
                const counter = document.getElementById('rackMovePickCounter');
                if (!counter) return;
                const selectedCount = selectedSet(currentRow).size;
                counter.textContent = 'Selected ' + selectedCount;
                const required = document.getElementById('rackMoveRequiredTotal');
                if (required) required.textContent = selectedCount;
            }

            async function openPicker(button) {
                currentRow = button.getAttribute('data-row-index');
                currentCondition = button.getAttribute('data-condition');
                currentItems = [];
                currentStockSummary = null;

                const fromWarehouseEl = document.getElementById('from_warehouse_id');
                const fromRackEl = document.getElementById('from_rack_id');
                const fromWarehouse = (fromWarehouseEl && fromWarehouseEl.value) || '';
                const fromRack = (fromRackEl && fromRackEl.value) || '';
                const productId = button.getAttribute('data-product-id');
                const productCode = button.getAttribute('data-product-code') || '-';
                const productName = button.getAttribute('data-product-name') || '-';
                const fromWarehouseOption = document.querySelector(`#from_warehouse_id option[value="${fromWarehouse}"]`);
                const fromRackOption = document.querySelector(`#from_rack_id option[value="${fromRack}"]`);
                const fromWarehouseLabel = (fromWarehouseOption && fromWarehouseOption.textContent ? fromWarehouseOption.textContent.trim() : '') || `Warehouse #${fromWarehouse}`;
                const fromRackLabel = (fromRackOption && fromRackOption.textContent ? fromRackOption.textContent.trim() : '') || `Rack #${fromRack}`;

                if (!fromWarehouse || !fromRack) {
                    alert('Please select Source Warehouse and Source Rack first.');
                    return;
                }

                document.getElementById('rackMovePickItemsSubtitle').textContent =
                    `${String(currentCondition).toUpperCase()} | ${productCode} - ${productName}`;
                document.getElementById('rackMovePickSearch').value = '';
                document.getElementById('rackMoveFilterWarehouse').innerHTML = `<option value="${fromWarehouse}">${escapeHtml(fromWarehouseLabel)}</option>`;
                document.getElementById('rackMoveFilterRack').innerHTML = `<option value="${fromRack}">${escapeHtml(fromRackLabel)}</option>`;
                document.getElementById('rackMoveFilterCondition').innerHTML = `<option value="${currentCondition}">${String(currentCondition).toUpperCase()}</option>`;
                document.getElementById('rackMoveRequiredTotal').textContent = selectedSet(currentRow).size;
                document.getElementById('rackMoveGoodTotal').textContent = '0';
                document.getElementById('rackMoveDefTotal').textContent = '0';
                document.getElementById('rackMoveDamTotal').textContent = '0';
                document.getElementById('rackMovePickList').innerHTML =
                    '<div class="text-muted">Loading items...</div>';
                $('#rackMovePickItemsModal').modal('show');

                const url = new URL(pickerUrl, window.location.origin);
                url.searchParams.set('warehouse_id', fromWarehouse);
                url.searchParams.set('rack_id', fromRack);
                url.searchParams.set('product_id', productId);
                url.searchParams.set('condition', currentCondition);

                try {
                    const res = await fetch(url.toString(), {
                        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
                    });
                    const json = await res.json();
                    if (!json || json.success !== true) {
                        throw new Error((json && json.message) || 'Failed to load items.');
                    }
                    currentItems = json.items || [];
                    currentStockSummary = json.stock_summary || null;
                    document.getElementById('rackMoveGoodTotal').textContent = Number((currentStockSummary && currentStockSummary.good) || 0);
                    document.getElementById('rackMoveDefTotal').textContent = Number((currentStockSummary && currentStockSummary.defect) || 0);
                    document.getElementById('rackMoveDamTotal').textContent = Number((currentStockSummary && currentStockSummary.damaged) || 0);
                    renderList();
                } catch (e) {
                    document.getElementById('rackMovePickList').innerHTML =
                        `<div class="text-danger">${escapeHtml(e.message || 'Failed to load items.')}</div>`;
                }
            }

            document.addEventListener('click', function (event) {
                const pickButton = event.target.closest('.js-rack-move-pick-items');
                if (pickButton) {
                    openPicker(pickButton);
                    return;
                }

                if (event.target && event.target.id === 'rackMovePickApply') {
                    syncRow(currentRow);
                    $('#rackMovePickItemsModal').modal('hide');
                    return;
                }

                if (event.target && event.target.id === 'rackMoveApplyFilters') {
                    renderList();
                    return;
                }

                if (event.target && event.target.id === 'rackMoveResetFilters') {
                    const search = document.getElementById('rackMovePickSearch');
                    const condition = document.getElementById('rackMoveFilterCondition');
                    if (search) search.value = '';
                    if (condition) condition.value = currentCondition || '';
                    renderList();
                    return;
                }
            });

            document.addEventListener('change', function (event) {
                if (event.target.classList && event.target.classList.contains('js-rack-move-unit-check')) {
                    const selected = selectedSet(currentRow);
                    const id = Number(event.target.value || 0);
                    if (event.target.checked) selected.add(id);
                    else selected.delete(id);
                    setSelection(currentRow, Array.from(selected));
                    updateCounter();
                    return;
                }

                if (event.target && event.target.id === 'rackMovePickAll') {
                    const visibleIds = Array.from(document.querySelectorAll('.js-rack-move-unit-check')).map(function (el) {
                        return Number(el.value || 0);
                    }).filter(Boolean);
                    const selected = selectedSet(currentRow);
                    visibleIds.forEach(function (id) {
                        if (event.target.checked) selected.add(id);
                        else selected.delete(id);
                    });
                    setSelection(currentRow, Array.from(selected));
                    renderList();
                }
            });

            document.addEventListener('input', function (event) {
                if (event.target && event.target.id === 'rackMovePickSearch') {
                    renderList();
                }
            });

            document.addEventListener('livewire:load', function () {
                Object.keys(window.__rackMoveSelections || {}).forEach(syncRow);
                if (window.Livewire && typeof window.Livewire.hook === 'function') {
                    window.Livewire.hook('message.processed', function () {
                        Object.keys(window.__rackMoveSelections || {}).forEach(syncRow);
                    });
                }
            });
        })();
    </script>
</div>

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

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
            <tr>
                <th style="width:60px">#</th>
                <th>Product</th>
                <th style="width:160px">Code</th>
                <th style="width:140px" class="text-center">Stock</th>
                <th style="width:220px">Rack</th>
                <th style="width:120px" class="text-center">Qty</th>
                <th style="width:90px" class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            @if(!empty($products))
                @foreach($products as $key => $product)
                    @php
                        // Classic Quality: selalu ambil GOOD available
                        $stockLabel    = 'GOOD';
                        $availableQty  = (int)($product['available_qty'] ?? 0);

                        $badgeClass = 'badge-success';

                        $selectedRackId = isset($product['rack_id']) ? (int)$product['rack_id'] : null;
                        $qty = (int)($product['qty'] ?? 1);
                        if ($qty < 1) $qty = 1;

                        $isDefectMode = (($qualityType ?? 'defect') === 'defect');
                        $isDamagedMode = (($qualityType ?? 'defect') === 'damaged');

                        $defects = (array)($product['defects'] ?? []);
                        $defects = array_values($defects);

                        $damagedItems = (array)($product['damaged_items'] ?? []);
                        $damagedItems = array_values($damagedItems);
                    @endphp

                    {{-- ✅ Hidden field product_id tetap perlu --}}
                    <input type="hidden"
                           name="items[{{ $key }}][product_id]"
                           value="{{ (int)($product['id'] ?? 0) }}">

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
                            {{-- ✅ FIX: kasih name agar ikut POST --}}
                            <select class="form-control"
                                    name="items[{{ $key }}][rack_id]"
                                    wire:model="products.{{ $key }}.rack_id"
                                    required>
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
                            {{-- ✅ FIX: kasih name agar qty yang user ketik pasti terkirim --}}
                            <input type="number"
                                   name="items[{{ $key }}][qty]"
                                   min="1"
                                   class="form-control text-center"
                                   style="max-width:110px;margin:0 auto;"
                                   wire:model="products.{{ $key }}.qty"
                                   value="{{ (int)$qty }}">
                            <small class="text-muted d-block mt-1">
                                Max: {{ (int)$availableQty }}
                            </small>
                        </td>

                        <td class="text-center">
                            <button type="button" class="btn btn-danger" wire:click="removeProduct({{ $key }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- DETAIL ROW: DEFECT / DAMAGED per unit (WAJIB) --}}
                    <tr>
                        <td colspan="7">
                            @if($isDefectMode)
                                <div class="mb-2 font-weight-bold">Defect Details (per unit) — Qty: {{ (int)$qty }}</div>

                                @for($i = 0; $i < $qty; $i++)
                                    @php
                                        $row = (array)($defects[$i] ?? []);
                                        $defType = (string)($row['defect_type'] ?? '');
                                        $desc = (string)($row['description'] ?? '');
                                    @endphp

                                    <div class="border rounded p-2 mb-2">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="font-weight-bold">Unit #{{ $i + 1 }}</div>
                                            <div class="text-muted small">Photo optional</div>
                                        </div>

                                        <div class="form-row">
                                            <div class="col-md-4">
                                                <label class="mb-1">Defect Type <span class="text-danger">*</span></label>
                                                <input type="text"
                                                       class="form-control"
                                                       name="items[{{ $key }}][defects][{{ $i }}][defect_type]"
                                                       wire:model.lazy="products.{{ $key }}.defects.{{ $i }}.defect_type"
                                                       value="{{ $defType }}"
                                                       required>
                                            </div>

                                            <div class="col-md-5">
                                                <label class="mb-1">Description</label>
                                                <input type="text"
                                                       class="form-control"
                                                       name="items[{{ $key }}][defects][{{ $i }}][description]"
                                                       wire:model.lazy="products.{{ $key }}.defects.{{ $i }}.description"
                                                       value="{{ $desc }}"
                                                       placeholder="Optional...">
                                            </div>

                                            <div class="col-md-3">
                                                <label class="mb-1">Photo</label>
                                                <input type="file"
                                                       class="form-control"
                                                       name="items[{{ $key }}][defects][{{ $i }}][photo]"
                                                       accept="image/*">
                                            </div>
                                        </div>
                                    </div>
                                @endfor
                            @endif

                            @if($isDamagedMode)
                                <div class="mb-2 font-weight-bold">Damaged Details (per unit) — Qty: {{ (int)$qty }}</div>

                                @for($i = 0; $i < $qty; $i++)
                                    @php
                                        $row = (array)($damagedItems[$i] ?? []);
                                        $reason = (string)($row['reason'] ?? '');
                                        $desc = (string)($row['description'] ?? '');
                                        $damageType = strtolower((string)($row['damage_type'] ?? 'damaged'));
                                        if (!in_array($damageType, ['damaged','missing'], true)) $damageType = 'damaged';
                                    @endphp

                                    <div class="border rounded p-2 mb-2">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="font-weight-bold">Unit #{{ $i + 1 }}</div>
                                            <div class="text-muted small">Photo optional</div>
                                        </div>

                                        <div class="form-row">
                                            <div class="col-md-3">
                                                <label class="mb-1">Type</label>
                                                <select class="form-control"
                                                        name="items[{{ $key }}][damaged_items][{{ $i }}][damage_type]">
                                                    <option value="damaged" {{ $damageType === 'damaged' ? 'selected' : '' }}>damaged</option>
                                                    <option value="missing" {{ $damageType === 'missing' ? 'selected' : '' }}>missing</option>
                                                </select>
                                            </div>

                                            <div class="col-md-4">
                                                <label class="mb-1">Reason <span class="text-danger">*</span></label>
                                                <input type="text"
                                                       class="form-control"
                                                       name="items[{{ $key }}][damaged_items][{{ $i }}][reason]"
                                                       wire:model.lazy="products.{{ $key }}.damaged_items.{{ $i }}.reason"
                                                       value="{{ $reason }}"
                                                       required>
                                            </div>

                                            <div class="col-md-3">
                                                <label class="mb-1">Description</label>
                                                <input type="text"
                                                       class="form-control"
                                                       name="items[{{ $key }}][damaged_items][{{ $i }}][description]"
                                                       wire:model.lazy="products.{{ $key }}.damaged_items.{{ $i }}.description"
                                                       value="{{ $desc }}"
                                                       placeholder="Optional...">
                                            </div>

                                            <div class="col-md-2">
                                                <label class="mb-1">Photo</label>
                                                <input type="file"
                                                       class="form-control"
                                                       name="items[{{ $key }}][damaged_items][{{ $i }}][photo]"
                                                       accept="image/*">
                                            </div>
                                        </div>
                                    </div>
                                @endfor
                            @endif

                            @if(!$isDefectMode && !$isDamagedMode)
                                <div class="text-muted small">
                                    Quality classic table hanya aktif untuk type: <b>defect</b> atau <b>damaged</b>.
                                </div>
                            @endif
                        </td>
                    </tr>

                @endforeach
            @else
                <tr>
                    <td colspan="7" class="text-center text-danger">Please search &amp; select products!</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
</div>
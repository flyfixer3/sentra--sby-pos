<div>
    @if (session()->has('message'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="alert-body">
                <span>{{ session('message') }}</span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>
    @endif

    {{-- small helper --}}
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="text-muted small">
            Cari product di atas → otomatis masuk ke table ini.
            <span class="ml-2">Type aktif: <b>{{ strtoupper($qualityType) }}</b></span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered mb-0">
            <thead>
            <tr>
                <th style="width:60px">#</th>
                <th>Product</th>
                <th style="width:160px">Code</th>
                <th style="width:140px" class="text-center">GOOD Stock</th>
                <th style="width:220px">Rack</th>
                <th style="width:110px" class="text-center">Qty</th>
                <th style="width:110px" class="text-center">Action</th>
            </tr>
            </thead>

            <tbody>
            @if(!empty($products))
                @foreach($products as $key => $product)
                    @php
                        $availableGood = (int)($product['available_qty'] ?? 0);
                        $selectedRackId = isset($product['rack_id']) ? (int)$product['rack_id'] : null;
                        $qty = (int)($product['qty'] ?? 1);

                        $isClassic = in_array($qualityType, ['defect','damaged'], true);
                        $isDefect = $qualityType === 'defect';
                        $isDamaged = $qualityType === 'damaged';
                    @endphp

                    <tr>
                        <td>{{ $key + 1 }}</td>

                        <td>
                            <div class="font-weight-bold">{{ $product['product_name'] ?? '-' }}</div>
                            <div class="text-muted small">Selected via Search Product</div>

                            {{-- hidden product_id for submit --}}
                            <input type="hidden" name="items[{{ $key }}][product_id]" value="{{ (int)($product['id'] ?? 0) }}">
                        </td>

                        <td>{{ $product['product_code'] ?? '-' }}</td>

                        <td class="text-center">
                            <span class="badge badge-success">
                                GOOD: {{ $availableGood }}
                            </span>
                        </td>

                        <td>
                            {{-- rack --}}
                            <select
                                class="form-control"
                                name="items[{{ $key }}][rack_id]"
                                wire:model="products.{{ $key }}.rack_id"
                                required>
                                <option value="">-- Select Rack --</option>
                                @foreach(($rackOptions ?? []) as $opt)
                                    <option value="{{ $opt['id'] }}">
                                        {{ $opt['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Required</small>
                        </td>

                        <td class="text-center">
                            <input
                                type="number"
                                class="form-control text-center"
                                style="max-width:90px;margin:0 auto;"
                                name="items[{{ $key }}][qty]"
                                min="1"
                                max="{{ max(1, $availableGood) }}"
                                wire:model.lazy="products.{{ $key }}.qty"
                                required>
                            <small class="text-muted d-block mt-1">≤ GOOD</small>
                        </td>

                        <td class="text-center">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                wire:click="removeProduct({{ $key }})">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </td>
                    </tr>

                    {{-- DETAILS ROW (modern / rapi) --}}
                    @if($isClassic)
                        <tr>
                            <td colspan="7" class="bg-light">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="font-weight-bold text-dark">
                                        {{ $isDefect ? 'Defect Details' : 'Damaged Details' }}
                                        <span class="text-muted small ml-2">(Per Unit)</span>
                                    </div>
                                    <div class="text-muted small">
                                        Qty: <b>{{ $qty }}</b>
                                    </div>
                                </div>

                                <div class="table-responsive mt-2">
                                    <table class="table table-sm table-bordered mb-0 bg-white">
                                        <thead>
                                        <tr>
                                            <th style="width:60px" class="text-center">#</th>

                                            @if($isDefect)
                                                <th style="width:260px">Defect Type <span class="text-danger">*</span></th>
                                            @else
                                                <th style="width:260px">Reason <span class="text-danger">*</span></th>
                                            @endif

                                            <th>Description (optional)</th>
                                            <th style="width:240px">Photo (optional)</th>
                                        </tr>
                                        </thead>

                                        <tbody>
                                        @for($i = 0; $i < $qty; $i++)
                                            <tr>
                                                <td class="text-center">{{ $i + 1 }}</td>

                                                @if($isDefect)
                                                    <td>
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            name="items[{{ $key }}][defects][{{ $i }}][defect_type]"
                                                            wire:model.lazy="products.{{ $key }}.defects.{{ $i }}.defect_type"
                                                            placeholder="bubble / scratch / distortion"
                                                            required>
                                                        <small class="text-muted">Wajib diisi</small>
                                                    </td>
                                                @else
                                                    <td>
                                                        <input type="hidden"
                                                               name="items[{{ $key }}][damaged_items][{{ $i }}][damage_type]"
                                                               value="damaged">
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            name="items[{{ $key }}][damaged_items][{{ $i }}][reason]"
                                                            wire:model.lazy="products.{{ $key }}.damaged_items.{{ $i }}.reason"
                                                            placeholder="contoh: retak / pecah / lecet"
                                                            required>
                                                        <small class="text-muted">Wajib diisi</small>
                                                    </td>
                                                @endif

                                                <td>
                                                    @if($isDefect)
                                                        <textarea
                                                            class="form-control"
                                                            rows="2"
                                                            name="items[{{ $key }}][defects][{{ $i }}][description]"
                                                            wire:model.lazy="products.{{ $key }}.defects.{{ $i }}.description"
                                                            placeholder="Optional..."></textarea>
                                                    @else
                                                        <textarea
                                                            class="form-control"
                                                            rows="2"
                                                            name="items[{{ $key }}][damaged_items][{{ $i }}][description]"
                                                            wire:model.lazy="products.{{ $key }}.damaged_items.{{ $i }}.description"
                                                            placeholder="Optional..."></textarea>
                                                    @endif
                                                </td>

                                                <td>
                                                    @if($isDefect)
                                                        <input
                                                            type="file"
                                                            class="form-control"
                                                            name="items[{{ $key }}][defects][{{ $i }}][photo]"
                                                            accept="image/*">
                                                    @else
                                                        <input
                                                            type="file"
                                                            class="form-control"
                                                            name="items[{{ $key }}][damaged_items][{{ $i }}][photo]"
                                                            accept="image/*">
                                                    @endif
                                                    <small class="text-muted">Max 5MB</small>
                                                </td>
                                            </tr>
                                        @endfor
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            @else
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        Please search &amp; select products!
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
</div>
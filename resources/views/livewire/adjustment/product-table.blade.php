<div>
    @if (session()->has('message'))
        <div class="alert alert-warning">{{ session('message') }}</div>
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

                    @if($mode !== 'quality')
                        <th style="width:140px">Condition</th>
                        <th style="width:190px">Defect Type</th>
                        <th style="width:220px">Damaged Reason</th>
                    @endif

                    <th style="width:140px">Qty</th>
                    <th style="width:160px">Type</th>
                    <th>Note</th>
                    <th style="width:90px" class="text-center">Action</th>
                </tr>
            </thead>

            <tbody>
                @if(!empty($products))
                    @foreach($products as $key => $product)
                        @php
                            $stockLabel = $mode === 'quality' ? (string)($product['stock_label'] ?? 'GOOD') : '';
                            $availableQty = $mode === 'quality' ? (int)($product['available_qty'] ?? 0) : 0;

                            $badgeClass = 'badge-success';
                            if ($stockLabel === 'DEFECT') $badgeClass = 'badge-warning';
                            if ($stockLabel === 'DAMAGED') $badgeClass = 'badge-danger';

                            $selectedRackId = isset($product['rack_id']) ? (int)$product['rack_id'] : null;
                        @endphp

                        <tr class="{{ $mode !== 'quality' ? 'adj-stock-row' : '' }}">
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $product['product_name'] }}</td>
                            <td>{{ $product['product_code'] }}</td>

                            <td class="text-center">
                                @if($mode === 'quality')
                                    <span class="badge {{ $badgeClass }}">
                                        {{ $stockLabel }}: {{ $availableQty }}
                                    </span>
                                @else
                                    <span class="badge badge-info">
                                        {{ number_format((int)($product['stock_qty'] ?? 0)) }} {{ $product['product_unit'] }}
                                    </span>
                                @endif
                            </td>

                            {{-- STOCK mode: kirim array untuk controller store/update --}}
                            @if($mode !== 'quality')
                                <input type="hidden" name="product_ids[]" value="{{ $product['id'] }}">
                            @endif

                            <td>
                                @if($mode === 'quality')
                                    <select
                                        class="form-control"
                                        name="rack_id"
                                        wire:model="products.{{ $key }}.rack_id"
                                        required
                                    >
                                        <option value="">-- Select Rack --</option>
                                        @foreach(($rackOptions ?? []) as $opt)
                                            <option value="{{ $opt['id'] }}" {{ (int)$opt['id'] === (int)$selectedRackId ? 'selected' : '' }}>
                                                {{ $opt['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Required (Quality)</small>
                                @else
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
                                @endif
                            </td>

                            {{-- ✅ NEW columns for STOCK mode only --}}
                            @if($mode !== 'quality')
                                <td>
                                    <select name="conditions[]" class="form-control adj-condition">
                                        <option value="good" selected>GOOD</option>
                                        <option value="defect">DEFECT</option>
                                        <option value="damaged">DAMAGED</option>
                                    </select>
                                    <small class="text-muted">Bucket target</small>
                                </td>

                                <td>
                                    <input type="text"
                                           name="defect_types[]"
                                           class="form-control adj-defect-type"
                                           placeholder="e.g. bubble / scratch"
                                           value="">
                                    <small class="text-muted adj-defect-hint d-none">Required for DEFECT (Add)</small>
                                </td>

                                <td>
                                    <input type="text"
                                           name="damaged_reasons[]"
                                           class="form-control adj-damaged-reason"
                                           placeholder="e.g. pecah sudut / retak"
                                           value="">
                                    <small class="text-muted adj-damaged-hint d-none">Required for DAMAGED (Add)</small>
                                </td>
                            @endif

                            <td>
                                <input
                                    type="number"
                                    min="1"
                                    class="form-control adj-qty"
                                    @if($mode === 'quality')
                                        wire:model="products.{{ $key }}.quantity"
                                    @else
                                        name="quantities[]"
                                        value="{{ $product['quantity'] }}"
                                    @endif
                                >

                                @if($mode === 'quality')
                                    <small class="text-muted">
                                        Max {{ $stockLabel }}: {{ $availableQty }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                @if($mode === 'quality')
                                    <input type="text" class="form-control" value="Quality Reclass" disabled>
                                @else
                                    <select name="types[]" class="form-control adj-type">
                                        <option value="add" {{ $product['type']==='add'?'selected':'' }}>Add</option>
                                        <option value="sub" {{ $product['type']==='sub'?'selected':'' }}>Sub</option>
                                    </select>
                                @endif
                            </td>

                            <td>
                                @if($mode === 'quality')
                                    <input type="text" class="form-control" wire:model.lazy="products.{{ $key }}.note">
                                @else
                                    <input type="text" name="notes[]" class="form-control" value="{{ $product['note'] }}">
                                @endif
                            </td>

                            <td class="text-center">
                                <button type="button" class="btn btn-danger"
                                        wire:click="removeProduct({{ $key }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        @php
                            $colspan = $mode !== 'quality' ? 12 : 9;
                        @endphp
                        <td colspan="{{ $colspan }}" class="text-center text-danger">
                            Please search & select products!
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- ✅ JS kecil untuk enforce required field (client-side) biar user kebantu --}}
    @if($mode !== 'quality')
        <script>
            (function(){
                function refreshRow(row){
                    const cond = row.querySelector('.adj-condition');
                    const type = row.querySelector('.adj-type');

                    const defectInput = row.querySelector('.adj-defect-type');
                    const damagedInput = row.querySelector('.adj-damaged-reason');

                    const defectHint = row.querySelector('.adj-defect-hint');
                    const damagedHint = row.querySelector('.adj-damaged-hint');

                    if(!cond || !type) return;

                    const c = String(cond.value || 'good').toLowerCase();
                    const t = String(type.value || 'add').toLowerCase();

                    // Default: not required
                    if(defectInput){ defectInput.required = false; }
                    if(damagedInput){ damagedInput.required = false; }

                    if(defectHint) defectHint.classList.add('d-none');
                    if(damagedHint) damagedHint.classList.add('d-none');

                    // Required hanya untuk ADD
                    if(t === 'add' && c === 'defect'){
                        if(defectInput) defectInput.required = true;
                        if(defectHint) defectHint.classList.remove('d-none');
                    }

                    if(t === 'add' && c === 'damaged'){
                        if(damagedInput) damagedInput.required = true;
                        if(damagedHint) damagedHint.classList.remove('d-none');
                    }
                }

                function bind(){
                    document.querySelectorAll('tr.adj-stock-row').forEach(row => {
                        const cond = row.querySelector('.adj-condition');
                        const type = row.querySelector('.adj-type');

                        if(cond){
                            cond.addEventListener('change', function(){ refreshRow(row); });
                        }
                        if(type){
                            type.addEventListener('change', function(){ refreshRow(row); });
                        }

                        refreshRow(row);
                    });
                }

                // delay dikit biar aman kalau livewire re-render
                setTimeout(bind, 50);
            })();
        </script>
    @endif
</div>

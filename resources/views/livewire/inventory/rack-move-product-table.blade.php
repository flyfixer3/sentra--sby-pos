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
                            $unit      = $p['product_unit'] ?? '';

                            $stockRack = (int)($p['stock_qty'] ?? 0);
                            $qtyInput  = (int)($p['quantity'] ?? 1);
                            if ($qtyInput < 1) $qtyInput = 1;

                            $cond = strtolower((string)($p['condition'] ?? 'good'));
                            if (!in_array($cond, ['good','defect','damaged'], true)) $cond = 'good';
                        @endphp

                        <tr>
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
                                <input
                                    type="number"
                                    name="quantities[]"
                                    min="1"
                                    max="{{ max(0, (int)$stockRack) }}"
                                    class="form-control"
                                    value="{{ $qtyInput }}"
                                    wire:model.lazy="products.{{ $key }}.quantity"
                                >
                                <div class="small text-muted mt-1">
                                    Max: {{ (int)$stockRack }} {{ $unit }}
                                </div>
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
</div>
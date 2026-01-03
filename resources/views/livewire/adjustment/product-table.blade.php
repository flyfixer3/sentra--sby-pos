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
                        @endphp

                        <tr>
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
                                        {{ $product['product_quantity'] }} {{ $product['product_unit'] }}
                                    </span>
                                @endif
                            </td>

                            {{-- Stock mode: kirim array untuk controller store/update --}}
                            @if($mode !== 'quality')
                                <input type="hidden" name="product_ids[]" value="{{ $product['id'] }}">
                            @endif

                            <td>
                                <input
                                    type="number"
                                    min="1"
                                    class="form-control"
                                    @if($mode === 'quality')
                                        wire:model.lazy="products.{{ $key }}.quantity"
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
                                    <select name="types[]" class="form-control">
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
                        <td colspan="8" class="text-center text-danger">
                            Please search & select products!
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

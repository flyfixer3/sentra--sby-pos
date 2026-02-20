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
                <th style="width:90px" class="text-center">Action</th>
            </tr>
            </thead>
            <tbody>
            @if(!empty($products))
                @foreach($products as $key => $product)
                    @php
                        $stockLabel = (string)($product['stock_label'] ?? 'GOOD');
                        $availableQty = (int)($product['available_qty'] ?? 0);

                        $badgeClass = 'badge-success';
                        if ($stockLabel === 'DEFECT') $badgeClass = 'badge-warning';
                        if ($stockLabel === 'DAMAGED') $badgeClass = 'badge-danger';

                        $selectedRackId = isset($product['rack_id']) ? (int)$product['rack_id'] : null;
                    @endphp
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
                            <select class="form-control" name="rack_id" wire:model="products.{{ $key }}.rack_id" required>
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
                            <button type="button" class="btn btn-danger" wire:click="removeProduct({{ $key }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="6" class="text-center text-danger">Please search &amp; select products!</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
</div>
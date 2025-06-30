<div>
    @include('livewire.modals.search-product') <!-- Gaya kamu -->
    
    <button type="button" class="btn btn-info mb-3" wire:click="$emit('openProductSearchModal')">
        + Add Product
    </button>

    @if (count($products) > 0)
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Product</th>
                    <th width="150px">Quantity</th>
                    <th width="50px">#</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $index => $item)
                    <tr>
                        <td>
                            {{ $item['name'] }}
                            <input type="hidden" name="product_ids[]" value="{{ $item['id'] }}">
                        </td>
                        <td>
                            <input type="number" name="quantities[]" class="form-control" wire:model="products.{{ $index }}.quantity" min="1">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger" wire:click="removeProduct({{ $index }})">
                                <i class="bi bi-x"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-muted">No products selected.</div>
    @endif
</div>

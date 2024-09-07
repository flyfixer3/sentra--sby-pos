<div class="input-group">
    <select disabled wire:model="warehouse_id.{{ $cart_item->id }}"
    wire:change="updateWarehouse('{{ $cart_item->rowId }}', '{{ $cart_item->id }}', $event.target.value)" class="form-control" required>
        @foreach($warehouses as $warehouse)
            <option {{ $warehouse->id == 99 ? 'selected' : '' }} value="{{ $warehouse->id }}">{{ $warehouse->warehouse_name }}</option>
        @endforeach
    </select>
</div>
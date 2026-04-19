<input
    wire:model.defer="quantity.{{ $cart_item->id }}"
    wire:change="updateQuantity('{{ $cart_item->rowId }}', '{{ $cart_item->id }}')"
    style="min-width: 40px;max-width: 90px;"
    type="number"
    class="form-control"
    value="{{ $cart_item->qty }}"
    min="1"
>

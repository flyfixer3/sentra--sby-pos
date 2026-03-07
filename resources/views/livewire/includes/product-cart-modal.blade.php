@php
    $pid = (int) $cart_item->id;

    $isPurchaseOrderCart = isset($cart_instance) && $cart_instance === 'purchase_order';
    $dtype = $discount_type[$pid] ?? ($cart_item->options->product_discount_type ?? 'fixed');

    $currentUnitPrice = (float) ($cart_item->options->unit_price ?? 0);
    if ($currentUnitPrice <= 0) {
        $currentUnitPrice = (float) ($cart_item->price ?? 0);
    }

    $currentRowPrice = (float) ($cart_item->price ?? 0);
    $currentDiscount = (float) ($cart_item->options->product_discount ?? 0);
@endphp

<span
    wire:click="$emitSelf('discountModalRefresh', '{{ $pid }}', '{{ $cart_item->rowId }}')"
    role="button"
    class="badge badge-warning pointer-event"
    data-toggle="modal"
    data-target="#discountModal{{ $pid }}"
>
    <i class="bi bi-pencil-square text-white"></i>
</span>

<div wire:ignore.self class="modal fade" id="discountModal{{ $pid }}" tabindex="-1" role="dialog" aria-labelledby="discountModalLabel{{ $pid }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="discountModalLabel{{ $pid }}">
                    {{ $cart_item->name }}
                    <br>
                    <span class="badge badge-success">
                        {{ $cart_item->options->code }}
                    </span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form wire:submit.prevent="setProductDiscount('{{ $cart_item->rowId }}', '{{ $pid }}')" method="POST">
                <div class="modal-body">

                    @if (session()->has('discount_message' . $pid))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <div class="alert-body">
                                <span>{{ session('discount_message' . $pid) }}</span>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($isPurchaseOrderCart)
                        <div class="alert alert-info">
                            <strong>Purchase Order Edit Mode</strong><br>
                            Field fixed di sini adalah <strong>harga beli / purchase unit price</strong>, bukan nominal diskon.
                        </div>
                    @endif

                    <div class="form-group">
                        <label>
                            {{ $isPurchaseOrderCart ? 'Change By' : 'Discount By' }}
                            <span class="text-danger">*</span>
                        </label>
                        <select wire:model="discount_type.{{ $pid }}" class="form-control" required>
                            @if($isPurchaseOrderCart)
                                <option value="fixed">Fixed Purchase Price</option>
                                <option value="percentage">Discount Percentage</option>
                            @else
                                <option value="fixed">Fixed Buy Price</option>
                                <option value="percentage">Percentage</option>
                            @endif
                        </select>
                    </div>

                    @if($isPurchaseOrderCart)
                        <div class="mb-2">
                            <small class="text-muted">
                                Current purchase unit price:
                                <strong>{{ format_currency($currentUnitPrice) }}</strong>
                            </small>
                        </div>
                    @endif

                    <div class="form-group">
                        @if($dtype === 'percentage')
                            <label>
                                {{ $isPurchaseOrderCart ? 'Discount (%) from Purchase Price' : 'Discount (%)' }}
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                wire:model.defer="item_discount.{{ $pid }}"
                                type="number"
                                class="form-control"
                                min="0"
                                max="100"
                                step="0.01"
                            >
                        @else
                            <label>
                                {{ $isPurchaseOrderCart ? 'Purchase Unit Price' : 'Buying Price' }}
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                wire:model.defer="item_discount.{{ $pid }}"
                                type="number"
                                class="form-control"
                                placeholder="{{ $isPurchaseOrderCart ? $currentUnitPrice : max(($currentRowPrice - $currentDiscount), 0) }}"
                                min="0"
                                step="0.01"
                            >
                        @endif
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">
                        {{ $isPurchaseOrderCart ? 'Save Purchase Price' : 'Save changes' }}
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
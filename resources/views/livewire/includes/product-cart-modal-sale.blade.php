<!-- Button trigger Modal -->
@php
    $isPurchaseCart = isset($cart_instance) && $cart_instance === 'purchase';
    $isPurchasePriceLocked = $isPurchaseCart && !empty($lock_purchase_price_edit);
    $lineKey = $lineKey ?? (string) ($cart_item->options->line_key ?? $cart_item->rowId);
    $safeLineKey = $safeLineKey ?? preg_replace('/[^A-Za-z0-9_-]/', '_', $lineKey);
    $discountModalId = 'discountModal' . $safeLineKey;
    $discountModalLabelId = 'discountModalLabel' . $safeLineKey;
@endphp

@if($isPurchasePriceLocked)
    <span
        role="button"
        class="badge badge-secondary"
        data-toggle="modal"
        data-target="#{{ $discountModalId }}"
        title="Purchase item price is locked because linked Purchase Delivery is partial."
    >
        <i class="bi bi-lock-fill text-white"></i>
    </span>
@else
    <span
        wire:click="$emitSelf('discountModalRefresh', '{{ $cart_item->id }}', '{{ $cart_item->rowId }}', '{{ $lineKey }}')"
        role="button"
        class="badge badge-warning pointer-event"
        data-toggle="modal"
        data-target="#{{ $discountModalId }}"
    >
        <i class="bi bi-pencil-square text-white"></i>
    </span>
@endif

<!-- Modal -->
<div wire:ignore.self class="modal fade" id="{{ $discountModalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $discountModalLabelId }}" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            @php
                $currentUnitPrice = (float) ($cart_item->options->unit_price ?? 0);
                if ($currentUnitPrice <= 0) {
                    $currentUnitPrice = (float) ($cart_item->price ?? 0);
                }

                $currentRowPrice  = (float) ($cart_item->price ?? 0);
                $currentDiscount  = (float) ($cart_item->options->product_discount ?? 0);
                $currentDiscountType = $discount_type[$lineKey] ?? ($cart_item->options->product_discount_type ?? 'fixed');
            @endphp

            <div class="modal-header">
                <h5 class="modal-title" id="{{ $discountModalLabelId }}">
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

            <form wire:submit.prevent="setProductDiscount('{{ $cart_item->rowId }}', '{{ $cart_item->id }}', '{{ $lineKey }}')" method="POST">
                <div class="modal-body">
                    @if (session()->has('discount_message' . $lineKey))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <div class="alert-body">
                                <span>{{ session('discount_message' . $lineKey) }}</span>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($isPurchaseCart)
                        <div class="alert alert-info">
                            <strong>Purchase Edit Mode:</strong><br>
                            Field ini mengubah <strong>harga beli item</strong> pada cart purchase.
                            Nilai ini nanti disimpan ke <strong>purchase detail</strong>, bukan mengubah harga jual master product.
                        </div>
                    @endif

                    @if($isPurchasePriceLocked)
                        <div class="alert alert-warning">
                            <strong>Price Locked:</strong><br>
                            Linked Purchase Delivery is already in <strong>partial</strong> status, so purchase item price can no longer be edited.
                        </div>
                    @endif

                    <div class="form-group">
                        <label>
                            {{ $isPurchaseCart ? 'Change By' : 'Discount By' }}
                            <span class="text-danger">*</span>
                        </label>

                        <select wire:model="discount_type.{{ $lineKey }}" class="form-control" required {{ $isPurchasePriceLocked ? 'disabled' : '' }}>
                            @if($isPurchaseCart)
                                <option value="fixed">Fixed Purchase Price</option>
                                <option value="percentage">Discount Percentage</option>
                            @else
                                <option value="fixed">Fixed Sell Price</option>
                                <option value="percentage">Percentage</option>
                            @endif
                        </select>
                    </div>

                    @if($isPurchaseCart)
                        <div class="mb-2">
                            <small class="text-muted">
                                Current purchase unit price:
                                <strong>{{ format_currency($currentUnitPrice) }}</strong>
                            </small>
                        </div>
                    @endif

                    <div class="form-group">
                        @if($currentDiscountType === 'percentage')
                            <label>
                                {{ $isPurchaseCart ? 'Discount (%) from Purchase Price' : 'Discount (%)' }}
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                wire:model.defer="item_discount.{{ $lineKey }}"
                                type="number"
                                class="form-control"
                                min="0"
                                max="100"
                                step="0.01"
                                {{ $isPurchasePriceLocked ? 'disabled' : '' }}
                            >
                        @else
                            <label>
                                {{ $isPurchaseCart ? 'Purchase Unit Price' : 'Sell Price' }}
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                wire:model.defer="item_discount.{{ $lineKey }}"
                                type="number"
                                class="form-control"
                                placeholder="{{ $isPurchaseCart ? $currentUnitPrice : max(($currentRowPrice - $currentDiscount), 0) }}"
                                step="0.01"
                                min="0"
                                {{ $isPurchasePriceLocked ? 'disabled' : '' }}
                            >
                        @endif
                    </div>

                    @php
                        $wid  = $warehouse_id[$lineKey] ?? ($warehouse_id[$cart_item->id] ?? null);
                        $wrec = null;

                        if ($wid) {
                            $wrec = \Modules\Product\Entities\Warehouse::query()
                                ->select('id', 'warehouse_code')
                                ->find($wid);
                        }

                        $warehouseCode = $wrec->warehouse_code ?? null;
                    @endphp

                    @if($warehouseCode === 'KS')
                        <div class="form-group">
                            <label>Konsyinasi Cost <span class="text-danger">*</span></label>
                            <input
                                wire:model.defer="item_cost_konsyinasi.{{ $lineKey }}"
                                type="number"
                                class="form-control"
                                placeholder="0"
                                step="0.01"
                                {{ $isPurchasePriceLocked ? 'disabled' : '' }}
                            >
                        </div>
                    @endif
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" {{ $isPurchasePriceLocked ? 'disabled' : '' }}>
                        {{ $isPurchaseCart ? 'Save Purchase Price' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

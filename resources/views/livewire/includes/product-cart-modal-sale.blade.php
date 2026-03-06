<!-- Button trigger Modal -->
<span
    wire:click="$emitSelf('discountModalRefresh', '{{ $cart_item->id }}', '{{ $cart_item->rowId }}')"
    role="button"
    class="badge badge-warning pointer-event"
    data-toggle="modal"
    data-target="#discountModal{{ $cart_item->id }}"
>
    <i class="bi bi-pencil-square text-white"></i>
</span>

<!-- Modal -->
<div wire:ignore.self class="modal fade" id="discountModal{{ $cart_item->id }}" tabindex="-1" role="dialog" aria-labelledby="discountModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            @php
                $isPurchaseCart = isset($cart_instance) && $cart_instance === 'purchase';
                $currentUnitPrice = (float) ($cart_item->options->unit_price ?? 0);
                $currentRowPrice  = (float) ($cart_item->price ?? 0);
                $currentDiscount  = (float) ($cart_item->options->product_discount ?? 0);
            @endphp

            <div class="modal-header">
                <h5 class="modal-title" id="discountModalLabel">
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

            <form wire:submit.prevent="setProductDiscount('{{ $cart_item->rowId }}', '{{ $cart_item->id }}')" method="POST">
                <div class="modal-body">
                    @if (session()->has('discount_message' . $cart_item->id))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <div class="alert-body">
                                <span>{{ session('discount_message' . $cart_item->id) }}</span>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($isPurchaseCart)
                        <div class="alert alert-info">
                            <strong>Purchase Edit Mode:</strong><br>
                            Field ini akan mengubah <strong>harga beli item</strong> pada cart purchase.
                            Jika invoice purchase nanti di-save, perubahan ini akan ikut terbaca pada proses koreksi HPP.
                        </div>
                    @endif

                    <div class="form-group">
                        <label>
                            {{ $isPurchaseCart ? 'Change By' : 'Discount By' }}
                            <span class="text-danger">*</span>
                        </label>

                        <select wire:model="discount_type.{{ $cart_item->id }}" class="form-control" required>
                            @if($isPurchaseCart)
                                <option value="fixed">Fixed Purchase Price</option>
                                <option value="percentage">Discount Percentage</option>
                            @else
                                <option value="fixed">Fixed Sell Price</option>
                                <option value="percentage">Percentage</option>
                            @endif
                        </select>
                    </div>

                    <div class="form-group">
                        @if(($discount_type[$cart_item->id] ?? null) === 'percentage')
                            <label>
                                {{ $isPurchaseCart ? 'Discount (%) from Purchase Price' : 'Discount (%)' }}
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                wire:model.defer="item_discount.{{ $cart_item->id }}"
                                type="number"
                                class="form-control"
                                value="{{ $item_discount[$cart_item->id] ?? '' }}"
                                min="0"
                                max="100"
                                step="0.01"
                            >
                        @elseif(($discount_type[$cart_item->id] ?? null) === 'fixed')
                            <label>
                                {{ $isPurchaseCart ? 'Purchase Unit Price' : 'Sell Price' }}
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                wire:model.defer="item_discount.{{ $cart_item->id }}"
                                type="number"
                                class="form-control"
                                value="{{
                                    $isPurchaseCart
                                        ? ($item_discount[$cart_item->id] ?? $currentUnitPrice)
                                        : (
                                            isset($item_discount[$cart_item->id]) && ($item_discount[$cart_item->id] ?? 0) != 0
                                                ? ($currentRowPrice - $currentDiscount)
                                                : ''
                                        )
                                }}"
                                placeholder="0"
                                step="0.01"
                                min="0"
                            >
                        @endif
                    </div>

                    @php
                        $wid  = $warehouse_id[$cart_item->id] ?? null;
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
                                wire:model.defer="item_cost_konsyinasi.{{ $cart_item->id }}"
                                type="number"
                                class="form-control"
                                value="{{ $item_cost_konsyinasi[$cart_item->id] ?? '' }}"
                                placeholder="0"
                                step="0.01"
                            >
                        </div>
                    @endif
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">
                        {{ $isPurchaseCart ? 'Save Purchase Price' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

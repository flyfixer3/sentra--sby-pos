@php
    $pid = (int) $cart_item->id;

    // ✅ SAFE DEFAULT biar gak "Undefined array key"
    $dtype = $discount_type[$pid] ?? 'fixed';
    $ival  = $item_discount[$pid] ?? 0;
@endphp

<!-- Button trigger Discount Modal -->
<span wire:click="$emitSelf('discountModalRefresh', '{{ $pid }}', '{{ $cart_item->rowId }}')" role="button" class="badge badge-warning pointer-event" data-toggle="modal" data-target="#discountModal{{ $pid }}">
    <i class="bi bi-pencil-square text-white"></i>
</span>

<!-- Discount Modal -->
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

                    <div class="form-group">
                        <label>Discount By <span class="text-danger">*</span></label>
                        <select wire:model="discount_type.{{ $pid }}" class="form-control" required>
                            <option value="fixed">Fixed Buy Price</option>
                            <option value="percentage">Percentage</option>
                        </select>
                    </div>

                    <div class="form-group">
                        {{-- ✅ SAFE: jangan akses $discount_type[$pid] langsung --}}
                        @if(($discount_type[$pid] ?? $dtype) == 'percentage')
                            <label>Discount(%) <span class="text-danger">*</span></label>
                            <input
                                wire:model.defer="item_discount.{{ $pid }}"
                                type="number"
                                class="form-control"
                                value="{{ $item_discount[$pid] ?? $ival }}"
                                min="0"
                                max="100"
                            >
                        @else
                            <label>Buying Price <span class="text-danger">*</span></label>
                            <input
                                wire:model.defer="item_discount.{{ $pid }}"
                                type="number"
                                class="form-control"
                                value="{{ ($item_discount[$pid] ?? $ival) == 0 ? '' : ($cart_item->price - ($item_discount[$pid] ?? $ival)) }}"
                                placeholder="0"
                                min="0"
                            >
                        @endif
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>

        </div>
    </div>
</div>

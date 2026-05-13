@php
    $purchaseOrderForModal = $purchaseOrderForModal ?? $purchase_order ?? $data ?? null;
    $modalId = $modalId ?? ('markSentToSupplierModal' . optional($purchaseOrderForModal)->id);
@endphp

@if($purchaseOrderForModal)
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('purchase-orders.mark-sent-to-supplier', $purchaseOrderForModal->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}Label">Mark as Sent to Supplier</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        This only marks the PO as sent to the supplier. It does not receive stock and does not create Purchase Delivery.
                    </p>
                    <div class="form-group mb-0">
                        <label for="{{ $modalId }}Note">Note</label>
                        <textarea
                            id="{{ $modalId }}Note"
                            name="sent_to_supplier_note"
                            class="form-control"
                            rows="4"
                            maxlength="1000"
                            placeholder="Optional note"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Mark as Sent</button>
                </div>
            </form>
        </div>
    </div>
@endif

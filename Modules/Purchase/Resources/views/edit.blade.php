@extends('layouts.app')

@section('title', 'Edit Purchase')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <livewire:search-product/>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')

                        @php
                            $isAdmin = $canManageHppSensitiveEdit ?? false;

                            $pdStatus = null;
                            if (!empty($purchase->purchase_delivery_id)) {
                                $pd = \Modules\PurchaseDelivery\Entities\PurchaseDelivery::find($purchase->purchase_delivery_id);
                                $pdStatus = $pd ? strtolower(trim((string) $pd->status)) : null;
                            }

                            $pdConfirmed = in_array($pdStatus, ['partial', 'received', 'completed'], true);
                            $pdPartial = $pdStatus === 'partial';
                        @endphp

                        @if($pdConfirmed)
                            <div class="alert alert-info">
                                <div style="font-weight:700;">Info:</div>
                                <div>
                                    Related Purchase Delivery has already been confirmed.
                                    You can still edit <b>simple fields</b> such as note / supplier invoice / due date.
                                </div>
                                @if($pdPartial)
                                    <div class="mt-1">
                                        Linked Purchase Delivery is already <b>partial</b>. <b>Item price is locked</b> and can no longer be edited in this Purchase.
                                    </div>
                                @else
                                    <div class="mt-1">
                                        Changes to <b>item price / qty / item list</b> are considered <b>HPP-sensitive</b> and are allowed only for <b>Administrator</b>.
                                    </div>
                                @endif
                            </div>
                        @endif

                        <form id="purchase-form" action="{{ route('purchases.update', $purchase) }}" method="POST">
                            @csrf
                            @method('put')

                            @if($isAdmin)
                                <input type="hidden" name="is_admin_ui" value="1">
                            @endif

                            <div class="form-row">
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required value="{{ old('reference', $purchase->reference) }}" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="reference_supplier">Supplier Invoice <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference_supplier" value="{{ old('reference_supplier', $purchase->reference_supplier) }}">
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                        <select class="form-control" name="supplier_id" id="supplier_id" required>
                                            @foreach(\Modules\People\Entities\Supplier::all() as $supplier)
                                                <option
                                                    value="{{ $supplier->id }}"
                                                    {{ (string) old('supplier_id', $purchase->supplier_id) === (string) $supplier->id ? 'selected' : '' }}>
                                                    {{ $supplier->supplier_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label for="date">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ old('date', $purchase->date) }}">
                                    </div>
                                </div>

                                <div class="col-lg-1">
                                    <div class="form-group">
                                        <label for="due_date">Due Date (Days) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="due_date" required value="{{ old('due_date', $purchase->due_date) }}">
                                    </div>
                                </div>
                            </div>

                            <livewire:product-cart-purchase
                                :cartInstance="'purchase'"
                                :data="$purchase"
                                :loading_warehouse="$loadingWarehouseId"
                                :stock_mode="$stock_mode"
                                :lock_purchase_price_edit="$pdPartial"
                            />

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="status">Status <span class="text-danger">*</span></label>
                                        <select class="form-control" name="status" id="status" required>
                                            <option value="Pending" {{ old('status', $purchase->status) == 'Pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="Ordered" {{ old('status', $purchase->status) == 'Ordered' ? 'selected' : '' }}>Ordered</option>
                                            <option value="Completed" {{ old('status', $purchase->status) == 'Completed' ? 'selected' : '' }}>Completed</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="payment_method" required value="{{ old('payment_method', $purchase->payment_method) }}" readonly>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="paid_amount">Amount Received <span class="text-danger">*</span></label>
                                        <input id="paid_amount" type="text" class="form-control" name="paid_amount" required value="{{ old('paid_amount', $purchase->paid_amount) }}" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control">{{ old('note', $purchase->note) }}</textarea>
                            </div>

                            @if($isAdmin)
                                <div class="card mt-3 border-warning">
                                    <div class="card-header bg-warning text-dark" style="font-weight: 700;">
                                        Administrator Only — HPP Sensitive Edit
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning mb-3">
                                            <div style="font-weight:700;">Warning:</div>
                                            <div>
                                                If you change <b>item price / qty / item list</b>, the system will create
                                                an <b>HPP correction ledger</b> and refresh same-day sale cost snapshot.
                                            </div>
                                            <div class="mt-1" style="font-size: 12px;">
                                                TODO future: validate against shift closing / reopen day flow.
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="edit_reason" style="font-weight:700;">
                                                Reason / Note for HPP-Sensitive Edit
                                            </label>
                                            <textarea
                                                name="edit_reason"
                                                id="edit_reason"
                                                rows="4"
                                                class="form-control"
                                                placeholder="Example: Supplier invoice price was wrong, corrected based on actual invoice..."
                                            >{{ old('edit_reason') }}</textarea>
                                        </div>

                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="confirm_recalculate_hpp" name="confirm_recalculate_hpp" {{ old('confirm_recalculate_hpp') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="confirm_recalculate_hpp" style="font-weight:700;">
                                                I understand this will correct HPP ledger and update same-day sale product cost snapshot.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    Update Purchase <i class="bi bi-check"></i>
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    <script src="{{ asset('js/jquery-mask-money.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('#paid_amount').maskMoney({
                prefix:'{{ settings()->currency->symbol }}',
                thousands:'{{ settings()->currency->thousand_separator }}',
                decimal:'{{ settings()->currency->decimal_separator }}',
                allowZero: true,
                precision: 0,
            });

            $('#paid_amount').maskMoney('mask');

            $('#purchase-form').submit(function () {
                var paid_amount = $('#paid_amount').maskMoney('destroy')[0];
                var raw = paid_amount.value.toString().replaceAll(/[Rp.]/g, "");
                var new_number = parseInt(raw || 0);
                $('#paid_amount').val(new_number);
            });
        });
    </script>
@endpush

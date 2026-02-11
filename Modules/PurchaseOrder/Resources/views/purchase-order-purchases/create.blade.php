@extends('layouts.app')

@section('title', 'Create Purchase From Purchase Order')

@push('page_css')
    <style>
        .sa-page { padding-bottom: 24px; }
        .sa-card { border: 1px solid rgba(0,0,0,.06); border-radius: 14px; box-shadow: 0 6px 18px rgba(0,0,0,.04); }
        .sa-card-header {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(0,0,0,.06);
            display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
        }
        .sa-title { margin:0; font-weight:700; font-size:16px; }
        .sa-subtitle { margin:2px 0 0; font-size:12px; color:#6c757d; }
        .sa-badge {
            display:inline-flex; align-items:center; gap:6px;
            padding:6px 10px; border-radius:999px; font-size:12px;
            background: rgba(13,110,253,.08);
            color: #0d6efd;
            border: 1px solid rgba(13,110,253,.18);
            white-space: nowrap;
        }
        .sa-section { padding: 16px; }
        .sa-section + .sa-section { border-top: 1px dashed rgba(0,0,0,.10); }
        .sa-section-title {
            font-weight:700;
            font-size:13px;
            margin:0 0 10px;
            color:#212529;
            display:flex; align-items:center; gap:8px;
        }
        .sa-help { font-size:12px; color:#6c757d; margin:0 0 10px; }
        .sa-form-label { font-size:12px; color:#6c757d; margin-bottom:6px; }
        .sa-actions {
            padding: 14px 16px;
            border-top: 1px solid rgba(0,0,0,.06);
            display:flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            background: #fff;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
        }
        .sa-actions .left { font-size:12px; color:#6c757d; }
        .sa-actions .right { display:flex; gap:8px; align-items:center; }
        .sa-note { resize: vertical; min-height: 90px; }
        .sa-input-readonly { background: #f8f9fa; }
    </style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchase-orders.index') }}">Purchase Orders</a></li>
        <li class="breadcrumb-item active">Make Purchase</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid sa-page mb-4">

        <div class="row">
            <div class="col-12">
                <livewire:search-product/>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card sa-card">
                    <div class="sa-card-header">
                        <div>
                            <h3 class="sa-title">Create Purchase</h3>
                            <p class="sa-subtitle">
                                This page pre-fills items from the selected Purchase Order. Stock is shown as total from all warehouses in the active branch.
                            </p>
                        </div>

                        <div class="sa-badge">
                            <i class="bi bi-receipt"></i>
                            PO: {{ make_reference_id('PO', $purchaseOrder->id) }}
                        </div>
                    </div>

                    <div class="card-body p-0">
                        @include('utils.alerts')

                        <form id="purchase-form" action="{{ route('purchases.store') }}" method="POST">
                            @csrf

                            <div class="sa-section">
                                <div class="sa-section-title">
                                    <i class="bi bi-file-earmark-text"></i> Document Info
                                </div>
                                <p class="sa-help">Some fields are auto-selected from the PO. You can adjust payment and notes before saving.</p>

                                <div class="row g-3">
                                    <div class="col-12 col-lg-2">
                                        <label class="sa-form-label">Reference</label>
                                        <input type="text" class="form-control sa-input-readonly" name="reference" required readonly value="PR">
                                    </div>

                                    <div class="col-12 col-lg-2">
                                        <label class="sa-form-label">Purchase Order</label>
                                        <input type="text" class="form-control sa-input-readonly" required readonly
                                               value="{{ make_reference_id('PO', $purchaseOrder->id) }}">
                                    </div>

                                    <div class="col-12 col-lg-3">
                                        <label class="sa-form-label">Supplier Invoice</label>
                                        <input type="text" class="form-control" name="reference_supplier" placeholder="e.g. INV/XYG/00123">
                                        <small class="text-muted">Optional: Supplier invoice number (if any).</small>
                                    </div>

                                    <div class="col-12 col-lg-3">
                                        <label class="sa-form-label">Supplier</label>
                                        <select class="form-control" name="supplier_id" id="supplier_id" required>
                                            @foreach(\Modules\People\Entities\Supplier::all() as $supplier)
                                                <option {{ $purchaseOrder->supplier_id == $supplier->id ? 'selected' : '' }}
                                                        value="{{ $supplier->id }}">
                                                    {{ $supplier->supplier_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-12 col-lg-2">
                                        <label class="sa-form-label">Date</label>
                                        <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                    </div>

                                    <div class="col-12 col-lg-2">
                                        <label class="sa-form-label">Due Date (Days)</label>
                                        <input type="number" class="form-control" name="due_date" required placeholder="0" min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="sa-section">
                                <div class="sa-section-title">
                                    <i class="bi bi-cart3"></i> Items
                                </div>
                                <p class="sa-help">Items are taken from cart instance <b>purchase</b>. You can add/remove items using the search above.</p>

                                {{-- ✅ pakai komponen yang benar --}}
                                <livewire:product-cart-purchase :cartInstance="'purchase'" :data="null" />
                            </div>

                            <div class="sa-section">
                                <div class="sa-section-title">
                                    <i class="bi bi-credit-card"></i> Payment & Status
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-lg-4">
                                        <label class="sa-form-label">Status</label>
                                        <select class="form-control" name="status" required>
                                            <option value="Pending">Pending</option>
                                            <option value="Completed">Completed</option>
                                        </select>
                                        <small class="text-muted">
                                            If you set <b>Completed</b>, the invoice can affect stock mutation (depending on your current rules).
                                        </small>
                                    </div>

                                    <div class="col-12 col-lg-4">
                                        <label class="sa-form-label">Payment Method</label>
                                        <select class="form-control" name="payment_method" id="payment_method" required>
                                            <option value="Cash">Cash</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Cheque">Cheque</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>

                                    <div class="col-12 col-lg-4">
                                        <label class="sa-form-label">Amount Received</label>
                                        <div class="input-group">
                                            <input id="paid_amount" type="text" class="form-control" name="paid_amount" required placeholder="0">
                                            <button id="getTotalAmount" class="btn btn-outline-primary" type="button" title="Set full amount">
                                                <i class="bi bi-check2-square"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Click the check button to auto-fill the total amount.</small>
                                    </div>

                                    <div class="col-12">
                                        <label class="sa-form-label">Note</label>
                                        <textarea name="note" id="note" rows="4" class="form-control sa-note" placeholder="Optional note..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}">

                            <div class="sa-actions">
                                <div class="left">
                                    Current Stock uses active branch total (ALL warehouses). Placement will be assigned to your default warehouse on save.
                                </div>
                                <div class="right">
                                    <a href="{{ route('purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-light">
                                        Back to PO
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        Create Purchase <i class="bi bi-check"></i>
                                    </button>
                                </div>
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
    function initPaidAmountMask() {
        const $paid = $('#paid_amount');
        if (!$paid.length) return;

        $paid.maskMoney({
            prefix:'{{ settings()->currency->symbol }}',
            thousands:'{{ settings()->currency->thousand_separator }}',
            decimal:'{{ settings()->currency->decimal_separator }}',
            allowZero: true,
            precision: 0,
        });
    }

    $(document).on('click', '#getTotalAmount', function () {
        const raw = $('input[name="total_amount"]').val() || '0';
        const num = parseInt(raw.toString().replace(/[^\d]/g, ''), 10) || 0;
        $('#paid_amount').maskMoney('mask', num);
    });

    $(function () {
        initPaidAmountMask();

        $('#purchase-form').on('submit', function () {
            const paid_amount = $('#paid_amount').maskMoney('destroy')[0];
            const new_number = parseInt((paid_amount.value || '').toString().replace(/[^\d]/g, ''), 10) || 0;
            $('#paid_amount').val(new_number);
        });
    });

    document.addEventListener("livewire:load", function () {
        initPaidAmountMask();
        if (window.Livewire) {
            window.Livewire.hook('message.processed', function () {
                initPaidAmountMask();
            });
        }
    });
</script>
@endpush

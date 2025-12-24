@extends('layouts.app')

@section('title', 'Create Purchase')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    @php
        // Ambil cabang aktif dari session (sesuaikan dengan proyekmu)
        $activeBranchId = session('active_branch_id') ?? session('active_branch') ?? null;

        // Cari gudang utama (is_main = 1) di cabang aktif
        $defaultWarehouse = \Modules\Product\Entities\Warehouse::query()
            ->when($activeBranchId, fn($q) => $q->where('branch_id', $activeBranchId))
            ->where('is_main', 1)
            ->orderBy('id')
            ->first(); // <- tidak pakai findOrFail agar aman null

        // Ambil daftar gudang untuk dropdown (urut pakai warehouse_name, bukan name)
        $warehouses = \Modules\Product\Entities\Warehouse::query()
            ->when($activeBranchId, fn($q) => $q->where('branch_id', $activeBranchId))
            ->orderBy('warehouse_name')
            ->get();
    @endphp

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
                        <form id="purchase-form" action="{{ route('purchases.store') }}" method="POST">
                            @csrf

                            <div class="form-row">
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label>Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required readonly value="PR">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label>Supplier Invoice <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference_supplier">
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>Supplier <span class="text-danger">*</span></label>
                                        <select class="form-control" name="supplier_id" id="supplier_id" required>
                                            @foreach(\Modules\People\Entities\Supplier::orderBy('supplier_name')->get() as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->supplier_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <div class="form-group">
                                        <label>Due Date (Days) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="due_date" required placeholder="0">
                                    </div>
                                </div>
                            </div>

                            {{-- Dropdown gudang tujuan penerimaan (pakai warehouse_name) --}}
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Receive To Warehouse <span class="text-danger">*</span></label>
                                        <select class="form-control" name="receive_warehouse_id" id="receive_warehouse_id" required>
                                            <option value="">— Select Warehouse —</option>
                                            @foreach($warehouses as $wh)
                                                <option value="{{ $wh->id }}"
                                                    {{ $defaultWarehouse && $defaultWarehouse->id === $wh->id ? 'selected' : '' }}>
                                                    {{ $wh->warehouse_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Barang hasil pembelian akan masuk ke gudang ini.</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Penting: kirim object/nullable ke Livewire TANPA findOrFail --}}
                            <livewire:product-cart-purchase
                                :cartInstance="'purchase'"
                                :data="null"
                                :loading_warehouse="$defaultWarehouse"
                            />

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Status <span class="text-danger">*</span></label>
                                        <select class="form-control" name="status" id="status" required>
                                            <option value="Pending">Pending</option>
                                            <option value="Ordered">Ordered</option>
                                            <option value="Completed">Completed</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-4" hidden>
                                    <div class="form-group">
                                        <label>Payment Method <span class="text-danger">*</span></label>
                                        <select class="form-control" name="payment_method" id="payment_method" required>
                                            <option selected>-</option>
                                            @foreach(
                                                \App\Models\AccountingSubaccount::join('accounting_accounts','accounting_accounts.id','=','accounting_subaccounts.accounting_account_id')
                                                ->where('accounting_accounts.is_active', 1)
                                                ->where('accounting_accounts.account_number', 3)
                                                ->select('accounting_subaccounts.*','accounting_accounts.account_number')
                                                ->orderBy('subaccount_number')
                                                ->get() as $account)
                                                <option value="{{ $account->id }}">({{ $account->subaccount_number }}) - {{ $account->subaccount_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label>Amount Paid <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input id="paid_amount" type="text" class="form-control" name="paid_amount" value="0" required>
                                            <div class="input-group-append">
                                                <button id="getTotalAmount" class="btn btn-primary" type="button">
                                                    <i class="bi bi-check-square"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control"></textarea>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    Create Purchase <i class="bi bi-check"></i>
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
        $(function () {
            $('#paid_amount').maskMoney({
                prefix:'{{ settings()->currency->symbol }}',
                thousands:'{{ settings()->currency->thousand_separator }}',
                decimal:'{{ settings()->currency->decimal_separator }}',
                allowZero: true,
                precision: 0,
            });

            $('#getTotalAmount').click(function () {
                $('#paid_amount').maskMoney('mask', {{ Cart::instance('purchase')->total() }});
            });

            $('#purchase-form').on('submit', function () {
                var paid_amount = $('#paid_amount').maskMoney('destroy')[0];
                var new_number = parseInt((paid_amount.value || '').toString().replaceAll(/[Rp.]/g, "")) || 0;
                $('#paid_amount').val(new_number);
            });
        });
    </script>
@endpush

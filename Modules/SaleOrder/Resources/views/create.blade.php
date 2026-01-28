@extends('layouts.app')

@section('title', 'Create Sale Order')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sale-orders.index') }}">Sale Orders</a></li>
        <li class="breadcrumb-item active">Create</li>
    </ol>
@endsection

@section('content')
@php
    $items = old('items', $prefillItems ?? []);
    if (!is_array($items) || count($items) === 0) {
        $items = [['product_id'=>'','quantity'=>1,'price'=>0]];
    }
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">

            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center">
                    <div>
                        <strong>Create Sale Order</strong>
                        @if(!empty($prefillRefText))
                            <div class="text-muted small">{{ $prefillRefText }}</div>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    <form action="{{ route('sale-orders.store') }}" method="POST" id="soForm">
                        @csrf

                        <input type="hidden" name="source" value="{{ $source }}">

                        @if($source === 'quotation')
                            <input type="hidden" name="quotation_id" value="{{ request('quotation_id') }}">
                        @endif

                        @if($source === 'sale')
                            <input type="hidden" name="sale_id" value="{{ request('sale_id') }}">
                        @endif

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control"
                                       value="{{ old('date', $prefillDate) }}" required>
                            </div>

                            <div class="col-md-5 mb-3">
                                <label class="form-label">Customer</label>
                                <select name="customer_id" class="form-control" required>
                                    <option value="">-- Choose --</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}"
                                            {{ (int) old('customer_id', $prefillCustomerId) === (int) $c->id ? 'selected' : '' }}>
                                            {{ $c->customer_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Default Warehouse (optional)</label>
                                <select name="warehouse_id" class="form-control">
                                    <option value="">-- None --</option>
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w->id }}"
                                            {{ (int) old('warehouse_id', $prefillWarehouseId) === (int) $w->id ? 'selected' : '' }}>
                                            {{ $w->warehouse_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="small text-muted mt-1">
                                    Warehouse ini hanya anchor default. Sale Delivery tetap bisa pilih warehouse saat dibuat.
                                </div>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">Note</label>
                                <textarea name="note" class="form-control" rows="2">{{ old('note', $prefillNote) }}</textarea>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex align-items-center justify-content-between">
                            <div><strong>Items</strong></div>
                            <div class="text-muted small">Pilih product, qty, dan price. Kamu bisa tambah/hapus baris.</div>
                        </div>

                        <div class="table-responsive mt-2">
                            <table class="table table-bordered" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 55%;">Product</th>
                                        <th style="width: 20%;">Qty</th>
                                        <th style="width: 20%;">Price</th>
                                        <th style="width: 5%;" class="text-center">#</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($items as $i => $row)
                                    <tr>
                                        <td>
                                            <select name="items[{{ $i }}][product_id]" class="form-control" required>
                                                <option value="">-- Choose Product --</option>
                                                @foreach(($products ?? []) as $p)
                                                    <option value="{{ $p->id }}"
                                                        {{ (int)($row['product_id'] ?? 0) === (int)$p->id ? 'selected' : '' }}>
                                                        {{ $p->product_name }} @if(!empty($p->product_code)) ({{ $p->product_code }}) @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="small text-muted mt-1">
                                                Kalau list product terlalu panjang, nanti kita upgrade jadi searchable select.
                                            </div>
                                        </td>
                                        <td>
                                            <input type="number" name="items[{{ $i }}][quantity]" class="form-control"
                                                   value="{{ (int)($row['quantity'] ?? 1) }}" min="1" required>
                                        </td>
                                        <td>
                                            <input type="number" name="items[{{ $i }}][price]" class="form-control"
                                                   value="{{ (int)($row['price'] ?? 0) }}" min="0">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger btn-remove-row" title="Remove">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-info" id="btnAddRow">
                                <i class="bi bi-plus-circle"></i> Add Row
                            </button>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-save"></i> Save Sale Order
                            </button>
                            <a href="{{ route('sale-orders.index') }}" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('page_scripts')
<script>
(function(){
    const tableBody = document.querySelector('#itemsTable tbody');
    const addBtn = document.getElementById('btnAddRow');

    function reIndex(){
        const rows = Array.from(tableBody.querySelectorAll('tr'));
        rows.forEach((tr, idx) => {
            const selects = tr.querySelectorAll('select, input');
            selects.forEach(el => {
                const name = el.getAttribute('name') || '';
                // items[0][product_id] => items[idx][product_id]
                const newName = name.replace(/items\[\d+\]/g, 'items['+idx+']');
                el.setAttribute('name', newName);
            });
        });
    }

    function addRow(){
        const idx = tableBody.querySelectorAll('tr').length;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="items[${idx}][product_id]" class="form-control" required>
                    <option value="">-- Choose Product --</option>
                    @foreach(($products ?? []) as $p)
                        <option value="{{ $p->id }}">{{ $p->product_name }} @if(!empty($p->product_code)) ({{ $p->product_code }}) @endif</option>
                    @endforeach
                </select>
                <div class="small text-muted mt-1">
                    Kalau list product terlalu panjang, nanti kita upgrade jadi searchable select.
                </div>
            </td>
            <td>
                <input type="number" name="items[${idx}][quantity]" class="form-control" value="1" min="1" required>
            </td>
            <td>
                <input type="number" name="items[${idx}][price]" class="form-control" value="0" min="0">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger btn-remove-row" title="Remove">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tableBody.appendChild(tr);
        bindRemoveButtons();
        reIndex();
    }

    function bindRemoveButtons(){
        const btns = tableBody.querySelectorAll('.btn-remove-row');
        btns.forEach(btn => {
            btn.onclick = function(){
                const rows = tableBody.querySelectorAll('tr');
                if (rows.length <= 1) {
                    alert('Minimal harus ada 1 item.');
                    return;
                }
                btn.closest('tr').remove();
                reIndex();
            };
        });
    }

    if (addBtn) addBtn.addEventListener('click', addRow);
    bindRemoveButtons();
})();
</script>
@endpush

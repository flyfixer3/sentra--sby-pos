@extends('layouts.app')

@section('title', 'Create Sale Delivery')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sale-deliveries.index') }}">Sale Deliveries</a></li>
    <li class="breadcrumb-item active">Create</li>
</ol>
@endsection

@section('content')
@php
    $source = request('source');
    $isSaleOrder = ($source === 'sale_order');

    // customer selected:
    $selectedCustomerId = $isSaleOrder
        ? (int) ($prefillCustomerId ?? 0)
        : (int) old('customer_id');

    // items:
    $items = old('items') ?? ($prefillItems ?? []);
    if (!is_array($items) || count($items) === 0) {
        $items = [
            ['product_id' => null, 'quantity' => 1],
        ];
    }

    // idx start for JS
    $idxStart = is_array(old('items'))
        ? count(old('items'))
        : (is_array($prefillItems ?? null) ? count($prefillItems) : 1);

    if (!$idxStart || $idxStart < 1) $idxStart = 1;
@endphp

<div class="container-fluid">
    @include('utils.alerts')

    <div class="card">
        <div class="card-body">
            <form action="{{ route('sale-deliveries.store') }}" method="POST">
                @csrf

                {{-- source --}}
                <input type="hidden" name="source" value="{{ $source }}">

                {{-- source ids (always send) --}}
                <input type="hidden" name="quotation_id" value="{{ request('quotation_id') }}">
                <input type="hidden" name="sale_id" value="{{ request('sale_id') }}">
                <input type="hidden" name="sale_order_id" value="{{ request('sale_order_id') }}">

                @if(request('source') === 'sale_order')
                    <div class="alert alert-info d-flex align-items-center mb-3">
                        <i class="bi bi-file-earmark-text me-2 fs-5"></i>
                        <div>
                            <div class="fw-bold">Sale Order Reference</div>
                            <div class="small text-muted">
                                This delivery is created from
                                <span class="fw-bold">{{ $prefillSaleOrderRef ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control"
                               value="{{ old('date', now()->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-9">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>

                        <select name="customer_id"
                                class="form-control"
                                required
                                {{ $isSaleOrder ? 'disabled' : '' }}>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}"
                                    {{ (int) $selectedCustomerId === (int) $c->id ? 'selected' : '' }}>
                                    {{ $c->customer_name }}
                                </option>
                            @endforeach
                        </select>

                        {{-- disabled tidak ikut submit --}}
                        @if($isSaleOrder)
                            <input type="hidden" name="customer_id" value="{{ (int) ($prefillCustomerId ?? 0) }}">
                        @endif
                    </div>

                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Optional">{{ old('note') }}</textarea>
                    </div>
                </div>

                <hr class="my-3">

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <h6 class="mb-0">Items</h6>
                        <small class="text-muted">
                            {{ $isSaleOrder ? 'Items are prefilled from Sale Order (remaining qty).' : 'Add products and quantities.' }}
                        </small>
                    </div>

                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            id="add-row"
                            {{ $isSaleOrder ? 'disabled' : '' }}>
                        Add Item <i class="bi bi-plus-lg"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="items-table">
                        <thead>
                            <tr>
                                <th style="width:70%">Product</th>
                                <th style="width:20%">Qty</th>
                                <th class="text-end" style="width:10%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $i => $row)
                                @php
                                    $pid = is_array($row) ? ($row['product_id'] ?? null) : null;
                                    $qty = is_array($row) ? ($row['quantity'] ?? 1) : 1;
                                @endphp
                                <tr>
                                    <td>
                                        <select name="items[{{ $i }}][product_id]"
                                                class="form-control"
                                                required
                                                {{ $isSaleOrder ? 'disabled' : '' }}>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}"
                                                    {{ (int) $pid === (int) $p->id ? 'selected' : '' }}>
                                                    {{ $p->product_name }} ({{ $p->product_code }})
                                                </option>
                                            @endforeach
                                        </select>

                                        @if($isSaleOrder)
                                            <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ (int) $pid }}">
                                        @endif
                                    </td>

                                    <td>
                                        <input type="number"
                                               name="items[{{ $i }}][quantity]"
                                               class="form-control"
                                               min="1"
                                               value="{{ (int) $qty }}"
                                               required>
                                    </td>

                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger remove-row"
                                                {{ ($isSaleOrder || $i === 0) ? 'disabled' : '' }}>
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary" type="submit">
                        Create Delivery <i class="bi bi-check-lg"></i>
                    </button>
                    <a href="{{ route('sale-deliveries.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page_scripts')
<script>
(function(){
    let idx = {{ (int)$idxStart }};

    function addRow(){
        const tbody = document.querySelector('#items-table tbody');
        const tr = document.createElement('tr');

        tr.innerHTML = `
            <td>
                <select name="items[${idx}][product_id]" class="form-control" required>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->product_name }} ({{ $p->product_code }})</option>
                    @endforeach
                </select>
            </td>
            <td>
                <input type="number" name="items[${idx}][quantity]" class="form-control" min="1" value="1" required>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row">Remove</button>
            </td>
        `;

        tbody.appendChild(tr);
        idx++;
    }

    document.addEventListener('click', function(e){
        if (e.target && (e.target.id === 'add-row' || e.target.closest('#add-row'))) {
            addRow();
        }

        const rm = e.target && (e.target.classList.contains('remove-row') ? e.target : e.target.closest('.remove-row'));
        if (rm) {
            const tr = rm.closest('tr');
            const tbody = document.querySelector('#items-table tbody');
            if (tbody && tbody.querySelectorAll('tr').length > 1) {
                tr.remove();
            }
        }
    });
})();
</script>
@endpush

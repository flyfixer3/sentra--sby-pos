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
<div class="container-fluid">
    @include('utils.alerts')

    <div class="card">
        <div class="card-body">
            <form action="{{ route('sale-deliveries.store') }}" method="POST">
                @csrf
                <input type="hidden" name="source" value="{{ request('source') }}">
                <input type="hidden" name="quotation_id" value="{{ request('quotation_id') }}">
                <input type="hidden" name="sale_id" value="{{ request('sale_id') }}">
                {{-- Catatan: form kamu masih “manual pilih customer & items”. Nanti kalau kamu sudah integrasi dari quotation/sale, biasanya customer/items akan di-lock atau auto-filled. --}}

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-control" required>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->customer_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Warehouse (Stock Out) <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-control" required>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->warehouse_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Optional"></textarea>
                    </div>
                </div>

                <hr class="my-3">

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div>
                        <h6 class="mb-0">Items</h6>
                        <small class="text-muted">Add products and quantities.</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-row">
                        Add Item <i class="bi bi-plus-lg"></i>
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="items-table">
                        <thead>
                            <tr>
                                <th style="width:60%">Product</th>
                                <th style="width:20%">Qty</th>
                                <th class="text-end" style="width:20%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select name="items[0][product_id]" class="form-control" required>
                                        @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->product_name }} ({{ $p->product_code }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[0][quantity]" class="form-control" min="1" value="1" required>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-row" disabled>
                                        Remove
                                    </button>
                                </td>
                            </tr>
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
    let idx = 1;
    const tableBody = document.querySelector('#items-table tbody');

    document.getElementById('add-row').addEventListener('click', function(){
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
        tableBody.appendChild(tr);
        idx++;
    });

    document.addEventListener('click', function(e){
        if(!e.target.classList.contains('remove-row')) return;
        const tr = e.target.closest('tr');
        if(tr) tr.remove();
    });
})();
</script>
@endpush

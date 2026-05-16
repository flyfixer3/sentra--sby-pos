@extends('layouts.app')

@section('title', 'Sale Orders')

@push('page_css')
<style>
    .index-filter-card {
        border: 1px solid rgba(0, 0, 0, .06);
        border-radius: .75rem;
        background: linear-gradient(180deg, #fbfcfe 0%, #f5f7fb 100%);
        padding: 1rem 1rem .9rem;
        margin-bottom: 1rem;
    }
    .index-filter-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .75rem;
    }
    .index-filter-card .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: .35rem;
    }
    .index-filter-card .form-control {
        border-radius: .65rem;
        min-height: 38px;
    }
    .index-filter-actions {
        display: flex;
        gap: .5rem;
        align-items: end;
        justify-content: flex-end;
    }
</style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Sale Orders</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center">
                    <div class="text-muted">All Sale Orders</div>
                    <div class="mfs-auto d-flex gap-2">
                        @can('create_sale_orders')
                            <a href="{{ route('sale-orders.create') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-journal-plus"></i> Create
                            </a>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    <div class="index-filter-card">
                        <div class="index-filter-title">Table Filter</div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4 col-lg-3">
                                <label for="sale-order-shortage-filter" class="form-label">Stock Status</label>
                                <select id="sale-order-shortage-filter" class="form-control">
                                    <option value="all">All</option>
                                    <option value="shortage">Shortage only</option>
                                    <option value="normal">Non-shortage / resolved only</option>
                                </select>
                            </div>

                            <div class="col-md-4 col-lg-3">
                                <label class="form-label">Filter Summary</label>
                                <div class="form-control d-flex align-items-center bg-white">
                                    <i class="bi bi-funnel mr-2 text-muted"></i>
                                    <span class="text-muted small">Track Sale Orders with pending stock.</span>
                                </div>
                            </div>

                            <div class="col-md-4 col-lg-6">
                                <div class="index-filter-actions">
                                    <button type="button" class="btn btn-outline-secondary" id="sale-order-shortage-filter-reset">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('includes.status-legend', [
                        'id' => 'saleOrderStatusLegend',
                        'title' => 'Sale Order Status Meaning',
                        'items' => [
                            [
                                'status' => 'pending',
                                'badge_class' => 'badge badge-warning',
                                'meaning' => 'Sale Order is created, but no confirmed delivery quantity has been fulfilled yet.',
                                'trigger' => 'Default status, or fulfillment returns to no delivered quantity.',
                            ],
                            [
                                'status' => 'partial_delivered',
                                'badge_class' => 'badge badge-info',
                                'meaning' => 'Some ordered quantity has been confirmed as delivered, with remaining quantity still open.',
                                'trigger' => 'Confirmed delivery quantity is greater than zero but less than total ordered quantity.',
                            ],
                            [
                                'status' => 'delivered',
                                'badge_class' => 'badge badge-primary',
                                'meaning' => 'All ordered quantity has been delivered, but not all confirmed deliveries are invoiced yet.',
                                'trigger' => 'All quantities are delivered, while at least one confirmed delivery is not linked to a Sales Invoice.',
                            ],
                            [
                                'status' => 'completed',
                                'badge_class' => 'badge badge-success',
                                'meaning' => 'Sale Order is fully delivered and all confirmed deliveries are invoiced.',
                                'trigger' => 'All ordered quantity is delivered and every confirmed delivery is linked to a Sales Invoice.',
                            ],
                            [
                                'status' => 'cancelled',
                                'badge_class' => 'badge badge-danger',
                                'meaning' => 'Sale Order was cancelled.',
                                'trigger' => 'Existing status display supports cancelled orders.',
                            ],
                        ],
                    ])

                    {!! $dataTable->table() !!}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_scripts')
{!! $dataTable->scripts() !!}
<script>
(function () {
    function dt() {
        return window.LaravelDataTables && window.LaravelDataTables["sale-orders-table"]
            ? window.LaravelDataTables["sale-orders-table"]
            : null;
    }

    function bind() {
        var table = dt();
        if (!table) return;

        table.on('preXhr.dt', function (e, settings, data) {
            data.shortage_filter = document.getElementById('sale-order-shortage-filter')?.value || 'all';
        });

        document.getElementById('sale-order-shortage-filter')?.addEventListener('change', function () {
            table.ajax.reload(null, true);
        });

        document.getElementById('sale-order-shortage-filter-reset')?.addEventListener('click', function () {
            var filter = document.getElementById('sale-order-shortage-filter');
            if (filter) {
                filter.value = 'all';
            }
            table.ajax.reload(null, true);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tries = 0;
        var timer = setInterval(function () {
            tries++;
            if (dt()) {
                clearInterval(timer);
                bind();
            }
            if (tries > 20) clearInterval(timer);
        }, 100);
    });
})();
</script>
@endpush

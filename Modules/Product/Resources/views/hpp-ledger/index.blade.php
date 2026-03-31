@extends('layouts.app')

@section('title', 'HPP Ledger')

@push('page_css')
<style>
    .hpp-filter-card {
        border: 1px solid rgba(0, 0, 0, .06);
        border-radius: 14px;
        background: linear-gradient(180deg, #fbfcfe 0%, #f5f7fb 100%);
        padding: 1rem 1rem .9rem;
        margin-bottom: 1rem;
    }
    .hpp-filter-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .75rem;
    }
    .hpp-filter-card .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: .35rem;
    }
    .hpp-filter-card .form-control,
    .hpp-filter-card .form-select {
        border-radius: .65rem;
        min-height: 38px;
    }
    .hpp-source-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(0, 0, 0, .03);
        color: #343a40;
        text-transform: uppercase;
    }
    .hpp-source-badge--pd { background: rgba(25, 135, 84, .10); color: #146c43; border-color: rgba(25, 135, 84, .18); }
    .hpp-source-badge--correction { background: rgba(255, 193, 7, .14); color: #7a5d00; border-color: rgba(255, 193, 7, .25); }
    .hpp-source-badge--default { background: rgba(13, 110, 253, .08); color: #0a58ca; border-color: rgba(13, 110, 253, .18); }
    .hpp-product-code {
        display: inline-flex;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        border: 1px solid rgba(25,135,84,.25);
        background: rgba(25,135,84,.10);
        color: #146c43;
    }
    .hpp-row-changed {
        background: rgba(255, 193, 7, .06);
    }
    .hpp-card {
        border: 1px solid rgba(0, 0, 0, .06);
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(0,0,0,.04);
    }
    .hpp-table thead th {
        background: rgba(0,0,0,.02);
        font-weight: 800;
        font-size: 12px;
        color: #343a40;
        border-bottom: 1px solid rgba(0,0,0,.06);
        white-space: nowrap;
    }
</style>
@endpush

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">HPP Ledger</li>
    </ol>
@endsection

@section('content')
@php
    $sourceLabel = function ($sourceType) {
        $raw = trim((string) $sourceType);
        if ($raw === '') return '-';
        if (str_contains($raw, '\\')) {
            $raw = class_basename($raw);
        }
        return \Illuminate\Support\Str::headline(str_replace('_', ' ', $raw));
    };

    $sourceBadgeClass = function ($sourceType) {
        $raw = strtolower(trim((string) $sourceType));
        if (str_contains($raw, 'purchasedelivery')) return 'hpp-source-badge hpp-source-badge--pd';
        if (str_contains($raw, 'correction')) return 'hpp-source-badge hpp-source-badge--correction';
        return 'hpp-source-badge hpp-source-badge--default';
    };
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card hpp-card">
                <div class="card-body">
                    <form method="GET" action="{{ route('hpp-ledger.index') }}" class="hpp-filter-card">
                        <div class="hpp-filter-title">Ledger Filter</div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="branch_id" class="form-label">Branch</label>
                                <select name="branch_id" id="branch_id" class="form-select" required>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (int) $selectedBranchId === (int) $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="product_id" class="form-label">Product</label>
                                <select name="product_id" id="product_id" class="form-select">
                                    <option value="">All Products</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" {{ (int) $selectedProductId === (int) $product->id ? 'selected' : '' }}>
                                            {{ $product->product_name }} ({{ $product->product_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $dateFrom }}">
                            </div>

                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $dateTo }}">
                            </div>

                            <div class="col-md-2">
                                <label for="source_type" class="form-label">Source Type</label>
                                <select name="source_type" id="source_type" class="form-select">
                                    <option value="">All Sources</option>
                                    @foreach($sourceTypes as $sourceType)
                                        <option value="{{ $sourceType }}" {{ $selectedSourceType === $sourceType ? 'selected' : '' }}>
                                            {{ $sourceLabel($sourceType) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 d-flex justify-content-end gap-2">
                                <a href="{{ route('hpp-ledger.index', ['branch_id' => $selectedBranchId]) }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Apply Filter
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div>
                            <h5 class="mb-0">HPP Ledger</h5>
                            <div class="text-muted small">
                                Historical product cost movements from <code>product_hpps</code>, sorted latest first.
                            </div>
                        </div>
                        <div class="text-muted small">
                            Rows: <strong>{{ $rows->total() }}</strong>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle hpp-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date Time</th>
                                    <th>Product</th>
                                    <th>Branch</th>
                                    <th>Source Type</th>
                                    <th class="text-end">Incoming Qty</th>
                                    <th class="text-end">Incoming Unit Cost</th>
                                    <th class="text-end">Old Avg Cost</th>
                                    <th class="text-end">New Avg Cost</th>
                                    <th class="text-end">Current Avg Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    @php
                                        $effectiveAt = $row->effective_at ?: $row->created_at;
                                        $avgChanged = round((float) ($row->old_avg_cost ?? 0), 2) !== round((float) ($row->new_avg_cost ?? 0), 2);
                                    @endphp
                                    <tr class="{{ $avgChanged ? 'hpp-row-changed' : '' }}">
                                        <td>
                                            <div class="fw-semibold">
                                                {{ $effectiveAt ? \Carbon\Carbon::parse($effectiveAt)->format('d M Y H:i:s') : '-' }}
                                            </div>
                                            <div class="text-muted small">
                                                Ledger ID #{{ (int) $row->id }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $row->product_name ?? '-' }}</div>
                                            <div class="mt-1">
                                                <span class="hpp-product-code">{{ $row->product_code ?? '-' }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $row->branch_name ?? '-' }}</td>
                                        <td>
                                            <span class="{{ $sourceBadgeClass($row->source_type) }}">
                                                {{ $sourceLabel($row->source_type) }}
                                            </span>
                                        </td>
                                        <td class="text-end">{{ number_format((int) ($row->incoming_qty ?? 0)) }}</td>
                                        <td class="text-end">{{ format_currency((float) ($row->incoming_unit_cost ?? 0)) }}</td>
                                        <td class="text-end">{{ format_currency((float) ($row->old_avg_cost ?? 0)) }}</td>
                                        <td class="text-end">{{ format_currency((float) ($row->new_avg_cost ?? 0)) }}</td>
                                        <td class="text-end fw-semibold">{{ format_currency((float) ($row->avg_cost ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No HPP ledger rows found for the selected filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($rows->hasPages())
                        <div class="mt-3">
                            {{ $rows->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

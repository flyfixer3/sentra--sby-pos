@extends('layouts.app')

@section('title', 'HPP Ledger')

@push('page_css')
<style>
    .hpp-filter-card {
        border: 1px solid rgba(0, 0, 0, .06);
        border-radius: 14px;
        background: linear-gradient(180deg, #fbfcfe 0%, #f5f7fb 100%);
        padding: 1rem 1rem .95rem;
        margin-bottom: 1rem;
    }

    .hpp-filter-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .9rem;
    }

    .hpp-filter-grid {
        row-gap: 1rem;
    }

    .hpp-filter-field {
        display: flex;
        flex-direction: column;
        width: 100%;
    }

    .hpp-filter-card .form-label {
        display: block;
        width: 100%;
        font-size: 12px;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: .35rem;
        line-height: 1.2;
    }

    .hpp-filter-card .form-control,
    .hpp-filter-card .form-select {
        border-radius: .65rem;
        min-height: 40px;
        width: 100%;
    }

    .hpp-filter-card .form-control::placeholder {
        color: #9aa4b2;
    }

    .hpp-filter-actions-col {
        display: flex;
        align-items: end;
    }

    .hpp-filter-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: .6rem;
        width: 100%;
        min-height: 40px;
    }

    .hpp-filter-actions .btn {
        min-height: 40px;
        border-radius: .65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        white-space: nowrap;
        padding-left: 1rem;
        padding-right: 1rem;
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

    .hpp-source-badge--pd {
        background: rgba(25, 135, 84, .10);
        color: #146c43;
        border-color: rgba(25, 135, 84, .18);
    }

    .hpp-source-badge--correction {
        background: rgba(255, 193, 7, .14);
        color: #7a5d00;
        border-color: rgba(255, 193, 7, .25);
    }

    .hpp-source-badge--default {
        background: rgba(13, 110, 253, .08);
        color: #0a58ca;
        border-color: rgba(13, 110, 253, .18);
    }

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

    .hpp-filter-card .btn-sm {
        border-radius: .6rem;
        padding: .35rem .7rem;
        font-size: 12px;
        font-weight: 600;
    }

    @media (max-width: 991.98px) {
        .hpp-filter-actions-col {
            align-items: stretch;
        }

        .hpp-filter-actions {
            justify-content: stretch;
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .hpp-filter-actions .btn {
            width: 100%;
        }

        .hpp-filter-card .col-lg-1 .btn {
            width: 100%;
        }
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

    $selectedProductDisplay = '';
    if (!empty($selectedProductId)) {
        $selectedProduct = collect($products)->firstWhere('id', (int) $selectedProductId);
        if ($selectedProduct) {
            $selectedProductDisplay = $selectedProduct->product_name . ' (' . $selectedProduct->product_code . ')';
        }
    }
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card hpp-card">
                <div class="card-body">
                    <form method="GET" action="{{ route('hpp-ledger.index') }}" class="hpp-filter-card" id="hppFilterForm">
                        <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
                        <input type="hidden" name="product_id" id="product_id" value="{{ $selectedProductId }}">

                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <div class="hpp-filter-title mb-0">Ledger Filter</div>

                            <a href="{{ route('hpp-ledger.index', ['branch_id' => $selectedBranchId]) }}"
                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                <span>Reset</span>
                            </a>
                        </div>

                        <div class="row hpp-filter-grid">
                            <div class="col-lg-4 col-md-6">
                                <div class="hpp-filter-field">
                                    <label for="product_search" class="form-label">Product</label>
                                    <input
                                        type="text"
                                        id="product_search"
                                        class="form-control"
                                        list="product_options"
                                        placeholder="Search product name or code"
                                        value="{{ $selectedProductDisplay }}"
                                        autocomplete="off"
                                    >
                                    <datalist id="product_options">
                                        <option value="All Products" data-id=""></option>
                                        @foreach($products as $product)
                                            <option
                                                value="{{ $product->product_name }} ({{ $product->product_code }})"
                                                data-id="{{ $product->id }}"
                                                data-name="{{ strtolower($product->product_name) }}"
                                                data-code="{{ strtolower($product->product_code) }}"
                                            ></option>
                                        @endforeach
                                    </datalist>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="hpp-filter-field">
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
                            </div>

                            <div class="col-lg-2 col-md-6">
                                <div class="hpp-filter-field">
                                    <label for="date_from" class="form-label">Date From</label>
                                    <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $dateFrom }}">
                                </div>
                            </div>

                            <div class="col-lg-2 col-md-6">
                                <div class="hpp-filter-field">
                                    <label for="date_to" class="form-label">Date To</label>
                                    <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $dateTo }}">
                                </div>
                            </div>

                            <div class="col-lg-1 col-md-12 d-flex align-items-end justify-content-lg-end">
                                <button type="submit" class="btn btn-primary w-100 w-lg-auto d-inline-flex align-items-center justify-content-center gap-1">
                                    <i class="bi bi-funnel"></i>
                                    <span>Apply</span>
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

<script>
    (function () {
        const productSearchInput = document.getElementById('product_search');
        const productIdInput = document.getElementById('product_id');
        const productOptions = Array.from(document.querySelectorAll('#product_options option'));
        const form = document.getElementById('hppFilterForm');

        function normalizeText(value) {
            return (value || '').toString().trim().toLowerCase();
        }

        function findMatchedOption(inputValue) {
            const normalizedInput = normalizeText(inputValue);

            if (!normalizedInput || normalizedInput === 'all products') {
                return { id: '', value: 'All Products' };
            }

            for (const option of productOptions) {
                const optionValue = normalizeText(option.value);
                const optionName = normalizeText(option.dataset.name);
                const optionCode = normalizeText(option.dataset.code);

                if (
                    normalizedInput === optionValue ||
                    normalizedInput === optionName ||
                    normalizedInput === optionCode ||
                    optionValue.includes(normalizedInput) ||
                    optionName.includes(normalizedInput) ||
                    optionCode.includes(normalizedInput)
                ) {
                    return {
                        id: option.dataset.id || '',
                        value: option.value
                    };
                }
            }

            return null;
        }

        function syncProductSelection() {
            const matched = findMatchedOption(productSearchInput.value);

            if (matched) {
                productIdInput.value = matched.id;
                if (matched.id !== '') {
                    productSearchInput.value = matched.value;
                } else if (normalizeText(productSearchInput.value) === 'all products') {
                    productSearchInput.value = 'All Products';
                }
                return true;
            }

            productIdInput.value = '';
            return false;
        }

        productSearchInput.addEventListener('change', syncProductSelection);
        productSearchInput.addEventListener('blur', syncProductSelection);

        form.addEventListener('submit', function () {
            syncProductSelection();
        });
    })();
</script>
@endsection
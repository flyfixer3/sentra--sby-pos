@extends('layouts.app')

@section('title', 'System Activity Log')

@section('content')
<div class="container-fluid">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">System Activity Log</h3>
            <small class="text-muted">Track perubahan data & aktivitas user di sistem.</small>
        </div>
        <div class="text-muted small">
            Total: <strong>{{ method_exists($logs, 'total') ? $logs->total() : '-' }}</strong>
        </div>
    </div>

    <!-- ✅ Filter Card -->
    <div class="card shadow-sm mb-3 border-0">
        <div class="card-body">
            <form method="GET" action="{{ route('logs.index') }}">
                <div class="row g-3 align-items-end">

                    <!-- User Filter -->
                    <div class="col-12 col-md-3">
                        <label for="user" class="form-label mb-1">User</label>
                        <select name="user" id="user" class="form-control">
                            <option value="">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('user') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Table Filter -->
                    <div class="col-12 col-md-3">
                        <label for="table" class="form-label mb-1">Module / Table</label>
                        <select name="table" id="table" class="form-control">
                            <option value="">All Tables</option>
                            @foreach($tables as $table)
                                @php
                                    // Label lebih jelas buat user
                                    $tableLabel = ucfirst($table);

                                    // Kalau Mutation, tampilkan jadi Stock Log (tapi value tetap "Mutation" supaya filter tetap jalan)
                                    if ($table === 'Mutation') {
                                        $tableLabel = 'Stock Log';
                                    }

                                    // Opsional rapihin nama gabungan (biar enak dibaca)
                                    if ($table === 'PurchaseDelivery') {
                                        $tableLabel = 'Purchase Delivery';
                                    }
                                    if ($table === 'SaleDelivery') {
                                        $tableLabel = 'Sale Delivery';
                                    }
                                @endphp

                                <option value="{{ $table }}" {{ request('table') == $table ? 'selected' : '' }}>
                                    {{ $tableLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div class="col-12 col-md-4">
                        <label for="date_range" class="form-label mb-1">Date Range</label>
                        <input type="text"
                               name="date_range"
                               id="date_range"
                               class="form-control"
                               value="{{ request('date_range') ?? '' }}"
                               placeholder="Select Date Range">
                    </div>

                    <!-- Buttons -->
                    <div class="col-12 col-md-2 d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter"></i> Apply
                        </button>

                        @if(request()->hasAny(['user','table','date_range']) && (request('user') || request('table') || request('date_range')))
                            <a href="{{ route('logs.index') }}" class="btn btn-light">
                                <i class="bi bi-x-circle"></i> Reset
                            </a>
                        @endif
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ✅ Table Card -->
    <div class="card shadow-sm border-0">
        <div class="card-body">

            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 160px;">Date</th>
                            <th style="width: 200px;">User</th>
                            <th>Action</th>
                            <th style="width: 180px;">Table</th>
                            <th style="width: 180px;">Record</th>
                            <th style="width: 320px;">Changes</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $subjectType = $log->subject_type ? class_basename($log->subject_type) : null;
                                $modelName = $subjectType ? strtolower($subjectType) : null;

                                // ✅ UI Label table
                                $tableLabel = $subjectType ?? '-';
                                if ($tableLabel === 'Mutation') {
                                    $tableLabel = 'Stock Log'; // biar jelas bahwa ini hanya log
                                }
                                if ($tableLabel === 'PurchaseDelivery') {
                                    $tableLabel = 'Purchase Delivery';
                                }
                                if ($tableLabel === 'SaleDelivery') {
                                    $tableLabel = 'Sale Delivery';
                                }

                                $oldValues = $log->properties['old'] ?? [];
                                $newValues = $log->properties['attributes'] ?? [];

                                // ✅ subject id
                                $subjectId = null;
                                if ($log->subject && method_exists($log->subject, 'getAttribute')) {
                                    $subjectId = $log->subject->getAttribute('id');
                                }

                                // ✅ label record
                                $label = $subjectId ?? '-';

                                // kalau punya reference, pakai reference biar lebih enak
                                if ($log->subject && method_exists($log->subject, 'getAttribute')) {
                                    $ref = $log->subject->getAttribute('reference');
                                    if (!empty($ref)) {
                                        $label = $ref;
                                    }
                                }

                                /**
                                 * ✅ RULE UTAMA:
                                 * - Mutation = LOG saja -> jangan ada link ke detail
                                 * - Adjustment -> boleh link (adjustments.show)
                                 * - PurchaseDelivery -> boleh link (purchase-deliveries.show)
                                 * - SaleDelivery -> boleh link (sale-deliveries.show)
                                 * - Sale/Purchase/Product dll tetap jalan
                                 */

                                $routeName = null;

                                if ($modelName) {
                                    $routeName = match ($modelName) {
                                        'sale'           => 'sales.show',
                                        'purchase'        => 'purchases.show',
                                        'product'         => 'products.show',
                                        'adjustment'      => 'adjustments.show',

                                        // ✅ ini yang kamu minta ditambahin
                                        'purchasedelivery'=> 'purchase-deliveries.show',
                                        'saledelivery'    => 'sale-deliveries.show',

                                        // ❌ MUTATION JANGAN ADA SHOW LINK (log only)
                                        'mutation'        => null,

                                        // contoh index (tanpa param)
                                        'stock'           => 'stocks.index',

                                        default           => $modelName . 's.show',
                                    };
                                }

                                $noParamRoutes = ['stocks.index'];
                                $hasRoute = $routeName ? \Illuminate\Support\Facades\Route::has($routeName) : false;
                                $needsParam = $routeName ? !in_array($routeName, $noParamRoutes, true) : false;

                                // hitung perubahan yang berbeda saja
                                $diffCount = 0;
                                if (!empty($oldValues) && !empty($newValues)) {
                                    foreach ($oldValues as $k => $v) {
                                        if (array_key_exists($k, $newValues) && $v !== $newValues[$k]) {
                                            $diffCount++;
                                        }
                                    }
                                }
                            @endphp

                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ optional($log->created_at)->format('Y-m-d') }}</div>
                                    <div class="text-muted small">{{ optional($log->created_at)->format('H:i') }}</div>
                                </td>

                                <td>
                                    <div class="fw-semibold">{{ $log->causer->name ?? 'System' }}</div>
                                    <div class="text-muted small">
                                        {{ $log->causer ? ('ID: ' . $log->causer->id) : '-' }}
                                    </div>
                                </td>

                                <td>
                                    <div class="fw-semibold">{{ $log->description }}</div>
                                    <div class="text-muted small">
                                        Log ID: {{ $log->id }}
                                    </div>
                                </td>

                                <td>
                                    @if($subjectType)
                                        <span class="badge bg-light text-dark border">{{ $tableLabel }}</span>
                                    @else
                                        -
                                    @endif
                                </td>

                                <td>
                                    {{-- ✅ kalau route null (mutation), atau tidak ada subjectId, tampilkan text saja --}}
                                    @if($hasRoute && $subjectId)
                                        @if($needsParam)
                                            <a href="{{ route($routeName, $subjectId) }}" class="text-primary text-decoration-none fw-semibold">
                                                {{ $label }}
                                            </a>
                                        @else
                                            <a href="{{ route($routeName) }}" class="text-primary text-decoration-none fw-semibold">
                                                {{ $label }}
                                            </a>
                                        @endif
                                    @else
                                        <span class="text-muted fw-semibold">{{ $label }}</span>
                                    @endif
                                </td>

                                <td>
                                    @if (!empty($oldValues) && !empty($newValues) && $diffCount > 0)
                                        <details>
                                            <summary class="text-primary" style="cursor:pointer;">
                                                View changes <span class="badge bg-secondary ms-1">{{ $diffCount }}</span>
                                            </summary>
                                            <div class="mt-2 small">
                                                @foreach($oldValues as $key => $oldValue)
                                                    @if(array_key_exists($key, $newValues) && $oldValue !== $newValues[$key])
                                                        <div class="mb-2">
                                                            <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $key)) }}</div>
                                                            <div>
                                                                <span class="text-danger">
                                                                    {{ is_array($oldValue) ? json_encode($oldValue) : $oldValue }}
                                                                </span>
                                                                <span class="mx-1">→</span>
                                                                <span class="text-success">
                                                                    {{ is_array($newValues[$key]) ? json_encode($newValues[$key]) : $newValues[$key] }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </details>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">No activity logs found.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                    @if(method_exists($logs, 'firstItem'))
                        Showing {{ $logs->firstItem() ?? 0 }} - {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() ?? 0 }}
                    @endif
                </div>

                {{-- ✅ pagination BIARKAN seperti sekarang (JANGAN DIUBAH) --}}
                <div>
                    {{ $logs->links() }}
                </div>
            </div>

        </div>
    </div>

</div>
@endsection

@push('page_scripts')
    <!-- ✅ Date Range Picker Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/moment/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('date_range');

            if (input && typeof $(input).daterangepicker === 'function') {
                $(input).daterangepicker({
                    autoUpdateInput: false,
                    opens: 'left',
                    locale: {
                        format: 'YYYY-MM-DD',
                        applyLabel: 'Apply',
                        cancelLabel: 'Clear'
                    }
                });

                $(input).on('apply.daterangepicker', function(ev, picker) {
                    $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
                });

                $(input).on('cancel.daterangepicker', function(ev, picker) {
                    $(this).val('');
                });
            }
        });
    </script>
@endpush
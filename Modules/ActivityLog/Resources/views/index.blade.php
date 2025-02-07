@extends('layouts.app')

@section('title', 'System Activity Log')

@section('content')
<div class="container">
    <h2>System Activity Log</h2>

    <!-- ✅ Filter Form -->
    <form method="GET" action="{{ route('logs.index') }}" class="mb-3">
        <div class="row">
            <!-- User Filter -->
            <div class="col-md-3">
                <label for="user">User:</label>
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
            <div class="col-md-3">
                <label for="table">Table:</label>
                <select name="table" id="table" class="form-control">
                    <option value="">All Tables</option>
                    @foreach($tables as $table)
                        <option value="{{ $table }}" {{ request('table') == $table ? 'selected' : '' }}>
                            {{ ucfirst($table) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range Filter (Custom Calendar Picker) -->
            <div class="col-md-4">
                <label for="date_range">Date Range:</label>
                <input type="text" name="date_range" id="date_range" class="form-control"
                    value="{{ request('date_range') ?? '' }}" placeholder="Select Date Range">
            </div>

            <!-- Filter Button -->
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter"></i> Apply Filters
                </button>
            </div>
        </div>
    </form>

    <!-- ✅ Activity Log Table -->
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Action</th>
                <th>Table</th>
                <th>Record ID</th>
                <th>Changes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
            <tr>
                <td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $log->causer->name ?? 'System' }}</td>
                <td>{{ $log->description }}</td>
                <td>{{ class_basename($log->subject_type) ?? '-' }}</td>
                <td>
                    @if($log->subject && method_exists($log->subject, 'getAttribute'))
                        @php
                            $modelName = strtolower(class_basename($log->subject_type));
                            $routeName = match ($modelName) {
                                'sale' => 'sales.show',
                                'product' => 'products.show',
                                'purchase' => 'purchases.show',
                                default => $modelName . 's.show'
                            };
                        @endphp
                        <a href="{{ route($routeName, $log->subject->id) }}" class="text-primary">
                            {{ $modelName === 'sale' ? ($log->subject->getAttribute('reference') ?? $log->subject->id) : $log->subject->id }}
                        </a>
                    @else
                        -
                    @endif
                </td>
                <td>
                    @php
                        $oldValues = $log->properties['old'] ?? [];
                        $newValues = $log->properties['attributes'] ?? [];
                    @endphp
                    @if (!empty($oldValues) && !empty($newValues))
                        @foreach($oldValues as $key => $oldValue)
                            @if(isset($newValues[$key]) && $oldValue !== $newValues[$key]) 
                                <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> 
                                <span class="text-danger">{{ $oldValue }}</span> → 
                                <span class="text-success">{{ $newValues[$key] }}</span><br>
                            @endif
                        @endforeach
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $logs->links() }}
</div>
@endsection

@push('page_scripts')

    <!-- ✅ Ensure jQuery is Loaded -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- ✅ Include Bootstrap JS & Popper.js (Only for this page) -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ✅ Include Date Range Picker Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/moment/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

    <script>
    $(document).ready(function() {
        console.log("Checking if Date Range Picker is loaded...");

        // ✅ Initialize Date Range Picker
        $('#date_range').daterangepicker({
            autoUpdateInput: false,
            opens: 'left',
            locale: {
                format: 'YYYY-MM-DD',
                applyLabel: 'Apply',
                cancelLabel: 'Clear'
            }
        });

        // ✅ Handle Selection
        $('#date_range').on('apply.daterangepicker', function(ev, picker) {
            console.log("📅 Date Selected: ", picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
        });

        // ✅ Handle Clear
        $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
            console.log("❌ Date Cleared");
            $(this).val('');
        });

        console.log("✅ Date Range Picker Initialized!");

        // ✅ Fix Bootstrap Tooltip Error
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
    </script>

@endpush

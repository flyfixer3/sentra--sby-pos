{{-- Modules/PurchaseOrder/Resources/views/partials/status.blade.php --}}

@php
    $status = (string) ($data->status ?? '');
    $statusLower = strtolower($status);
@endphp

@if ($statusLower === 'pending')
    <span class="badge badge-info">{{ $status }}</span>
@elseif ($statusLower === 'partial' || $statusLower === 'partially sent')
    <span class="badge badge-warning">{{ $status }}</span>
@elseif ($statusLower === 'completed')
    <span class="badge badge-success">{{ $status }}</span>
@else
    <span class="badge badge-secondary">{{ $status ?: '-' }}</span>
@endif

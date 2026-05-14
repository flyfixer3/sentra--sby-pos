@php
    $status = trim((string) ($data->status ?? ''));
    $normalizedStatus = strtolower($status);
@endphp

@if ($normalizedStatus === 'pending')
    <span class="badge badge-warning">Pending</span>
@elseif ($normalizedStatus === 'completed')
    <span class="badge badge-success">Completed</span>
@else
    <span class="badge badge-secondary">{{ $status !== '' ? $status : '-' }}</span>
@endif

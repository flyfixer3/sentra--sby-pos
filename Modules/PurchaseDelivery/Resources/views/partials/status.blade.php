@php
    $st = strtolower(trim((string)($data->status ?? 'pending')));

    // Normalize beberapa kemungkinan nilai
    if ($st === 'open') $st = 'pending';
    if ($st === 'completed') $st = 'received';

    $class = match ($st) {
        'pending' => 'badge bg-warning text-dark',
        'partial' => 'badge bg-info text-white',
        'received' => 'badge bg-success',
        'cancelled', 'canceled' => 'badge bg-danger',
        default => 'badge bg-secondary',
    };

    $label = strtoupper($st);
@endphp

<span class="{{ $class }}">{{ $label }}</span>

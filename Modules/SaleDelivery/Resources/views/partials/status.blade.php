@php
    $status = strtolower((string)($data->status ?? 'pending'));
    $map = [
        'pending' => 'warning',
        'confirmed' => 'success',
        'cancelled' => 'danger',
    ];
    $cls = $map[$status] ?? 'secondary';
@endphp

<span class="badge bg-{{ $cls }}">{{ strtoupper($status) }}</span>

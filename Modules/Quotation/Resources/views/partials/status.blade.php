@php
    $st = strtolower(trim((string) ($data->status ?? 'pending')));
    if ($st === 'sent') {
        $st = 'completed';
    }

    $class = match ($st) {
        'pending'   => 'badge bg-warning text-dark',
        'completed' => 'badge bg-success',
        'cancelled' => 'badge bg-danger',
        default     => 'badge bg-secondary',
    };

    // label rapih (tapi DB tetap lowercase)
    $label = match ($st) {
        'pending'   => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default     => strtoupper($st),
    };
@endphp

<span class="{{ $class }}">
    {{ $label }}
</span>

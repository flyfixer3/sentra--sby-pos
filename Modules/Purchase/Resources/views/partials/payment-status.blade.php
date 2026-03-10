@php
    $status = $data->effective_payment_status ?? $data->payment_status;
@endphp

@if ($status == 'Partial')
    <span class="badge badge-warning">
        {{ $status }}
    </span>
@elseif ($status == 'Paid')
    <span class="badge badge-success">
        {{ $status }}
    </span>
@elseif ($status == 'Overpaid')
    <span class="badge badge-info">
        {{ $status }}
    </span>
@else
    <span class="badge badge-danger">
        {{ $status }}
    </span>
@endif

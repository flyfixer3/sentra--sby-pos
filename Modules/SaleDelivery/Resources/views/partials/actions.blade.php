@php
    $active = session('active_branch');
    $isPending = strtolower((string) $data->status) === 'pending';
    $canConfirm = $active && $active !== 'all' && $isPending;
@endphp

<div class="btn-group" role="group">
    @can('show_sale_deliveries')
        <a href="{{ route('sale-deliveries.show', $data->id) }}" class="btn btn-sm btn-primary">
            <i class="bi bi-eye"></i>
        </a>
    @endcan

    @can('confirm_sale_deliveries')
        @if($canConfirm)
            <a href="{{ route('sale-deliveries.confirm.form', $data->id) }}" class="btn btn-sm btn-success">
                <i class="bi bi-check2-circle"></i>
            </a>
        @endif
    @endcan
</div>

@php
    $active = session('active_branch');
    $isPending = strtolower((string) $data->status) === 'pending';
    $canPrint = in_array(strtolower((string) $data->status), ['pending', 'confirmed'], true);
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

    @can('show_sale_deliveries')
        @if($canPrint)
            <button type="button"
                    class="btn btn-sm btn-secondary js-print-sale-delivery"
                    data-id="{{ (int) $data->id }}"
                    title="Print Delivery Note">
                <i class="bi bi-printer"></i>
            </button>
        @endif
    @endcan

    @can('edit_sale_deliveries')
        @if($isPending)
            <a href="{{ route('sale-deliveries.edit', $data->id) }}" class="btn btn-sm btn-info">
                <i class="bi bi-pencil-square"></i>
            </a>
        @endif
    @endcan
</div>

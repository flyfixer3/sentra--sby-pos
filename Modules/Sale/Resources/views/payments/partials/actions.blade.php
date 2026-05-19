@can('access_sale_payments')
    <a target="_blank" href="{{ route('sale-payments.receipt', $data->id) }}" class="btn btn-secondary btn-sm" title="Print Receipt">
        <i class="bi bi-printer"></i>
    </a>
@endcan

@can('access_sale_payments')
    <a href="{{ route('sale-payments.edit', [$data->sale->id, $data->id]) }}" class="btn btn-info btn-sm">
        <i class="bi bi-pencil"></i>
    </a>
@endcan
@can('access_sale_payments')
    <button id="delete" class="btn btn-danger btn-sm" type="button" data-confirm-target-form="destroy{{ $data->id }}" data-confirm-title="Confirm Delete" data-confirm-message="Are you sure? It will delete the data permanently!" data-confirm-button="Delete" data-confirm-variant="danger">
        <i class="bi bi-trash"></i>
        <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('sale-payments.destroy', $data->id) }}" method="POST">
            @csrf
            @method('delete')
        </form>
    </button>
@endcan

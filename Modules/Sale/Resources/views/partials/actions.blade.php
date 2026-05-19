<div class="btn-group dropleft">
    <button type="button" class="btn btn-ghost-primary dropdown rounded" data-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-three-dots-vertical"></i>
    </button>

    <div class="dropdown-menu">

        {{-- Kalau sudah deleted: tampilkan Restore + Force Delete --}}
        @if(!empty($data->deleted_at))

            @can('delete_sales')
                <button class="dropdown-item" type="button" data-confirm-target-form="restore{{ $data->id }}" data-confirm-title="Confirm Restore" data-confirm-message="Restore this Sale?" data-confirm-button="Restore" data-confirm-variant="success">
                    <i class="bi bi-arrow-counterclockwise mr-2 text-success" style="line-height:1;"></i> Restore
                </button>
                <form id="restore{{ $data->id }}" class="d-none" action="{{ route('sales.restore', $data->id) }}" method="POST">
                    @csrf
                    @method('patch')
                </form>
            @endcan

            @can('delete_sales')
                <button class="dropdown-item" type="button" data-confirm-target-form="forceDestroy{{ $data->id }}" data-confirm-title="Confirm Delete" data-confirm-message="Force delete? This will delete permanently and cannot be undone!" data-confirm-button="Delete" data-confirm-variant="danger">
                    <i class="bi bi-trash-fill mr-2 text-danger" style="line-height:1;"></i> Force Delete
                </button>
                <form id="forceDestroy{{ $data->id }}" class="d-none" action="{{ route('sales.force-destroy', $data->id) }}" method="POST">
                    @csrf
                    @method('delete')
                </form>
            @endcan

        @else
            {{-- Normal (not deleted) --}}

            @can('show_sales')
                <a href="{{ route('sales.show', $data->id) }}" class="dropdown-item">
                    <i class="bi bi-eye mr-2 text-info" style="line-height: 1;"></i> Details
                </a>
            @endcan

            @can('access_sale_payments')
                <a href="{{ route('sale-payments.index', $data->id) }}" class="dropdown-item">
                    <i class="bi bi-cash-coin mr-2 text-warning" style="line-height: 1;"></i> Show Payments
                </a>
            @endcan

            @can('access_sale_payments')
                @if((int) ($data->due_amount ?? 0) > 0)
                    <a href="{{ route('sale-payments.create', $data->id) }}" class="dropdown-item">
                        <i class="bi bi-plus-circle-dotted mr-2 text-success" style="line-height: 1;"></i> Add Payment
                    </a>
                @endif
            @endcan

            @can('edit_sales')
                @if($data->isEditableInvoice())
                    <a href="{{ route('sales.edit', $data->id) }}" class="dropdown-item">
                        <i class="bi bi-pencil mr-2 text-primary" style="line-height: 1;"></i> Edit
                    </a>
                @else
                    <button type="button" class="dropdown-item text-muted" disabled title="{{ $data->editLockReason() }}">
                        <i class="bi bi-lock mr-2 text-muted" style="line-height: 1;"></i> Edit Locked
                    </button>
                @endif
            @endcan

            @can('delete_sales')
                <button class="dropdown-item" type="button" data-confirm-target-form="destroy{{ $data->id }}" data-confirm-title="Confirm Delete" data-confirm-message="Soft delete this Sale? You can restore it later." data-confirm-button="Delete" data-confirm-variant="danger">
                    <i class="bi bi-trash mr-2 text-danger" style="line-height: 1;"></i> Delete
                </button>
                <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('sales.destroy', $data->id) }}" method="POST">
                    @csrf
                    @method('delete')
                </form>
            @endcan

        @endif

    </div>
</div>

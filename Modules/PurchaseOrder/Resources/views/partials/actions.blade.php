<div class="btn-group dropleft">
    <button type="button" class="btn btn-ghost-primary dropdown rounded" data-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
    <div class="dropdown-menu">
        @can('create_purchase_order_purchases')
            <a href="{{ route('purchase-order-purchases.create', $data) }}" 
            class="dropdown-item {{ $data->status == 'Completed' ? 'disabled' : '' }}"
            onclick="{{ $data->status == 'Completed' ? 'return false;' : '' }}">
                <i class="bi bi-check2-circle mr-2 text-success" style="line-height: 1;"></i> Make Invoice
            </a>
        @endcan
        @can('create_purchase_deliveries')
            <a href="{{ route('purchase-deliveries.create', $data) }}" 
            class="dropdown-item {{ $data->status == 'Completed' ? 'disabled' : '' }}"
            onclick="{{ $data->status == 'Completed' ? 'return false;' : '' }}">
                <i class="bi bi-truck mr-2 text-success" style="line-height: 1;"></i> Make Delivery
            </a>
        @endcan
        @can('send_purchase_order_mails')
            <a href="{{ route('purchase-order.email', $data) }}" class="dropdown-item">
                <i class="bi bi-cursor mr-2 text-warning" style="line-height: 1;"></i> Send On Email
            </a>
        @endcan
        @can('edit_purchase_orders')
            <a href="{{ route('purchase-orders.edit', $data->id) }}" class="dropdown-item">
                <i class="bi bi-pencil mr-2 text-primary" style="line-height: 1;"></i> Edit
            </a>
        @endcan
        @can('show_purchase_orders')
            <a href="{{ route('purchase-orders.show', $data->id) }}" class="dropdown-item">
                <i class="bi bi-eye mr-2 text-info" style="line-height: 1;"></i> Details
            </a>
        @endcan
        @can('delete_purchase_orders')
            <button id="delete" class="dropdown-item" onclick="
                event.preventDefault();
                if (confirm('Are you sure? It will delete the data permanently!')) {
                document.getElementById('destroy{{ $data->id }}').submit()
                }">
                <i class="bi bi-trash mr-2 text-danger" style="line-height: 1;"></i> Delete
                <form id="destroy{{ $data->id }}" class="d-none" action="{{ route('purchase-orders.destroy', $data->id) }}" method="POST">
                    @csrf
                    @method('delete')
                </form>
            </button>
        @endcan
    </div>
</div>

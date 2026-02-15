<div class="btn-group dropleft">
    <button type="button" class="btn btn-ghost-primary dropdown rounded" data-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
    <div class="dropdown-menu">
        @php
            $statusLower = strtolower(trim((string) ($data->status ?? '')));
            $hasInvoice = ((int) ($data->invoice_count ?? 0)) > 0;

            // ✅ Make Invoice: hanya disable kalau invoice sudah ada
            $disableInvoice = $hasInvoice;

            // ✅ Make Delivery: hanya boleh untuk Pending/Partial
            $disableDelivery = !in_array($statusLower, ['pending', 'partial'], true);
        @endphp

        @can('create_purchase_order_purchases')
            <a href="{{ route('purchase-order-purchases.create', $data) }}"
            class="dropdown-item {{ $disableInvoice ? 'disabled' : '' }}"
            onclick="{{ $disableInvoice ? 'return false;' : '' }}">
                <i class="bi bi-receipt mr-2 text-primary" style="line-height: 1;"></i> Make Invoice
                @if($disableInvoice)
                    <span class="ml-2 badge badge-light">Already invoiced</span>
                @endif
            </a>
        @endcan

        @can('create_purchase_deliveries')
            <a href="{{ route('purchase-orders.deliveries.create', $data) }}"
            class="dropdown-item {{ $disableDelivery ? 'disabled' : '' }}"
            onclick="{{ $disableDelivery ? 'return false;' : '' }}">
                <i class="bi bi-truck mr-2 text-info" style="line-height: 1;"></i> Make Delivery
                @if($disableDelivery)
                    <span class="ml-2 badge badge-light">No remaining</span>
                @endif
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

@extends('layouts.app')

@section('title', 'Purchase Deliveries')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Purchase Deliveries</li>
    </ol>
@endsection

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h3 class="mb-0">Purchase Deliveries</h3>
            <div class="ml-auto d-flex">
                <!-- Status Filter -->
                <select class="form-control mr-2" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="Open">Open</option>
                    <option value="Completed">Completed</option>
                    <option value="Canceled">Canceled</option>
                </select>

                <!-- Search Box -->
                <input type="text" class="form-control mr-2" id="searchTransaction" placeholder="Search transaction">

                <!-- Bulk Delete Button -->
                <button id="bulkDelete" class="btn btn-danger mr-2" disabled>
                    <i class="bi bi-trash"></i> Delete Selected
                </button>

                <!-- Create New Button -->
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-primary">
                    <i class="bi bi-plus"></i> Create New
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="purchaseDeliveriesTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Date</th>
                            <th>Number</th>
                            <th>Vendor</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchaseDeliveries as $delivery)
                        <tr data-id="{{ $delivery->id }}">
                            <td><input type="checkbox" class="select-item" value="{{ $delivery->id }}"></td>
                            <td>{{ \Carbon\Carbon::parse($delivery->date)->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('purchase-deliveries.show', $delivery->id) }}" class="text-primary">
                                    Purchase Delivery #{{ $delivery->id }}
                                </a>
                            </td>
                            <td>{{ $delivery->purchaseOrder->supplier->supplier_name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge badge-{{ $delivery->status == 'Open' ? 'warning' : ($delivery->status == 'Completed' ? 'success' : 'danger') }}">
                                    {{ ucfirst($delivery->status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('purchases.create', ['purchase_delivery_id' => $delivery->id]) }}" class="btn btn-sm btn-primary">
                                    Create Invoice
                                </a>
                                <a href="{{ route('purchase-deliveries.show', $delivery->id) }}" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="{{ route('purchase-deliveries.edit', $delivery->id) }}" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="{{ $delivery->id }}">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-3">
                {{ $purchaseDeliveries->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('page_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    let selectAll = document.getElementById('selectAll');
    let checkboxes = document.querySelectorAll('.select-item');
    let bulkDeleteBtn = document.getElementById('bulkDelete');

    // ✅ Search Functionality
    document.getElementById('searchTransaction').addEventListener('keyup', function () {
        let searchValue = this.value.toLowerCase();
        let rows = document.querySelectorAll('#purchaseDeliveriesTable tbody tr');
        
        rows.forEach(row => {
            let text = row.innerText.toLowerCase();
            row.style.display = text.includes(searchValue) ? "" : "none";
        });
    });

    // ✅ Status Filter
    document.getElementById('statusFilter').addEventListener('change', function () {
        let statusValue = this.value.toLowerCase();
        let rows = document.querySelectorAll('#purchaseDeliveriesTable tbody tr');

        rows.forEach(row => {
            let statusText = row.querySelector("td:nth-child(5)").innerText.toLowerCase();
            row.style.display = statusValue === "" || statusText.includes(statusValue) ? "" : "none";
        });
    });

    // ✅ Select All Checkboxes
    selectAll.addEventListener('change', function () {
        checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        toggleBulkDeleteButton();
    });

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            toggleBulkDeleteButton();
        });
    });

    function toggleBulkDeleteButton() {
        let anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
        bulkDeleteBtn.disabled = !anyChecked;
    }

    // ✅ Confirm Before Deleting (Single Item)
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function (event) {
            let deliveryId = this.getAttribute("data-id");

            Swal.fire({
                title: "Are you sure?",
                text: "This action cannot be undone!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/purchase-delivery/${deliveryId}`, {  // ✅ FIXED URL
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                            "Accept": "application/json",
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire("Deleted!", "Purchase Delivery has been deleted.", "success").then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire("Error!", data.message || "Something went wrong.", "error");
                        }
                    })
                    .catch(error => {
                        console.error("Delete Error:", error);
                        Swal.fire("Error!", "Failed to delete the delivery.", "error");
                    });
                }
            });
        });
    });

    // ✅ Bulk Delete Function
    bulkDeleteBtn.addEventListener('click', function () {
        if (!confirm("Are you sure you want to delete selected Purchase Deliveries?")) return;

        let selectedIds = Array.from(checkboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);

        fetch("{{ route('purchase-deliveries.bulk-delete') }}", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ ids: selectedIds })
        }).then(response => {
            if (response.ok) {
                Swal.fire("Deleted!", "Selected deliveries have been deleted.", "success");
                selectedIds.forEach(id => document.querySelector(`tr[data-id="${id}"]`).remove());
                bulkDeleteBtn.disabled = true;
            } else {
                Swal.fire("Error!", "Failed to delete selected deliveries.", "error");
            }
        }).catch(error => console.error("Error:", error));
    });

});
</script>
@endpush

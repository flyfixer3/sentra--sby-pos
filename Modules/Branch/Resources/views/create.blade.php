@extends('layouts.app')

@section('title', 'Create Branch')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('branches.index') }}">Branches</a></li>
    <li class="breadcrumb-item active">Add</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    <form action="{{ route('branches.store') }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-lg-12">
                @include('utils.alerts')
                <div class="form-group">
                    <button class="btn btn-primary">Create Branch <i class="bi bi-check"></i></button>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="branch_name">Branch Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="branch_name" required>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="branch_address">Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="branch_address" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label for="branch_phone">Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="branch_phone" required>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h5 class="mb-3">Warehouse Option</h5>

                        <div class="form-group">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="selectExisting" name="warehouse_option" class="custom-control-input" value="select_existing" onchange="toggleWarehouseFields()" required>
                                <label class="custom-control-label" for="selectExisting">Select Existing</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="createNew" name="warehouse_option" class="custom-control-input" value="create_new" onchange="toggleWarehouseFields()" required>
                                <label class="custom-control-label" for="createNew">Create New</label>
                            </div>
                        </div>

                        <div class="form-group d-none" id="existingWarehouseField">
                            <label for="existing_warehouse_id">Select Available Warehouse</label>
                            <div class="input-group">
                                <select class="form-control" name="existing_warehouse_id" id="existing_warehouse_id">
                                    <option value="">-- Choose Available Warehouse --</option>
                                    @foreach(\Modules\Product\Entities\Warehouse::whereNull('branch_id')->get() as $warehouse)
                                        <option value="{{ $warehouse->id }}">
                                            {{ $warehouse->warehouse_name }} ({{ $warehouse->warehouse_code }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-info" id="btn-preview-warehouse">
                                        <i class="bi bi-eye"></i> Preview
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Only unassigned warehouses are listed here.</small>
                        </div>

                        <div id="newWarehouseFields" class="d-none">
                            <div class="form-group">
                                <label for="new_warehouse_code">New Warehouse Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="new_warehouse_code">
                            </div>
                            <div class="form-group">
                                <label for="new_warehouse_name">New Warehouse Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="new_warehouse_name">
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal Preview -->
<div class="modal fade" id="warehousePreviewModal" tabindex="-1" aria-labelledby="warehousePreviewLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="warehousePreviewLabel">Warehouse Preview</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>Warehouse Name:</strong> <span id="preview-name">-</span></p>
        <p><strong>Warehouse Code:</strong> <span id="preview-code">-</span></p>
        <p><strong>Status:</strong> <span id="preview-main-status">-</span></p>
      </div>
    </div>
  </div>
</div>
@endsection

@push('page_scripts')
<script>
    function toggleWarehouseFields() {
        const selected = document.querySelector('input[name="warehouse_option"]:checked')?.value;
        document.getElementById('existingWarehouseField').classList.toggle('d-none', selected !== 'select_existing');
        document.getElementById('newWarehouseFields').classList.toggle('d-none', selected !== 'create_new');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const selectedOption = document.querySelector('input[name="warehouse_option"]:checked');
        if (selectedOption) toggleWarehouseFields();

        document.getElementById('btn-preview-warehouse').addEventListener('click', () => {
            const selectedId = document.getElementById('existing_warehouse_id').value;
            if (!selectedId) {
                alert('Please select a warehouse first.');
                return;
            }

            fetch(`/warehouses/${selectedId}/preview`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('preview-name').textContent = data.warehouse_name || '-';
                    document.getElementById('preview-code').textContent = data.warehouse_code || '-';
                    document.getElementById('preview-main-status').textContent = data.is_main ? 'Main Warehouse' : 'Regular Warehouse';
                    $('#warehousePreviewModal').modal('show');
                })
                .catch(() => {
                    alert('Failed to load preview.');
                });
        });
    });
</script>
@endpush

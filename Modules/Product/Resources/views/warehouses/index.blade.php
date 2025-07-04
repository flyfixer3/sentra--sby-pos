@extends('layouts.app')

@section('title', 'Warehouses')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item active">Warehouses</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                @include('utils.alerts')
                <div class="card">
                    <div class="card-body">
                        <!-- Button trigger modal -->
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#warehouseCreateModal">
                            Add Warehouse <i class="bi bi-plus"></i>
                        </button>

                        <hr>

                        <div class="table-responsive">
                            {!! $dataTable->table() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    <div class="modal fade" id="warehouseCreateModal" tabindex="-1" role="dialog" aria-labelledby="warehouseCreateModal" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="warehouseCreateModalLabel">Create Warehouse</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('product-warehouses.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="warehouse_code">Warehouse Code <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="warehouse_code" required>
                        </div>

                        <div class="form-group">
                            <label for="warehouse_name">Warehouse Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="warehouse_name" required>
                        </div>

                        <div class="form-group">
                            <label for="branch_id">Branch <span class="text-danger">*</span></label>
                            <select class="form-control" name="branch_id" required>
                                <option value="" disabled selected>Select Branch</option>
                                @foreach(\Modules\Branch\Entities\Branch::all() as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mt-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_main" value="1" id="isMainWarehouse">
                                <label class="form-check-label" for="isMainWarehouse">Set as Main Warehouse</label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Create <i class="bi bi-check"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    {!! $dataTable->scripts() !!}
@endpush

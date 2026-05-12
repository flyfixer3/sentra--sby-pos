@extends('layouts.app')

@section('title', 'Product Accessories')

@section('third_party_stylesheets')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
@endsection

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item active">Accessories</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                @include('utils.alerts')
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center" style="gap: .5rem;">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#accessoryCreateModal">
                                Add Accessory <i class="bi bi-plus"></i>
                            </button>
                            <a href="{{ route('product-accessories.import.template') }}" class="btn btn-outline-secondary">
                                Download Template <i class="bi bi-download"></i>
                            </a>
                            <form action="{{ route('product-accessories.import.store') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap align-items-center" style="gap: .5rem;">
                                @csrf
                                <input type="file" name="file" accept=".xlsx,.xls" class="form-control-file" required>
                                <button type="submit" class="btn btn-outline-primary">Import ACC <i class="bi bi-upload"></i></button>
                            </form>
                        </div>

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
    <div class="modal fade" id="accessoryCreateModal" tabindex="-1" role="dialog" aria-labelledby="accessoryCreateModal" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="accessoryCreateModalLabel">Create Accessory</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('product-accessories.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="accessory_code">Accessory Code <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="accessory_code" required>
                        </div>
                        <div class="form-group">
                            <label for="accessory_name">Accessory Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="accessory_name" required>
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

@extends('layouts.app')

@section('title', 'Edit Warehouse')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('product-warehouses.index') }}">Warehouse</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-7">
                @include('utils.alerts')
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('product-warehouses.update', $warehouse->id) }}" method="POST">
                            @csrf
                            @method('patch')
                            <div class="form-group">
                                <label class="font-weight-bold" for="warehouse_code">Warehouse Code <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="warehouse_code" required value="{{ $warehouse->warehouse_code }}">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold" for="warehouse_name">Warehouse Name <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="warehouse_name" required value="{{ $warehouse->warehouse_name }}">
                            </div>
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="is_main" name="is_main" value="1" {{ $warehouse->is_main ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_main">Set as Main Warehouse</label>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Update <i class="bi bi-check"></i></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


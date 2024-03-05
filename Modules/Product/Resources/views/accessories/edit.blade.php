@extends('layouts.app')

@section('title', 'Edit Product Accessory')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item"><a href="{{ route('product-accessories.index') }}">Accessories</a></li>
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
                        <form action="{{ route('product-accessories.update', $accessory->id) }}" method="POST">
                            @csrf
                            @method('patch')
                            <div class="form-group">
                                <label class="font-weight-bold" for="accessory_code">Accessory Code <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="accessory_code" required value="{{ $accessory->accessory_code }}">
                            </div>
                            <div class="form-group">
                                <label class="font-weight-bold" for="accessory_name">Accessory Name <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="accessory_name" required value="{{ $accessory->accessory_name }}">
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


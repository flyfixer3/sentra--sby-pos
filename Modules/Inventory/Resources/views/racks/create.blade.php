@extends('layouts.app')

@section('title', 'Create Rack')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.racks.index') }}">Racks</a></li>
    <li class="breadcrumb-item active">Create</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    @include('utils.alerts')

    @if (session()->has('message'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="alert-body">
                <span>{{ session('message') }}</span>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="mb-0">Create Rack</h4>
            <div class="text-muted small">Rack wajib terikat ke 1 warehouse.</div>

            <hr class="my-3">

            <form method="POST" action="{{ route('inventory.racks.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label mb-1">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-control" required>
                            <option value="" disabled {{ old('warehouse_id') ? '' : 'selected' }}>-- Choose Warehouse --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" {{ (string)old('warehouse_id') === (string)$w->id ? 'selected' : '' }}>
                                    {{ $w->warehouse_name }} {{ $w->is_main ? '(Main)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label class="form-label mb-1">Rack Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" value="{{ old('code') }}" required maxlength="50" placeholder="contoh: A1, B2, R01">
                    </div>

                    <div class="col-md-6 mb-2">
                        <label class="form-label mb-1">Rack Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" maxlength="100" placeholder="optional">
                    </div>

                    <div class="col-12 mb-2">
                        <label class="form-label mb-1">Description</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="2000" placeholder="optional">{{ old('description') }}</textarea>
                    </div>
                </div>

                <div class="mt-3 d-flex">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="bi bi-check2-circle mr-1"></i> Save
                    </button>
                    <a href="{{ route('inventory.racks.index') }}" class="btn btn-light">
                        Cancel
                    </a>
                </div>
            </form>

        </div>
    </div>
</div>
@endsection

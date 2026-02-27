@extends('layouts.app')

@section('title', 'Import Racks')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('inventory.racks.index') }}">Racks</a></li>
        <li class="breadcrumb-item active">Import</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <strong>Import Racks (.xlsx)</strong>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <div><strong>Flow:</strong></div>
                        <ol class="mb-0">
                            <li>Pastikan Branch & Warehouse sudah ada (manual input).</li>
                            <li>Download template</li>
                            <li>Isi sheet <strong>Template</strong> lalu upload kembali</li>
                        </ol>
                    </div>

                    <a href="{{ route('inventory.racks.import.template') }}" class="btn btn-primary mb-3">
                        Download Template
                    </a>

                    <form action="{{ route('inventory.racks.import.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label>Upload File (.xlsx)</label>
                            <input type="file" name="file" class="form-control" required accept=".xlsx,.xls">
                            @error('file')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <button class="btn btn-success" type="submit">
                            Import Now
                        </button>
                        <a href="{{ route('inventory.racks.index') }}" class="btn btn-light">
                            Cancel
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

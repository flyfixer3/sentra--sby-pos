@extends('layouts.app')

@section('title', 'Import Products')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item active">Import</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <strong>Import Products (.xlsx)</strong>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <div><strong>Flow:</strong></div>
                        <ol class="mb-0">
                            <li>Download template</li>
                            <li>Copy-paste data kamu ke sheet <strong>Template</strong></li>
                            <li>Upload kembali file .xlsx ke form di bawah</li>
                        </ol>
                        <div class="mt-2">
                            <strong>Catatan:</strong> Category & Accessory bisa auto dibuat jika belum ada, asal <code>category_name</code> / <code>accessory_name</code> diisi.
                        </div>
                    </div>

                    <a href="{{ route('products.import.template') }}" class="btn btn-primary mb-3">
                        Download Template
                    </a>

                    <form action="{{ route('products.import.store') }}" method="POST" enctype="multipart/form-data">
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
                        <a href="{{ route('products.index') }}" class="btn btn-light">
                            Cancel
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
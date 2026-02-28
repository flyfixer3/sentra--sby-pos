@extends('layouts.app')

@section('title', 'Import Opening Stock')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('inventory.stocks.index') }}">Stocks</a></li>
        <li class="breadcrumb-item active">Import Opening Stock</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <strong>Import Opening Stock (.xlsx) — via Mutation</strong>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <div><strong>PENTING:</strong></div>
                        <ul class="mb-0">
                            <li>Import ini akan membuat <strong>Mutation (In)</strong> Opening Balance per Rack.</li>
                            <li>Template hanya menerima <strong>qty_good</strong>.</li>
                            <li><strong>Defect/Damaged</strong> tidak boleh dari Excel — lakukan lewat <strong>Adjustment / Quality Reclass</strong> supaya detail item valid.</li>
                            <li>Note mutation akan otomatis ditandai: <strong>AUTO GENERATED: EXCEL</strong></li>
                        </ul>
                    </div>

                    <a href="{{ route('inventory.stocks.import-opening.template') }}" class="btn btn-primary mb-3">
                        Download Template
                    </a>

                    <form action="{{ route('inventory.stocks.import-opening.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label>Opening Date</label>
                            <input type="date" name="date" class="form-control" required value="{{ old('date', date('Y-m-d')) }}">
                            @error('date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Reference (optional)</label>
                            <input type="text" name="reference" class="form-control" value="{{ old('reference') }}"
                                   placeholder="OB-IMPORT-YYYYMMDD-HHMMSS (auto if empty)">
                            @error('reference')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Note (optional)</label>
                            <input type="text" name="note" class="form-control" value="{{ old('note', 'Opening Balance Import') }}"
                                   placeholder="Opening Balance Import">
                            <small class="text-muted">
                                Sistem akan menambahkan prefix otomatis: <strong>AUTO GENERATED: EXCEL </strong>
                            </small>
                            @error('note')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

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
                        <a href="{{ route('inventory.stocks.index') }}" class="btn btn-light">
                            Cancel
                        </a>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

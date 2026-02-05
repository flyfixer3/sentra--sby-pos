@extends('layouts.app')

@section('title', 'Edit Rack')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.racks.index') }}">Racks</a></li>
    <li class="breadcrumb-item active">Edit</li>
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
            <h4 class="mb-0">Edit Rack</h4>
            <div class="text-muted small">Update rack data.</div>

            <hr class="my-3">

            <form method="POST" action="{{ route('inventory.racks.update', $rack->id) }}">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label mb-1">Warehouse <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" class="form-control" required>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" {{ (string)old('warehouse_id', $rack->warehouse_id) === (string)$w->id ? 'selected' : '' }}>
                                    {{ $w->warehouse_name }} {{ $w->is_main ? '(Main)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_id')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-2">
                        <label class="form-label mb-1">Rack Code <span class="text-danger">*</span></label>

                        <div class="input-group">
                            <input
                                type="text"
                                name="code"
                                id="rack_code"
                                class="form-control"
                                value="{{ old('code', $rack->code) }}"
                                required
                                maxlength="50"
                            >
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btn-generate-code">
                                    <i class="bi bi-magic mr-1"></i> Generate
                                </button>
                            </div>
                        </div>

                        @error('code')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-2">
                        <label class="form-label mb-1">Rack Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $rack->name) }}" maxlength="100">
                        @error('name')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 mb-2">
                        <label class="form-label mb-1">Description</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="2000">{{ old('description', $rack->description) }}</textarea>
                        @error('description')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-3 d-flex">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="bi bi-check2-circle mr-1"></i> Update
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

@push('page_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn-generate-code');
    const codeInput = document.getElementById('rack_code');
    const whSelect = document.getElementById('warehouse_id');

    if (!btn || !codeInput || !whSelect) return;

    btn.addEventListener('click', async function () {
        const wid = whSelect.value;
        if (!wid) {
            alert('Pilih warehouse dulu.');
            return;
        }

        btn.disabled = true;

        try {
            const url = `{{ route('inventory.racks.generate-code', ['warehouseId' => '__ID__']) }}`.replace('__ID__', wid);

            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!res.ok) throw new Error('Request failed');

            const data = await res.json();
            if (data && data.code) {
                codeInput.value = data.code;
                codeInput.focus();
            } else {
                alert('Gagal generate code.');
            }
        } catch (e) {
            alert('Gagal generate code.');
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
@endpush

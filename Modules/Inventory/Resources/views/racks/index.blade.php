@extends('layouts.app')

@section('title', 'Racks')

@section('breadcrumb')
<ol class="breadcrumb border-0 m-0">
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item active">Racks</li>
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

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4 class="mb-0">Racks</h4>
                    <div class="text-muted small">Manage racks per warehouse (branch mengikuti warehouse).</div>
                </div>

                @can('create_racks')
                    <a href="{{ route('inventory.racks.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-circle mr-1"></i> Create Rack
                    </a>
                @endcan
            </div>

            <hr class="my-3">

            <form method="GET" action="{{ route('inventory.racks.index') }}">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label mb-1">Warehouse</label>
                        <select name="warehouse_id" class="form-control">
                            <option value="">-- All Warehouses --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" {{ (string)request('warehouse_id') === (string)$w->id ? 'selected' : '' }}>
                                    {{ $w->warehouse_name }} {{ $w->is_main ? '(Main)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5 mb-2">
                        <label class="form-label mb-1">Search</label>
                        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="code / name / description">
                    </div>

                    <div class="col-md-3 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-dark btn-block">
                            <i class="bi bi-funnel mr-1"></i> Filter
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <!-- <th style="width:70px;">#</th> -->
                            <th style="width:70px;">Rack ID</th>
                            <th>Warehouse</th>
                            <th style="width:140px;">Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th style="width:160px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($racks as $i => $r)
                            <tr>
                                <!-- <td class="align-middle">{{ $racks->firstItem() + $i }}</td> -->
                                <td class="align-middle">{{ $r->id }}</td>
                                <td class="align-middle">
                                    {{ $r->warehouse?->warehouse_name ?? ('WH#'.$r->warehouse_id) }}
                                </td>
                                <td class="align-middle">
                                    <span class="badge badge-dark">{{ $r->code }}</span>
                                </td>
                                <td class="align-middle">{{ $r->name ?? '-' }}</td>
                                <td class="align-middle">{{ $r->description ?? '-' }}</td>
                                <td class="align-middle">
                                    <div class="d-flex">
                                        @can('edit_racks')
                                            <a href="{{ route('inventory.racks.edit', $r->id) }}" class="btn btn-sm btn-info mr-2">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan

                                        @can('delete_racks')
                                            <form action="{{ route('inventory.racks.destroy', $r->id) }}" method="POST" onsubmit="return confirm('Delete this rack?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No racks found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $racks->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

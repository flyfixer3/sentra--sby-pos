@extends('layouts.app')

@section('title', 'Create Transfer')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">Transfers</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <livewire:search-product />
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')

                        <form action="{{ route('transfers.store') }}" method="POST">
                            @csrf
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="date">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="note">Note</label>
                                        <input type="text" class="form-control" name="note">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="from_warehouse_id">From Warehouse <span class="text-danger">*</span></label>
                                        <select class="form-control" name="from_warehouse_id" required>
                                            <option value="" disabled selected>Select Source Warehouse</option>
                                            @foreach ($warehouses as $wh)
                                                <option value="{{ $wh->id }}">{{ $wh->warehouse_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="to_warehouse_id">To Warehouse <span class="text-danger">*</span></label>
                                        <select class="form-control" name="to_warehouse_id" required>
                                            <option value="" disabled selected>Select Destination Warehouse</option>
                                            @foreach ($warehouses as $wh)
                                                <option value="{{ $wh->id }}">{{ $wh->warehouse_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <livewire:transfer.product-table />

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    Submit Transfer <i class="bi bi-check"></i>
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

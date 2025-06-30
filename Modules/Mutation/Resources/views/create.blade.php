@extends('layouts.app')

@section('title', 'Create Mutation')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('mutations.index') }}">Mutations</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <livewire:search-product/>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        @include('utils.alerts')
                        <form action="{{ route('mutations.store') }}" method="POST">
                            @csrf
                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">
                                        <label for="reference">Reference <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="reference" required>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="date">Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="date" required value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="mutation_type">Type <span class="text-danger">*</span></label>
                                            <select name="mutation_type" id="mutation_type" class="form-control">
                                                <option value="" hidden disabled selected>Select Mutation Type</option>
                                                <option value="In">(+) Mutation In</option>
                                                <option value="Out">(-) Mutation Out</option>
                                                <option value="Transfer">(-/+) Transfer Warehouse</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>      
                            </div>
                            <div class="form-row">
                                <div class="col-lg-6" id="container_warehouse_out">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="warehouse_out_id">From Warehouse <span class="text-danger">*</span></label>
                                            <select class="form-control" name="warehouse_out_id" id="warehouse_out_id">
                                                <option value="" selected disabled>Select Warehouse</option>
                                                @foreach(\Modules\Product\Entities\Warehouse::all() as $warehouse)
                                                    <option value="{{ $warehouse->id }}">{{ $warehouse->warehouse_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>      
                                <div class="col-lg-6" id="container_warehouse_in">
                                    <div class="from-group">
                                        <div class="form-group">
                                            <label for="warehouse_in_id">To Warehouse <span class="text-danger">*</span></label>
                                            <select class="form-control" name="warehouse_in_id" id="warehouse_in_id">
                                                <option value="" selected disabled>Select Warehouse</option>
                                                @foreach(\Modules\Product\Entities\Warehouse::all() as $warehouse)
                                                    <option value="{{ $warehouse->id }}">{{ $warehouse->warehouse_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>      
                            </div>
                            <livewire:mutation.product-table/>
                            <div class="form-group">
                                <label for="note">Note (If Needed)</label>
                                <textarea name="note" id="note" rows="5" class="form-control"></textarea>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    Create Mutation <i class="bi bi-check"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
    <script>
        $(document).ready(function () {
            $("#container_warehouse_out").hide();
            $("#container_warehouse_in").hide();
            $("#mutation_type").change(function () {
                console.log("test");
                if ($(this).val() == "In") {
                    $("#container_warehouse_out").hide();
                    $("#container_warehouse_in").show();
                }
                else if ($(this).val() == "Out") {
                    $("#container_warehouse_out").show();
                    $("#container_warehouse_in").hide();
                }
                else if ($(this).val() == "Transfer") {
                    $("#container_warehouse_out").show();
                    $("#container_warehouse_in").show();
                }

            }); 
    });
    </script>
@endpush

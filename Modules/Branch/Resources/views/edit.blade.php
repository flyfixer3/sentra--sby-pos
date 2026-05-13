@extends('layouts.app')

@section('title', 'Edit Branch')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('branches.index') }}">Branches</a></li>
        <li class="breadcrumb-item active">Edit</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('branches.update', $branch) }}" method="POST"
              data-confirm-submit="true"
              data-confirm-title="Confirm Update?"
              data-confirm-message="Please make sure all changes are correct before updating this branch."
              data-confirm-confirm-text="Yes, update"
              data-confirm-cancel-text="Cancel"
              data-confirm-icon="question">
            @csrf
            @method('patch')
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                    <div class="form-group">
                        <button class="btn btn-primary">Update Branch <i class="bi bi-check"></i></button>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="entity_id">Entity <span class="text-danger">*</span></label>
                                        <select class="form-control" name="entity_id" id="entity_id" required>
                                            <option value="">-- Choose Entity --</option>
                                            @foreach($entities as $entity)
                                                <option value="{{ $entity->id }}" {{ (string) old('entity_id', $branch->entity_id) === (string) $entity->id ? 'selected' : '' }}>
                                                    {{ $entity->name }} ({{ $entity->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Perubahan entity akan memengaruhi grouping laporan cabang ini.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="branch_name">Branch Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="branch_name" required value="{{ old('branch_name', $branch->name) }}">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="branch_address">Address <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="branch_address" required value="{{ old('branch_address', $branch->address) }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-4">
                                    <div class="form-group">    
                                        <label for="branch_phone">Phone <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="branch_phone" required value="{{ old('branch_phone', $branch->phone) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection


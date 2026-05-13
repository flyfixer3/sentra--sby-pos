@extends('layouts.app')

@section('title', 'Create Entity')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entities</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <form action="{{ route('entities.store') }}" method="POST"
              data-confirm-submit="true"
              data-confirm-title="Confirm Create?"
              data-confirm-message="Please make sure all data is correct before creating this entity."
              data-confirm-confirm-text="Yes, create"
              data-confirm-cancel-text="Cancel"
              data-confirm-icon="question">
            @csrf
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                    <div class="form-group">
                        <button class="btn btn-primary">Create Entity <i class="bi bi-check"></i></button>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="name">Entity Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required value="{{ old('name') }}">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="code">Entity Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="code" required value="{{ old('code') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-lg-8">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <textarea class="form-control" name="description" rows="3">{{ old('description') }}</textarea>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="form-group mt-lg-4 pt-lg-2">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="is_active">Active Entity</label>
                                        </div>
                                        <small class="form-text text-muted">Entity aktif bisa dipilih saat membuat branch baru.</small>
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

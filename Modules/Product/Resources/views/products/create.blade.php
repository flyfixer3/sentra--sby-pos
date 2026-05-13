@extends('layouts.app')

@section('title', 'Create Product')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item active">Add</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <form id="product-form" action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data"
              data-confirm-submit="true"
              data-confirm-title="Confirm Create?"
              data-confirm-message="Please make sure all data is correct before creating this record."
              data-confirm-confirm-text="Yes, create"
              data-confirm-cancel-text="Cancel"
              data-confirm-icon="question">
            @csrf
            <div class="row">
                <div class="col-lg-12">
                    @include('utils.alerts')
                    <div class="form-group">
                        <button class="btn btn-primary">Create Product <i class="bi bi-check"></i></button>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_name">Product Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="product_name" required value="{{ old('product_name') }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_code">Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="product_code" required value="{{ old('product_code') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category_id">Category <span class="text-danger">*</span></label>
                                        <select class="form-control" name="category_id" id="category_id" required>
                                            <option value="" {{ old('category_id') ? '' : 'selected' }} disabled>Select Category</option>
                                            @foreach(\Modules\Product\Entities\Category::all() as $category)
                                                <option value="{{ $category->id }}" {{ (string) old('category_id') === (string) $category->id ? 'selected' : '' }}>
                                                    {{ $category->category_code }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Accessories <span class="text-danger">*</span></label>
                                        <div class="border rounded p-3" style="max-height: 220px; overflow:auto;">
                                            @forelse($accessories as $accessory)
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="accessory_ids[]" value="{{ $accessory->id }}" id="accessory_{{ $accessory->id }}"
                                                        {{ in_array((string) $accessory->id, array_map('strval', old('accessory_ids', [])), true) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="accessory_{{ $accessory->id }}">
                                                        <strong>{{ $accessory->accessory_code }}</strong>
                                                        <span class="text-muted">- {{ $accessory->accessory_name }}</span>
                                                    </label>
                                                </div>
                                            @empty
                                                <div class="text-muted small">No accessories available. Create accessories first.</div>
                                            @endforelse
                                        </div>
                                        <small class="text-muted d-block mt-2">The first selected accessory is kept as the legacy primary ACC code for compatibility.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="d-flex align-items-center justify-content-between" for="barcode_symbology">
                                            <span>Barcode Setup <span class="text-danger">*</span></span>
                                            <button class="btn btn-link btn-sm p-0" type="button" data-toggle="collapse" data-target="#barcodeAdvancedCreate" aria-expanded="false" aria-controls="barcodeAdvancedCreate">
                                                Advanced
                                            </button>
                                        </label>
                                        <div class="border rounded p-3">
                                            <div class="font-weight-bold">Default Label Format: Code 128</div>
                                            <div class="small text-muted">Use the default unless a specific scanner or supplier format requires another symbology.</div>
                                        </div>
                                        <div class="collapse mt-2" id="barcodeAdvancedCreate">
                                            <select class="form-control" name="product_barcode_symbology" id="barcode_symbology" required>
                                                <option value="C128" {{ old('product_barcode_symbology', 'C128') === 'C128' ? 'selected' : '' }}>Code 128</option>
                                                <option value="C39" {{ old('product_barcode_symbology') === 'C39' ? 'selected' : '' }}>Code 39</option>
                                                <option value="UPCA" {{ old('product_barcode_symbology') === 'UPCA' ? 'selected' : '' }}>UPC-A</option>
                                                <option value="UPCE" {{ old('product_barcode_symbology') === 'UPCE' ? 'selected' : '' }}>UPC-E</option>
                                                <option value="EAN13" {{ old('product_barcode_symbology') === 'EAN13' ? 'selected' : '' }}>EAN-13</option>
                                                <option value="EAN8" {{ old('product_barcode_symbology') === 'EAN8' ? 'selected' : '' }}>EAN-8</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_cost">Cost <span class="text-danger">*</span></label>
                                        <input id="product_cost" type="text" class="form-control" name="product_cost" value="{{ old('product_cost') }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_price">Default Selling Price <span class="text-danger">*</span></label>
                                        <input id="product_price" type="text" class="form-control" name="product_price" value="{{ old('product_price') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_price_item_only">Item-Only Price</label>
                                        <input id="product_price_item_only" type="text" class="form-control" name="product_price_item_only" value="{{ old('product_price_item_only') }}">
                                        <small class="text-muted">Defaults to the selling price when left blank.</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="installation_service_price">Installation Service Price</label>
                                        <input id="installation_service_price" type="text" class="form-control" name="installation_service_price" value="{{ old('installation_service_price') }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_price_package">Glass + Installation Package Price</label>
                                        <input id="product_price_package" type="text" class="form-control" name="product_price_package" value="{{ old('product_price_package') }}">
                                        <small class="text-muted">Defaults to the selling price when left blank.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_quantity">Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="product_quantity" required value="{{ old('product_quantity') }}" min="0" placeholder="0" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_stock_alert">Alert Quantity <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="product_stock_alert" required value="{{ old('product_stock_alert') }}" min="-1" max="100">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_order_tax">Tax (%)</label>
                                        <input type="number" class="form-control" name="product_order_tax" value="{{ old('product_order_tax') }}" min="1">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_tax_type">Tax type</label>
                                        <select class="form-control" name="product_tax_type" id="product_tax_type">
                                            <option value="" selected disabled>Select Tax Type</option>
                                            <option value="1">Exclusive</option>
                                            <option value="2">Inclusive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="product_unit">Unit <i class="bi bi-question-circle-fill text-info" data-toggle="tooltip" data-placement="top" title="This text will be placed after Product Quantity."></i> <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="product_unit" value="{{ old('product_unit') }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="product_note">Note</label>
                                <textarea name="product_note" id="product_note" rows="4" class="form-control">{{ old('product_note') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="image">Product Images <i class="bi bi-question-circle-fill text-info" data-toggle="tooltip" data-placement="top" title="Max Files: 3, Max File Size: 1MB, Image Size: 400x400"></i></label>
                                <div class="dropzone d-flex flex-wrap align-items-center justify-content-center" id="document-dropzone">
                                    <div class="dz-message" data-dz-message>
                                        <i class="bi bi-cloud-arrow-up"></i>
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

@section('third_party_scripts')
    <script src="{{ asset('js/dropzone.js') }}"></script>
@endsection

@push('page_scripts')
    <script>
        var uploadedDocumentMap = {}
        Dropzone.options.documentDropzone = {
            url: '{{ route('dropzone.upload') }}',
            maxFilesize: 1,
            acceptedFiles: '.jpg, .jpeg, .png',
            maxFiles: 3,
            addRemoveLinks: true,
            dictRemoveFile: "<i class='bi bi-x-circle text-danger'></i> remove",
            headers: {
                "X-CSRF-TOKEN": "{{ csrf_token() }}"
            },
            success: function (file, response) {
                $('form').append('<input type="hidden" name="document[]" value="' + response.name + '">');
                uploadedDocumentMap[file.name] = response.name;
            },
            removedfile: function (file) {
                file.previewElement.remove();
                var name = '';
                if (typeof file.file_name !== 'undefined') {
                    name = file.file_name;
                } else {
                    name = uploadedDocumentMap[file.name];
                }
                $.ajax({
                    type: "POST",
                    url: "{{ route('dropzone.delete') }}",
                    data: {
                        '_token': "{{ csrf_token() }}",
                        'file_name': `${name}`
                    },
                });
                $('form').find('input[name="document[]"][value="' + name + '"]').remove();
            },
            init: function () {
                @if(isset($product) && $product->getMedia('images'))
                var files = {!! json_encode($product->getMedia('images')) !!};
                for (var i in files) {
                    var file = files[i];
                    this.options.addedfile.call(this, file);
                    this.options.thumbnail.call(this, file, file.original_url);
                    file.previewElement.classList.add('dz-complete');
                    $('form').append('<input type="hidden" name="document[]" value="' + file.file_name + '">');
                }
                @endif
            }
        }
    </script>

    <script>
        $(document).ready(function () {
            $('#product-form').submit(function () {
                ['#product_cost', '#product_price', '#product_price_item_only', '#installation_service_price', '#product_price_package'].forEach(function (selector) {
                    var input = $(selector)[0];
                    if (!input) {
                        return;
                    }
                    var newNumber = parseInt((input.value || "").toString().replace(/[^\d-]/g, ""), 10) || 0;
                    $(selector).val(input.value === '' ? '' : newNumber);
                });
            });
        });
    </script>
@endpush

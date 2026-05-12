<div>
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
    <div class="card">
        <div class="card-body">
            <div class="table-responsive-md">
                <table class="table table-bordered mb-0">
                    <thead>
                    <tr class="align-middle">
                        <th class="align-middle">Product Name</th>
                        <th class="align-middle">Code</th>
                        <th class="align-middle">
                            Quantity <i class="bi bi-question-circle-fill text-info" data-toggle="tooltip" data-placement="top" title="Max Quantity: 100"></i>
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        @if(!empty($product))
                            <td class="align-middle">{{ $product->product_name }}</td>
                            <td class="align-middle">{{ $product->product_code }}</td>
                            <td class="align-middle text-center" style="width: 200px;">
                                <input wire:model="quantity" class="form-control" type="number" min="1" max="100" value="{{ $quantity }}">
                            </td>
                        @else
                            <td colspan="3" class="text-center">
                                <span class="text-danger">Please search & select a product!</span>
                            </td>
                        @endif
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button wire:click="generateBarcodes({{ !empty($product) ? $product->id : 0 }}, {{ $quantity }})" type="button" class="btn btn-primary" @if(empty($product)) disabled @endif>
                    <i class="bi bi-upc-scan"></i> Generate Barcodes
                </button>
            </div>
        </div>
    </div>

    <div wire:loading wire:target="generateBarcodes" class="w-100">
        <div class="d-flex justify-content-center">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
    </div>

    @if(!empty($labels))
        <div class="text-right mb-3">
            <button wire:click="getPdf" wire:loading.attr="disabled" type="button" class="btn btn-primary">
                <span wire:loading wire:target="getPdf" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <i wire:loading.remove wire:target="getPdf" class="bi bi-file-earmark-pdf"></i> Download PDF
            </button>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="row justify-content-center">
                    @foreach($labels as $label)
                        <div class="col-lg-4 col-md-6 mb-3">
                            <div class="border rounded p-3 h-100" style="background:#f8fafc;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>{{ $label['title'] }}</strong>
                                    <span class="badge badge-primary">{{ $label['condition'] }}</span>
                                </div>
                                <div class="font-weight-bold">{{ $label['product_name'] }}</div>
                                <div class="small text-muted mb-2">{{ $label['product_code'] }}</div>
                                <div>{!! $label['barcode_svg'] !!}</div>
                                <div class="small text-dark mt-2">Scan Key: {{ $label['encoded_value'] }}</div>
                                @foreach($label['details'] as $detailLabel => $detailValue)
                                    <div class="small"><strong>{{ $detailLabel }}:</strong> {{ $detailValue ?: '-' }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

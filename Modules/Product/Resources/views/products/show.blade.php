@extends('layouts.app')

@section('title', 'Product Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row mb-3 align-items-center">
            <div class="col-lg-8">
                {!! \Milon\Barcode\Facades\DNS1DFacade::getBarCodeSVG($product->product_code, $product->product_barcode_symbology, 2, 110) !!}
            </div>
            <div class="col-lg-4 text-lg-right mt-3 mt-lg-0">
                @can('print_barcodes')
                    <a href="{{ route('products.labels.good', $product->id) }}" target="_blank" class="btn btn-primary">
                        <i class="bi bi-printer-fill"></i> Print GOOD Label
                    </a>
                @endcan
            </div>
        </div>
        <div class="row">
            <div class="col-lg-9">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <tr>
                                    <th>Product Code</th>
                                    <td>{{ $product->product_code }}</td>
                                </tr>
                                <tr>
                                    <th>Barcode Symbology</th>
                                    <td>{{ $product->product_barcode_symbology }}</td>
                                </tr>
                                <tr>
                                    <th>Name</th>
                                    <td>{{ $product->product_name }}</td>
                                </tr>
                                <tr>
                                    <th>Category</th>
                                    <td>{{ $product->category->category_name }}</td>
                                </tr>
                                <tr>
                                    <th>Accessories</th>
                                    <td>
                                        @forelse($product->accessories as $accessory)
                                            <span class="badge badge-light border mr-1 mb-1">{{ $accessory->accessory_code }}</span>
                                        @empty
                                            <span class="text-muted">-</span>
                                        @endforelse
                                    </td>
                                </tr>
                                <tr>
                                    <th>Current HPP</th>
                                    <td>
                                        @if($activeBranchId)
                                            {{ format_currency((float) ($currentBranchHpp ?? 0)) }}
                                            <small class="text-muted d-block">Moving average for active branch.</small>
                                        @else
                                            <span class="text-muted">Select a specific branch to view branch HPP.</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Default Selling Price</th>
                                    <td>{{ format_currency($product->product_price) }}</td>
                                </tr>
                                <tr>
                                    <th>Item-Only Price</th>
                                    <td>{{ $product->product_price_item_only !== null ? format_currency($product->product_price_item_only) : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Installation Service Price</th>
                                    <td>{{ $product->installation_service_price !== null ? format_currency($product->installation_service_price) : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Package Price</th>
                                    <td>{{ $product->product_price_package !== null ? format_currency($product->product_price_package) : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Quantity</th>
                                    <td>{{ $product->product_quantity . ' ' . $product->product_unit }}</td>
                                </tr>
                                <tr>
                                    <th>Branch Stock Worth</th>
                                    <td>
                                        @if($activeBranchId)
                                            HPP:: {{ format_currency(((float) ($currentBranchHpp ?? 0)) * ((int) ($currentBranchStockOnHand ?? 0))) }} /
                                            PRICE:: {{ format_currency($product->product_price * ((int) ($currentBranchStockOnHand ?? 0))) }}
                                            <small class="text-muted d-block">
                                                Based on active branch on-hand stock: {{ (int) ($currentBranchStockOnHand ?? 0) }} {{ stock_unit_label($product->product_unit) }}.
                                            </small>
                                        @else
                                            <span class="text-muted">Select a specific branch to view branch stock worth.</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Alert Quantity</th>
                                    <td>{{ $product->product_stock_alert }}</td>
                                </tr>
                                <tr>
                                    <th>Tax (%)</th>
                                    <td>{{ $product->product_order_tax ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Tax Type</th>
                                    <td>
                                        @if($product->product_tax_type == 1)
                                            Exclusive
                                        @elseif($product->product_tax_type == 2)
                                            Inclusive
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Brand</th>
                                    <td>{{ optional($product->brand)->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Created By</th>
                                    <td>{{ optional($product->creator)->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Created At</th>
                                    <td>{{ $product->created_at ? \Carbon\Carbon::parse($product->created_at)->format('d M Y H:i') : '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Last Updated By</th>
                                    <td>{{ optional($product->updater)->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Last Updated At</th>
                                    <td>{{ $product->updated_at ? \Carbon\Carbon::parse($product->updated_at)->format('d M Y H:i') : '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="mb-3">Product Knowledge / Notes</h5>
                        <div class="p-3 border rounded" style="background:#f8fafc; min-height:120px;">
                            {!! nl2br(e($product->product_note ?: 'No product knowledge note has been recorded yet.')) !!}
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Stock Location Helper</h5>
                            @if(!$activeBranchId)
                                <span class="text-muted small">Select a specific branch to view warehouse and rack helper data.</span>
                            @endif
                        </div>

                        @if($activeBranchId && $stockLocationHints->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Branch</th>
                                            <th>Warehouse</th>
                                            <th>Rack</th>
                                            <th class="text-right">Good</th>
                                            <th class="text-right">Defect</th>
                                            <th class="text-right">Damaged</th>
                                            <th class="text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($stockLocationHints as $hint)
                                            <tr>
                                                <td>{{ $hint->branch_name }}</td>
                                                <td>{{ $hint->warehouse_name }}</td>
                                                <td>{{ trim(($hint->rack_code ? $hint->rack_code . ' - ' : '') . $hint->rack_name) ?: '-' }}</td>
                                                <td class="text-right">{{ (int) $hint->qty_good }}</td>
                                                <td class="text-right">{{ (int) $hint->qty_defect }}</td>
                                                <td class="text-right">{{ (int) $hint->qty_damaged }}</td>
                                                <td class="text-right font-weight-bold">{{ (int) $hint->qty_total }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif($activeBranchId)
                            <div class="text-muted">No active stock location snapshot found for this branch.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        @forelse($product->getMedia('images') as $media)
                            <img src="{{ $media->getUrl() }}" alt="Product Image" class="img-fluid img-thumbnail mb-2">
                        @empty
                            <img src="{{ $product->getFirstMediaUrl('images') }}" alt="Product Image" class="img-fluid img-thumbnail mb-2">
                        @endforelse
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="mb-3">Image Checklist</h5>
                        @foreach([
                            'Full Car',
                            'Glass On Car',
                            'Glass Detail',
                            'Sensor',
                            'Full Glass',
                            'Logo / Brand Marking',
                        ] as $slot)
                            <div class="border rounded p-3 mb-2" style="background:#f8fafc;">
                                <div class="font-weight-bold">{{ $slot }}</div>
                                <div class="small text-muted">Placeholder for dedicated image slot in phase 2.</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @include('includes.edit-activity-log', ['model' => $product])
    </div>
@endsection

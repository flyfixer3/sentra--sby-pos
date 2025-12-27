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

    <div class="table-responsive position-relative">
        <div wire:loading.flex class="col-12 position-absolute justify-content-center align-items-center"
            style="top:0;right:0;left:0;bottom:0;background-color: rgba(255,255,255,0.5);z-index: 99;">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr class="align-middle">
                    <th class="align-middle">#</th>
                    <th class="align-middle">Product Name</th>
                    <th class="align-middle">Code</th>
                    <th class="align-middle text-center">Stock (From WH)</th>
                    <th class="align-middle">Quantity</th>
                    <th class="align-middle text-center">Action</th>
                </tr>
            </thead>

            <tbody>
                @if(!empty($products) && count($products) > 0)
                    @foreach($products as $key => $p)
                        @php
                            $productId = $p['id'] ?? null;
                            $name      = $p['product_name'] ?? '-';
                            $code      = $p['product_code'] ?? '-';
                            $unit      = $p['product_unit'] ?? '';
                            $stockQty  = (int)($p['stock_qty'] ?? 0);
                            $qtyInput  = (int)($p['quantity'] ?? 1);
                            if ($qtyInput < 1) $qtyInput = 1;
                        @endphp

                        <tr>
                            <td class="align-middle">{{ $key + 1 }}</td>
                            <td class="align-middle">{{ $name }}</td>
                            <td class="align-middle">{{ $code }}</td>

                            <td class="align-middle text-center">
                                <span class="badge badge-info">
                                    {{ $stockQty }} {{ $unit }}
                                </span>
                            </td>

                            <input type="hidden" name="product_ids[]" value="{{ $productId }}">

                            <td class="align-middle" style="width: 160px;">
                                <input type="number"
                                    name="quantities[]"
                                    min="1"
                                    class="form-control"
                                    value="{{ $qtyInput }}">
                            </td>

                            <td class="align-middle text-center" style="width: 90px;">
                                <button type="button" class="btn btn-danger" wire:click="removeProduct({{ $key }})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="6" class="text-center">
                            <span class="text-danger">Please search & select products!</span>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

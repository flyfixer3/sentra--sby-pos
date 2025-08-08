@extends('layouts.app')

@section('title', 'Confirm Transfer')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">Transfers</a></li>
        <li class="breadcrumb-item active">Confirm</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        Confirm Transfer #{{ $transfer->reference }}
                    </div>
                    <div class="card-body">
                        @include('utils.alerts')

                        <p><strong>Date:</strong> {{ $transfer->date }}</p>
                        <p><strong>From Warehouse:</strong> {{ $transfer->fromWarehouse->warehouse_name ?? '-' }}</p>
                        <p><strong>To Branch:</strong> {{ $transfer->toBranch->name ?? '-' }}</p>
                        <p><strong>Note:</strong> {{ $transfer->note ?? '-' }}</p>

                        <hr>

                        <h5>Items</h5>
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transfer->items as $item)
                                    <tr>
                                        <td>{{ $item->product->name }}</td>
                                        <td>{{ $item->quantity }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <form id="confirm-form" action="{{ route('transfers.confirm.store', $transfer->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            <div class="form-group mt-4">
                                <label for="to_warehouse_id">Destination Warehouse <span class="text-danger">*</span></label>
                                <select name="to_warehouse_id" class="form-control" required>
                                    <option value="">-- Select Warehouse --</option>
                                    @foreach (\Modules\Product\Entities\Warehouse::where('branch_id', session('active_branch'))->get() as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->warehouse_name }}</option>
                                    @endforeach
                                </select>
                                @error('to_warehouse_id')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mt-3">
                                <label for="delivery_proof">Upload Signed Delivery Proof (Surat Jalan) <span class="text-danger">*</span></label>
                                <input type="file" name="delivery_proof" class="form-control" accept="image/*,.pdf" required>
                                @error('delivery_proof')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="button" class="btn btn-success mt-4" onclick="confirmSubmit()">
                                Confirm & Receive <i class="bi bi-check2-circle"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('page_scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmSubmit() {
        Swal.fire({
            title: 'Yakin ingin konfirmasi transfer ini?',
            text: "Setelah dikonfirmasi, data tidak dapat diubah!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, konfirmasi',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('confirm-form').submit();
            }
        });
    }
</script>
@endpush

@extends('layouts.app')

@section('title', 'Sales Details')

@section('breadcrumb')
    <ol class="breadcrumb border-0 m-0">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('sales.index') }}">Sales</a></li>
        <li class="breadcrumb-item active">Details</li>
    </ol>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center">
                        <div>
                            Reference: <strong>{{ $sale->reference }}</strong>
                        </div>
                        <a target="_blank" class="btn btn-sm btn-secondary mfs-auto mfe-1 d-print-none" href="{{ route('sales.pdf', $sale->id) }}">
                            <i class="bi bi-printer"></i> Print
                        </a>
                        <a target="_blank" class="btn btn-sm btn-info mfe-1 d-print-none" href="{{ route('sales.pdf', $sale->id) }}">
                            <i class="bi bi-save"></i> Save
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-sm-4 mb-3 mb-md-0">
                                <h5 class="mb-2 border-bottom pb-2">Company Info:</h5>
                                <div><strong>{{ settings()->company_name }}</strong></div>
                                <div>{{ settings()->company_address }}</div>
                                <div>Email: {{ settings()->company_email }}</div>
                                <div>Phone: {{ settings()->company_phone }}</div>
                            </div>

                            <div class="col-sm-4 mb-3 mb-md-0">
                                <h5 class="mb-2 border-bottom pb-2">Customer Info:</h5>
                                <div><strong>{{ $customer->customer_name }}</strong></div>
                                <div>{{ $customer->address }}</div>
                                <div>Email: {{ $customer->customer_email }}</div>
                                <div>Phone: {{ $customer->customer_phone }}</div>
                            </div>

                            <div class="col-sm-4 mb-3 mb-md-0">
                                <h5 class="mb-2 border-bottom pb-2">Invoice Info:</h5>
                                <div>Invoice: <strong>INV/{{ $sale->reference }}</strong></div>
                                <div>Date: {{ \Carbon\Carbon::parse($sale->date)->format('d M, Y') }}</div>
                                <div>
                                    Payment Status: <strong>{{ $sale->payment_status }}</strong>
                                </div>

                                {{-- ✅ Sale note (from create/edit) --}}
                                @if(!empty($sale->note))
                                    <div class="mt-2">
                                        <div class="text-muted" style="font-size: 12px;">Note:</div>
                                        <div><strong>{{ $sale->note }}</strong></div>
                                    </div>
                                @endif

                                {{-- ✅ Deposit from Sale Order (Single source of truth) --}}
                                @php
                                    $allocatedDp = (int) ($sale->dp_allocated_amount ?? 0);
                                @endphp

                                @if(!empty($saleOrderDepositInfo) && ((int)($saleOrderDepositInfo['deposit_total'] ?? 0) > 0 || $allocatedDp > 0))
                                    <div class="mt-2 p-2" style="border:1px solid rgba(0,0,0,.08); border-radius:8px;">
                                        <div class="text-muted" style="font-size: 12px;">Deposit (From Sale Order)</div>

                                        @if(!empty($saleOrderDepositInfo['sale_order_reference']))
                                            <div>
                                                SO: <strong>{{ $saleOrderDepositInfo['sale_order_reference'] }}</strong>
                                            </div>
                                        @endif

                                        <div>
                                            Total DP (SO): <strong>{{ format_currency((int)($saleOrderDepositInfo['deposit_total'] ?? 0)) }}</strong>
                                        </div>

                                        <div>
                                            Allocated to this Invoice:
                                            <strong>{{ format_currency($allocatedDp) }}</strong>
                                        </div>

                                        <div class="text-muted" style="font-size: 12px; margin-top: 4px;">
                                            Catatan: DP Allocated ini dipakai sebagai pengurang invoice (single source of truth).
                                        </div>
                                    </div>
                                @endif

                                {{-- ✅ Delivery links --}}
                                <div class="mt-2">
                                    <div class="text-muted" style="font-size: 12px;">Sale Delivery:</div>

                                    @if(isset($saleDeliveries) && $saleDeliveries->count() > 0)
                                        @foreach($saleDeliveries as $sd)
                                            <div class="d-flex align-items-center" style="gap: 8px;">
                                                <a href="{{ route('sale-deliveries.show', $sd->id) }}" class="text-decoration-none">
                                                    <strong>
                                                        {{ $sd->reference ?? ('SD#'.$sd->id) }}
                                                    </strong>
                                                </a>

                                                @php
                                                    $st = strtolower((string)($sd->status ?? 'pending'));
                                                    $badgeClass = match($st) {
                                                        'pending' => 'bg-warning text-dark',
                                                        'confirmed' => 'bg-success',
                                                        'partial' => 'bg-info text-dark',
                                                        'cancelled' => 'bg-danger',
                                                        default => 'bg-secondary',
                                                    };
                                                @endphp

                                                <span class="badge {{ $badgeClass }}">{{ $sd->status }}</span>

                                                @if(!empty($sd->note) && str_starts_with((string)$sd->note, '[AUTO]'))
                                                    <span class="badge bg-secondary">AUTO</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <div>-</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive-sm">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th class="align-middle">Product</th>
                                    <th class="align-middle">Net Unit Price</th>
                                    <th class="align-middle">Quantity</th>
                                    <th class="align-middle">Discount</th>
                                    <th class="align-middle">Tax</th>
                                    <th class="align-middle">Sub Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($sale->saleDetails as $item)
                                    <tr>
                                        <td class="align-middle">
                                            {{ $item->product_name }} <br>
                                            <span class="badge badge-success">
                                                {{ $item->product_code }}
                                            </span>
                                        </td>

                                        <td class="align-middle">{{ format_currency($item->unit_price) }}</td>

                                        <td class="align-middle">
                                            {{ $item->quantity }}
                                        </td>

                                        <td class="align-middle">
                                            {{ format_currency($item->product_discount_amount) }}
                                        </td>

                                        <td class="align-middle">
                                            {{ format_currency($item->product_tax_amount) }}
                                        </td>

                                        <td class="align-middle">
                                            {{ format_currency($item->sub_total) }}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="row">
                            <div class="col-lg-5 col-sm-5 d-flex flex-column mt-auto">
                                <div class="mt-auto d-flex justify-content-between">
                                    <span><strong>Created At:</strong> {{ \Carbon\Carbon::parse($sale->created_at)->format('d M, Y H:i') }} <strong>By:</strong> {{ $sale->creator->name ?? 'System' }}</span>
                                    <span><strong>Last Updated At:</strong> {{ \Carbon\Carbon::parse($sale->updated_at)->format('d M, Y H:i') }} <strong>By:</strong> {{ $sale->updater->name ?? 'System' }}</span>
                                </div>
                            </div>

                            <div class="col-lg-4 col-sm-5 ml-md-auto">
                                @php
                                    $grandTotal = (int) $sale->total_amount;
                                    $paidInvoice = (int) $sale->paid_amount;

                                    $netAfterDp = $allocatedDp > 0 ? max(0, $grandTotal - $allocatedDp) : $grandTotal;
                                    $remainingAfterDp = max(0, $netAfterDp - $paidInvoice);
                                @endphp

                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <td class="left"><strong>Discount ({{ $sale->discount_percentage }}%)</strong></td>
                                            <td class="right">{{ format_currency((int)$sale->discount_amount) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="left"><strong>Tax ({{ $sale->tax_percentage }}%)</strong></td>
                                            <td class="right">{{ format_currency((int)$sale->tax_amount) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="left"><strong>Shipping</strong></td>
                                            <td class="right">{{ format_currency((int)$sale->shipping_amount) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="left"><strong>Fee</strong></td>
                                            <td class="right">{{ format_currency((int)($sale->fee_amount ?? 0)) }}</td>
                                        </tr>

                                        <tr>
                                            <td class="left"><strong>Grand Total</strong></td>
                                            <td class="right"><strong>{{ format_currency($grandTotal) }}</strong></td>
                                        </tr>

                                        @if($allocatedDp > 0)
                                            <tr>
                                                <td class="left"><strong>Less: DP Allocated (SO)</strong></td>
                                                <td class="right">- {{ format_currency($allocatedDp) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="left"><strong>Net Invoice Total</strong></td>
                                                <td class="right"><strong>{{ format_currency($netAfterDp) }}</strong></td>
                                            </tr>
                                        @endif

                                        <tr>
                                            <td class="left"><strong>Total Paid</strong></td>
                                            <td class="right">{{ format_currency($paidInvoice) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="left"><strong>Remaining Due</strong></td>
                                            <td class="right"><strong>{{ format_currency($remainingAfterDp) }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- ✅ Payment History (Invoice) --}}
                        @if(isset($salePayments) && $salePayments->count() > 0)
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5 class="mb-2 border-bottom pb-2">Payment History</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                            <tr>
                                                <th class="align-middle">Date</th>
                                                <th class="align-middle">Reference</th>
                                                <th class="align-middle">Method</th>
                                                <th class="align-middle text-right">Amount</th>
                                                <th class="align-middle">Note</th>
                                                <th class="align-middle text-center" style="width: 90px;">Action</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($salePayments as $p)
                                                <tr>
                                                    <td class="align-middle">{{ $p->date }}</td>
                                                    <td class="align-middle">{{ $p->reference }}</td>
                                                    <td class="align-middle">{{ $p->payment_method }}</td>
                                                    <td class="align-middle text-right">{{ format_currency((int)$p->amount) }}</td>
                                                    <td class="align-middle">{{ $p->note }}</td>
                                                    <td class="align-middle text-center">
                                                        <a target="_blank" href="{{ route('sale-payments.receipt', $p->id) }}" class="btn btn-sm btn-secondary" title="Print Receipt">
                                                            <i class="bi bi-printer"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div><!-- card-body -->
                </div><!-- card -->
            </div>
        </div>
    </div>
@endsection

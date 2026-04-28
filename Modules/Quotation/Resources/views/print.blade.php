<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Quotation Details</title>
    <link rel="stylesheet" href="{{ public_path('b3/bootstrap.min.css') }}">
</head>
<body>
@php
    $settings = settings();
    $printBranch = $branch ?? null;
    $companyName = $printBranch->name ?? $settings->company_name ?? 'Company';
    $companyAddress = $printBranch->address ?? $settings->company_address ?? '-';
    $companyEmail = $printBranch->email ?? $settings->company_email ?? '-';
    $companyPhone = $printBranch->phone ?? $settings->company_phone ?? '-';
    $statusValue = strtolower(trim((string) ($quotation->status ?? 'pending')));
    if ($statusValue === 'sent') {
        $statusValue = 'completed';
    }
    $statusLabel = match ($statusValue) {
        'pending' => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => strtoupper($statusValue),
    };
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-xs-12">
            <div style="text-align: center;margin-bottom: 25px;">
                <img width="180" src="{{ public_path('images/logo-dark.png') }}" alt="Logo">
                <h4 style="margin-bottom: 20px;">
                    <span>Reference::</span> <strong>{{ $quotation->reference }}</strong>
                </h4>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-xs-4 mb-3 mb-md-0">
                            <h4 class="mb-2" style="border-bottom: 1px solid #dddddd;padding-bottom: 10px;">Company Info:</h4>
                            <div><strong>{{ $companyName }}</strong></div>
                            <div>{{ $companyAddress }}</div>
                            <div>Email: {{ $companyEmail }}</div>
                            <div>Phone: {{ $companyPhone }}</div>
                        </div>

                        <div class="col-xs-4 mb-3 mb-md-0">
                            <h4 class="mb-2" style="border-bottom: 1px solid #dddddd;padding-bottom: 10px;">Customer Info:</h4>
                            <div><strong>{{ $customer->customer_name }}</strong></div>
                            <div>{{ $customer->address }}</div>
                            <div>Email: {{ $customer->customer_email }}</div>
                            <div>Phone: {{ $customer->customer_phone }}</div>
                        </div>

                        <div class="col-xs-4 mb-3 mb-md-0">
                            <h4 class="mb-2" style="border-bottom: 1px solid #dddddd;padding-bottom: 10px;">Invoice Info:</h4>
                            <div>Invoice: <strong>INV/{{ $quotation->reference }}</strong></div>
                            <div>Date: {{ \Carbon\Carbon::parse($quotation->date)->format('d M, Y') }}</div>
                            <div>
                                Status: <strong>{{ $statusLabel }}</strong>
                            </div>
                            <div>
                                Payment Status: <strong>{{ $quotation->payment_status }}</strong>
                            </div>
                        </div>

                    </div>

                    <div class="table-responsive-sm" style="margin-top: 30px;">
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th class="align-middle">Product</th>
                                <th class="align-middle">Unit Price</th>
                                <th class="align-middle">Qty</th>
                                <th class="align-middle">Discount</th>
                                <!-- <th class="align-middle">Tax</th> -->
                                <th class="align-middle">Sub Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($quotation->quotationDetails as $item)
                                @php
                                    $installationType = (string) ($item->installation_type ?? 'item_only');
                                    $vehicle = $item->customerVehicle;
                                @endphp
                                <tr>
                                    <td class="align-middle">
                                        {{ $item->product_name }} <br>
                                        <span class="badge badge-success">
                                                {{ $item->product_code }}
                                            </span>
                                        @if($installationType === 'with_installation')
                                            <br>
                                            <small>
                                                Service: With Installation<br>
                                                Vehicle:
                                                @if($vehicle)
                                                    {{ $vehicle->car_plate }}{{ $vehicle->vehicle_name ? ' / ' . $vehicle->vehicle_name : '' }}
                                                @else
                                                    Vehicle not found
                                                @endif
                                            </small>
                                        @endif
                                    </td>
                                    <td class="align-middle">{{ format_currency($item->unit_price) }}</td>

                                    <td class="align-middle">
                                        {{ $item->quantity }}
                                    </td>

                                    <td class="align-middle">
                                        {{ format_currency($item->product_discount_amount) }}
                                    </td>

                                    <!-- <td class="align-middle">
                                        {{ format_currency($item->product_tax_amount) }}
                                    </td> -->

                                    <td class="align-middle">
                                        {{ format_currency($item->sub_total) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="row">
                        <div class="col-xs-4 col-xs-offset-8">
                            <table class="table">
                                <tbody>
                                <tr>
                                    <td class="left"><strong>Discount ({{ $quotation->discount_percentage }}%)</strong></td>
                                    <td class="right">{{ format_currency($quotation->discount_amount) }}</td>
                                </tr>
                                <!-- <tr>
                                    <td class="left"><strong>Tax ({{ $quotation->tax_percentage }}%)</strong></td>
                                    <td class="right">{{ format_currency($quotation->tax_amount) }}</td>
                                </tr> -->
                                <tr>
                                    <td class="left"><strong>Shipping</strong></td>
                                    <td class="right">{{ format_currency($quotation->shipping_amount) }}</td>
                                </tr>
                                <tr>
                                    <td class="left"><strong>Grand Total</strong></td>
                                    <td class="right"><strong>{{ format_currency($quotation->total_amount) }}</strong></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 25px;">
                        <div class="col-xs-12">
                            <p style="font-style: italic;text-align: center">{{ $companyName }} &copy; {{ date('Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

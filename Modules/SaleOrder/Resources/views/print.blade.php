<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Order - {{ $saleOrder->reference ?? $saleOrder->id }}</title>
    <style>
        @page {
            margin: 24px 28px 24px 28px;
        }

        * {
            box-sizing: border-box;
            font-family: DejaVu Sans, sans-serif;
        }

        body {
            margin: 0;
            color: #1b1b1b;
            font-size: 11px;
            line-height: 1.45;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #6b7280; }
        .fw-bold { font-weight: 700; }
        .fw-semibold { font-weight: 600; }

        .document-header {
            width: 100%;
            border-bottom: 1px solid #d9dde3;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }

        .document-header td {
            vertical-align: top;
        }

        .logo {
            width: 154px;
            height: auto;
            margin-bottom: 8px;
        }

        .company-name {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
        }

        .document-title {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin: 0;
            color: #111827;
        }

        .document-ref {
            margin-top: 4px;
            font-size: 11px;
            color: #4b5563;
        }

        .meta-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 18px;
        }

        .meta-grid td {
            width: 33.33%;
            vertical-align: top;
            padding-right: 12px;
        }

        .meta-grid td:last-child {
            padding-right: 0;
        }

        .panel {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
            min-height: 122px;
        }

        .panel-title {
            margin: 0 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 1px solid #edf0f3;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #374151;
        }

        .panel-line {
            margin: 0 0 5px 0;
            word-break: break-word;
        }

        .status-pill {
            display: inline-block;
            padding: 3px 9px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #374151;
            background: #f9fafb;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        .items-table thead th {
            background: #f5f7fa;
            border-top: 1px solid #dfe3e8;
            border-bottom: 1px solid #dfe3e8;
            color: #374151;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            padding: 9px 8px;
            text-align: left;
        }

        .items-table tbody td {
            border-bottom: 1px solid #eceff3;
            padding: 10px 8px;
            vertical-align: top;
        }

        .items-table tbody tr:last-child td {
            border-bottom: 1px solid #dfe3e8;
        }

        .col-product { width: 34%; }
        .col-money { width: 15%; }
        .col-qty { width: 8%; }

        .product-name {
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .product-code {
            display: inline-block;
            padding: 2px 7px;
            border: 1px solid #d9dee5;
            border-radius: 999px;
            background: #f8fafc;
            font-size: 9.5px;
            color: #4b5563;
        }

        .summary-section {
            width: 100%;
            margin-top: 18px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .summary-section td {
            vertical-align: top;
        }

        .note-wrap {
            width: 56%;
            padding-right: 16px;
        }

        .totals-wrap {
            width: 44%;
        }

        .note-box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px 14px;
            min-height: 132px;
        }

        .note-title {
            margin: 0 0 8px 0;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #374151;
        }

        .totals-box {
            border: 1px solid #dfe3e8;
            border-radius: 8px;
            overflow: hidden;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #eceff3;
        }

        .totals-table tr:last-child td {
            border-bottom: none;
        }

        .totals-table .label {
            color: #4b5563;
        }

        .totals-table .amount {
            text-align: right;
            white-space: nowrap;
        }

        .totals-table .grand-row td {
            background: #f8fafc;
            font-weight: 700;
            color: #111827;
        }

        .footer {
            margin-top: 22px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $subtotal = (int) ($saleOrder->subtotal_amount ?? 0);
    $taxAmt = (int) ($saleOrder->tax_amount ?? 0);
    $fee = (int) ($saleOrder->fee_amount ?? 0);
    $ship = (int) ($saleOrder->shipping_amount ?? 0);
    $total = (int) ($saleOrder->total_amount ?? 0);

    $storedDiscountAmount = (int) ($saleOrder->discount_amount ?? 0);
    $baseGrandBeforeDiscount = $subtotal + $taxAmt + $fee + $ship;
    $appliedOrderDiscount = max(0, $baseGrandBeforeDiscount - $total);
    $isManualOrderDiscount = $appliedOrderDiscount > 0;
    $discInfo = $isManualOrderDiscount ? $appliedOrderDiscount : $storedDiscountAmount;

    $dpPlanned = (int) ($saleOrder->deposit_amount ?? 0);
    $dpReceived = (int) ($saleOrder->deposit_received_amount ?? 0);
    $remainingAfterDp = max(0, $total - $dpReceived);

    $statusText = strtoupper((string) ($saleOrder->status ?? 'PENDING'));
    $dateText = $saleOrder->date ? \Carbon\Carbon::parse($saleOrder->date)->format('d M Y') : '-';

    $companyName = settings()->company_name ?? 'Company';
    $companyAddress = settings()->company_address ?? '-';
    $companyEmail = settings()->company_email ?? '-';
    $companyPhone = settings()->company_phone ?? '-';
@endphp

<table class="document-header">
    <tr>
        <td style="width: 56%;">
            <img class="logo" src="{{ public_path('images/logo-dark.png') }}" alt="Logo">
            <div class="company-name">{{ $companyName }}</div>
            <div class="muted">{{ $companyAddress }}</div>
        </td>
        <td style="width: 44%;" class="text-right">
            <h1 class="document-title">Sale Order</h1>
            <div class="document-ref">Reference: <span class="fw-semibold">{{ $saleOrder->reference ?? '-' }}</span></div>
        </td>
    </tr>
</table>

<table class="meta-grid">
    <tr>
        <td>
            <div class="panel">
                <div class="panel-title">Company Info</div>
                <div class="panel-line fw-semibold">{{ $companyName }}</div>
                <div class="panel-line">{{ $companyAddress }}</div>
                <div class="panel-line">Email: {{ $companyEmail }}</div>
                <div class="panel-line">Phone: {{ $companyPhone }}</div>
            </div>
        </td>
        <td>
            <div class="panel">
                <div class="panel-title">Customer Info</div>
                <div class="panel-line fw-semibold">{{ $customer->customer_name ?? '-' }}</div>
                <div class="panel-line">{{ $customer->address ?? '-' }}</div>
                <div class="panel-line">Email: {{ $customer->customer_email ?? '-' }}</div>
                <div class="panel-line">Phone: {{ $customer->customer_phone ?? '-' }}</div>
            </div>
        </td>
        <td>
            <div class="panel">
                <div class="panel-title">Order Summary</div>
                <div class="panel-line">Sale Order: <span class="fw-semibold">{{ $saleOrder->reference ?? '-' }}</span></div>
                <div class="panel-line">Date: {{ $dateText }}</div>
                <div class="panel-line">
                    Status:
                    <span class="status-pill">{{ $statusText }}</span>
                </div>
                <div class="panel-line">DP Planned (Max): <span class="fw-semibold">{{ format_currency($dpPlanned) }}</span></div>
                <div class="panel-line">DP Received: <span class="fw-semibold">{{ format_currency($dpReceived) }}</span></div>
            </div>
        </td>
    </tr>
</table>

<table class="items-table">
    <thead>
    <tr>
        <th class="col-product">Product</th>
        <th class="col-money text-right">Net Unit Price</th>
        <th class="col-qty text-right">Qty</th>
        <th class="col-money text-right">Discount</th>
        <th class="col-money text-right">Tax</th>
        <th class="col-money text-right">Sub Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($saleOrder->items as $item)
        @php
            $unitPrice = (int) ($item->unit_price ?? ($item->price ?? 0));
            $netPrice = (int) ($item->price ?? $unitPrice);
            $itemDiscount = (int) ($item->product_discount_amount ?? max(0, $unitPrice - $netPrice));
            $lineSubtotal = (int) ($item->sub_total ?? ((int) ($item->quantity ?? 0) * $netPrice));
        @endphp
        <tr>
            <td class="col-product">
                <div class="product-name">{{ $item->product_name ?? '-' }}</div>
                <span class="product-code">{{ $item->product_code ?? '-' }}</span>
            </td>
            <td class="col-money text-right">{{ format_currency($netPrice) }}</td>
            <td class="col-qty text-right">{{ (int) ($item->quantity ?? 0) }}</td>
            <td class="col-money text-right">{{ format_currency($itemDiscount) }}</td>
            <td class="col-money text-right">{{ format_currency((int) ($item->product_tax_amount ?? 0)) }}</td>
            <td class="col-money text-right fw-semibold">{{ format_currency($lineSubtotal) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="summary-section">
    <tr>
        <td class="note-wrap">
            <div class="note-box">
                <div class="note-title">Notes</div>
                @if(!empty($saleOrder->note))
                    <div>{{ $saleOrder->note }}</div>
                @else
                    <div class="muted">No additional notes.</div>
                @endif
            </div>
        </td>
        <td class="totals-wrap">
            <div class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td class="label">Items Subtotal</td>
                        <td class="amount">{{ format_currency($subtotal) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Tax</td>
                        <td class="amount">{{ format_currency($taxAmt) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Platform Fee</td>
                        <td class="amount">{{ format_currency($fee) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Shipping</td>
                        <td class="amount">{{ format_currency($ship) }}</td>
                    </tr>
                    @if($discInfo > 0)
                        <tr>
                            <td class="label">{{ $isManualOrderDiscount ? 'Order Discount' : 'Discount Info (Diff)' }}</td>
                            <td class="amount">- {{ format_currency($discInfo) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-row">
                        <td>Grand Total</td>
                        <td class="amount">{{ format_currency($total) }}</td>
                    </tr>
                    <tr>
                        <td class="label">DP Planned (Max)</td>
                        <td class="amount">{{ format_currency($dpPlanned) }}</td>
                    </tr>
                    <tr>
                        <td class="label">DP Received</td>
                        <td class="amount">{{ format_currency($dpReceived) }}</td>
                    </tr>
                    <tr>
                        <td class="label fw-semibold">Remaining After DP</td>
                        <td class="amount fw-semibold">{{ format_currency($remainingAfterDp) }}</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="footer">
    {{ $companyName }} &copy; {{ date('Y') }}
</div>
</body>
</html>

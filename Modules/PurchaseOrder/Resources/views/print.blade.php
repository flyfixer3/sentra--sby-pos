<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - {{ $purchase_order->reference ?? $purchase_order->id }}</title>
    <style>
        @page { margin: 22px 26px; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 0;
            padding: 0;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #666; }
        .fw-bold { font-weight: 700; }

        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .header td {
            vertical-align: top;
            padding: 0;
        }

        .logo {
            width: 150px;
            height: auto;
            margin-bottom: 8px;
        }

        .company-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 4px 0;
        }

        .company-meta {
            margin: 0 0 3px 0;
            line-height: 1.35;
        }

        .document-title {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: .5px;
            margin: 2px 0 6px 0;
        }

        .document-ref {
            font-size: 13px;
            margin: 0;
        }

        .divider {
            border: 0;
            border-top: 2px solid #222;
            margin: 10px 0 12px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 14px;
        }

        .info-table td {
            width: 33.333%;
            border: 1px solid #e3e3e3;
            vertical-align: top;
            padding: 9px 10px;
            line-height: 1.4;
        }

        .info-title {
            font-weight: 700;
            font-size: 12px;
            margin: 0 0 7px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ededed;
        }

        .info-line {
            margin: 0 0 4px 0;
            word-wrap: break-word;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 4px;
        }

        .items th,
        .items td {
            border: 1px solid #d9d9d9;
            padding: 8px 7px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .items th {
            background: #f4f4f4;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
        }

        .col-no { width: 6%; text-align: center; }
        .col-product { width: 38%; }
        .col-price { width: 17%; }
        .col-qty { width: 9%; text-align: center; }
        .col-discount { width: 14%; }
        .col-subtotal { width: 16%; }

        .product-code {
            display: block;
            margin-top: 3px;
            font-size: 10px;
            color: #555;
        }

        .summary-wrap {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .summary-wrap td {
            vertical-align: top;
            padding: 0;
        }

        .summary-box {
            width: 42%;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
        }

        .summary td {
            border-bottom: 1px solid #ececec;
            padding: 6px 4px;
        }

        .summary tr:last-child td {
            border-bottom: 0;
            font-size: 13px;
            font-weight: 700;
        }

        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e3e3e3;
            text-align: center;
            color: #666;
            font-size: 11px;
            line-height: 1.35;
        }
    </style>
</head>
<body>
@php
    $settings = settings();

    $branchName = $branch->name ?? 'Company';
    $branchAddress = $branch->address ?? $settings->company_address ?? '-';
    $branchPhone = $branch->phone ?? $settings->company_phone ?? '-';
    $branchEmail = $branch->email ?? $settings->company_email ?? '-';

    $supplierName = $supplier->supplier_name ?? $purchase_order->supplier_name ?? '-';
    $supplierAddress = $supplier->address ?? '-';
    $supplierEmail = $supplier->supplier_email ?? '-';
    $supplierPhone = $supplier->supplier_phone ?? '-';

    $reference = $purchase_order->reference ?? ('PO-' . $purchase_order->id);
    $poDate = !empty($purchase_order->date)
        ? \Carbon\Carbon::parse($purchase_order->date)->format('d M Y')
        : '-';

    $supplierInvoice = $purchase_order->supplier_invoice ?? $purchase_order->supplier_invoice_number ?? null;
@endphp

<table class="header">
    <tr>
        <td style="width: 55%;">
            <img class="logo" src="{{ public_path('images/logo-dark.png') }}" alt="Logo">
            <p class="company-name">{{ $branchName }}</p>
            <p class="company-meta">{{ $branchAddress }}</p>
            <p class="company-meta">
                Phone: {{ $branchPhone }}
                @if(!empty($branchEmail) && $branchEmail !== '-')
                    | Email: {{ $branchEmail }}
                @endif
            </p>
        </td>
        <td class="text-right" style="width: 45%;">
            <p class="document-title">PURCHASE ORDER</p>
            <p class="document-ref"><span class="muted">Reference:</span> <strong>{{ $reference }}</strong></p>
            <p class="document-ref"><span class="muted">Date:</span> <strong>{{ $poDate }}</strong></p>
        </td>
    </tr>
</table>

<hr class="divider">

<table class="info-table">
    <tr>
        <td>
            <p class="info-title">Branch Info</p>
            <p class="info-line fw-bold">{{ $branchName }}</p>
            <p class="info-line">{{ $branchAddress }}</p>
            <p class="info-line">Phone: {{ $branchPhone }}</p>
            @if(!empty($branchEmail) && $branchEmail !== '-')
                <p class="info-line">Email: {{ $branchEmail }}</p>
            @endif
        </td>
        <td>
            <p class="info-title">Supplier Info</p>
            <p class="info-line fw-bold">{{ $supplierName }}</p>
            <p class="info-line">{{ $supplierAddress }}</p>
            <p class="info-line">Email: {{ $supplierEmail }}</p>
            <p class="info-line">Phone: {{ $supplierPhone }}</p>
        </td>
        <td>
            <p class="info-title">Order Info</p>
            <p class="info-line">Reference: <strong>{{ $reference }}</strong></p>
            @if(!empty($supplierInvoice))
                <p class="info-line">Supplier Invoice: <strong>{{ $supplierInvoice }}</strong></p>
            @endif
            <p class="info-line">Status: <strong>{{ $purchase_order->status ?? '-' }}</strong></p>
            <p class="info-line">Payment Status: <strong>{{ $purchase_order->payment_status ?? '-' }}</strong></p>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th class="col-no">No</th>
        <th class="col-product">Product</th>
        <th class="col-price text-right">Gross Price</th>
        <th class="col-qty">Qty</th>
        <th class="col-discount text-right">Discount</th>
        <th class="col-subtotal text-right">Subtotal</th>
    </tr>
    </thead>
    <tbody>
    @forelse($purchase_order->purchaseOrderDetails as $item)
        <tr>
            <td class="col-no">{{ $loop->iteration }}</td>
            <td>
                <strong>{{ $item->product_name ?? '-' }}</strong>
                <span class="product-code">Code: {{ $item->product_code ?? '-' }}</span>
            </td>
            <td class="text-right">{{ format_currency($item->unit_price ?? 0) }}</td>
            <td class="col-qty">{{ $item->quantity ?? 0 }}</td>
            <td class="text-right">{{ format_currency($item->product_discount_amount ?? 0) }}</td>
            <td class="text-right">{{ format_currency($item->sub_total ?? 0) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="text-center muted">No items found.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<table class="summary-wrap">
    <tr>
        <td style="width: 58%;">&nbsp;</td>
        <td class="summary-box">
            <table class="summary">
                <tr>
                    <td>Discount{{ (float) ($purchase_order->discount_percentage ?? 0) > 0 ? ' (' . $purchase_order->discount_percentage . '%)' : '' }}</td>
                    <td class="text-right">{{ format_currency($purchase_order->discount_amount ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Tax{{ (float) ($purchase_order->tax_percentage ?? 0) > 0 ? ' (' . $purchase_order->tax_percentage . '%)' : '' }}</td>
                    <td class="text-right">{{ format_currency($purchase_order->tax_amount ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Shipping</td>
                    <td class="text-right">{{ format_currency($purchase_order->shipping_amount ?? 0) }}</td>
                </tr>
                <tr>
                    <td>Grand Total</td>
                    <td class="text-right">{{ format_currency($purchase_order->total_amount ?? 0) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="footer">
    {{ $branchName }} &copy; {{ date('Y') }}
    <br>
    {{ $branchAddress }}
</div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $saleDelivery->reference ?? ('SDO-' . $saleDelivery->id) }}</title>
    <style>
        @page { margin: 18px 22px; }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }

        .small { font-size: 11px; }

        .header { width: 100%; text-align: center; margin-bottom: 10px; }

        .company-name {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 4px 0;
        }

        .company-meta { margin: 0; font-size: 12px; }

        .divider {
            border: 0;
            border-top: 2px solid #222;
            margin: 10px 0 14px;
        }

        .title {
            text-align: center;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1px;
            margin: 0 0 6px 0;
        }

        .copy-label {
            text-align:center;
            font-size: 12px;
            font-weight: 800;
            margin: 0 0 10px 0;
            letter-spacing: .5px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 12px;
        }

        .info-table td {
            padding: 6px 8px;
            vertical-align: top;
            font-size: 13px;
        }

        .info-label { font-weight: 700; width: 140px; white-space: nowrap; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 10px;
        }

        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 8px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .items-table thead th {
            background: #f1f1f1;
            font-size: 13px;
            font-weight: 800;
        }

        .col-no { width: 7%; text-align: center; }
        .col-name { width: 50%; }
        .col-qty { width: 10%; text-align: center; }
        .col-unit { width: 10%; text-align: center; }
        .col-note { width: 23%; }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 26px;
        }

        .signature-table td {
            width: 33.333%;
            text-align: center;
            padding: 8px 10px;
            vertical-align: top;
        }

        .signature-title {
            font-weight: 800;
            margin-bottom: 70px;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 80%;
            margin: 0 auto;
            height: 1px;
        }

        .watermark {
            position: fixed;
            top: 42%;
            left: 12%;
            transform: rotate(-28deg);
            font-size: 74px;
            font-weight: 800;
            color: rgba(120,120,120,0.16);
            z-index: 999;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>

@php
    $copyNo = (int) ($copyNumber ?? 1);
    if ($copyNo <= 0) $copyNo = 1;

    $senderName = $senderBranch->name ?? ($setting->company_name ?? 'Sentra Autoglass');
    $senderAddr = $senderBranch->address ?? ($setting->company_address ?? '-');
    $senderPhone = $senderBranch->phone ?? ($setting->company_phone ?? null);

    $ref = $saleDelivery->reference ?? ('SDO-' . $saleDelivery->id);

    $customerName = $saleDelivery->customer->customer_name ?? '-';
    $customerAddr = $saleDelivery->customer->customer_address ?? '-';
    $customerPhone = $saleDelivery->customer->customer_phone ?? null;

    $warehouseName = $saleDelivery->warehouse->warehouse_name ?? '-';
    $saleOrderRef = $saleDelivery->saleOrder->reference ?? null;
@endphp

@if($copyNo > 1)
    <div class="watermark">COPY #{{ $copyNo }}</div>
@endif

<div class="header">
    <div class="company-name">{{ $senderName }}</div>
    <p class="company-meta small">
        {{ $senderAddr }}
        @if(!empty($senderPhone))
            | Telp: {{ $senderPhone }}
        @endif
    </p>
    <hr class="divider">
    <div class="title">SURAT JALAN</div>
    <div class="copy-label">COPY #{{ $copyNo }}</div>
</div>

<table class="info-table">
    <tr>
        <td>
            <span class="info-label">No. Referensi</span>:
            <strong>{{ $ref }}</strong>
        </td>
        <td class="text-right">
            <span class="info-label">Tanggal</span>:
            <strong>{{ \Carbon\Carbon::parse($saleDelivery->date)->format('d M Y') }}</strong>
        </td>
    </tr>
    <tr>
        <td>
            <span class="info-label">Gudang</span>:
            <strong>{{ $warehouseName }}</strong>
        </td>
        <td class="text-right">
            <span class="info-label">Status</span>:
            <strong>{{ strtoupper((string) $saleDelivery->status) }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="info-label">Customer</span>:
            <strong>{{ $customerName }}</strong>
            @if(!empty($customerPhone))
                | {{ $customerPhone }}
            @endif
            <div class="small" style="margin-top:4px;">
                {{ $customerAddr }}
            </div>
        </td>
    </tr>
    @if(!empty($saleOrderRef))
    <tr>
        <td colspan="2">
            <span class="info-label">Sale Order</span>:
            <strong>{{ $saleOrderRef }}</strong>
        </td>
    </tr>
    @endif
</table>

<table class="items-table">
    <thead>
        <tr>
            <th class="col-no">No</th>
            <th class="col-name">Item</th>
            <th class="col-qty">Qty</th>
            <th class="col-unit">Unit</th>
            <th class="col-note">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach($saleDelivery->items as $i => $it)
            @php
                $name = $it->product->product_name ?? ('Product #' . $it->product_id);
                $unit = $it->product->product_unit ?? '';
                $qty = (int) ($it->quantity ?? 0);
                $note = $notesByItemId[(int)$it->id] ?? '';
            @endphp
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $name }}</td>
                <td class="text-center">{{ number_format($qty) }}</td>
                <td class="text-center">{{ $unit }}</td>
                <td>{{ $note }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="signature-table">
    <tr>
        <td>
            <div class="signature-title">Disiapkan</div>
            <div class="signature-line"></div>
        </td>
        <td>
            <div class="signature-title">Dikirim</div>
            <div class="signature-line"></div>
        </td>
        <td>
            <div class="signature-title">Diterima</div>
            <div class="signature-line"></div>
        </td>
    </tr>
</table>

</body>
</html>

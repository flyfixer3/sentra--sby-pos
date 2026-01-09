<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $transfer->reference }}</title>
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
        .muted { color: #444; }

        .header {
            width: 100%;
            text-align: center;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 4px 0;
        }

        .company-meta {
            margin: 0;
            font-size: 12px;
        }

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

        .info-label {
            font-weight: 700;
            width: 140px;
            white-space: nowrap;
        }

        .addr-box {
            border: 1px solid #000;
            padding: 8px 10px;
            margin-top: 6px;
            font-size: 12px;
        }

        .addr-title {
            font-weight: 800;
            margin-bottom: 4px;
        }

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

        .footer-info {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .footer-info td {
            border: 1px solid #000;
            padding: 10px 12px;
            vertical-align: top;
            font-size: 13px;
        }

        .footer-info .box-label {
            font-weight: 800;
            display: inline-block;
            min-width: 115px;
        }

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

        $receiverName = $receiverBranch->name ?? ($transfer->toBranch->name ?? '-');
        $receiverAddr = $receiverBranch->address ?? '-';
        $receiverPhone = $receiverBranch->phone ?? null;
    @endphp

    <div class="watermark">COPY #{{ $copyNo }}</div>

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
                <strong>{{ $transfer->reference }}</strong>
            </td>
            <td class="text-right">
                <span class="info-label">Tanggal</span>:
                <strong>{{ \Carbon\Carbon::parse($transfer->date)->format('d M Y') }}</strong>
            </td>
        </tr>
        <tr>
            <td>
                <span class="info-label">Dari Gudang</span>:
                <strong>{{ $transfer->fromWarehouse->warehouse_name ?? '-' }}</strong>
            </td>
            <td class="text-right">
                <span class="info-label">Ke Cabang</span>:
                <strong>{{ $receiverName }}</strong>
            </td>
        </tr>
    </table>

    <div class="addr-box">
        <div class="addr-title">PENGIRIM</div>
        <div><strong>{{ $senderName }}</strong></div>
        <div>{{ $senderAddr }}</div>
        @if(!empty($senderPhone))
            <div>Telp: {{ $senderPhone }}</div>
        @endif
    </div>

    <div class="addr-box" style="margin-top:8px;">
        <div class="addr-title">PENERIMA</div>
        <div><strong>{{ $receiverName }}</strong></div>
        <div>{{ $receiverAddr }}</div>
        @if(!empty($receiverPhone))
            <div>Telp: {{ $receiverPhone }}</div>
        @endif
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-name">Nama Produk</th>
                <th class="col-qty">Jumlah</th>
                <th class="col-unit">Satuan</th>
                <th class="col-note">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transfer->items as $index => $item)
                @php
                    $productName = $item->product->product_name ?? ($item->product->name ?? '-');
                    $productCode = $item->product->product_code ?? null;
                    $displayName = $productCode ? ($productName . ' | ' . $productCode) : $productName;
                    $unit = 'PCS';

                    $pid = (int) $item->product_id;
                    $note = $notesByProduct[$pid] ?? '';
                @endphp
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td class="col-name">{{ $displayName }}</td>
                    <td class="col-qty">{{ (int) $item->quantity }}</td>
                    <td class="col-unit">{{ $unit }}</td>
                    <td class="col-note">{{ $note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="footer-info">
        <tr>
            <td style="width: 40%;">
                <span class="box-label">No. Referensi</span>:
                <strong>{{ $transfer->reference }}</strong>
            </td>
            <td style="width: 60%;">
                <span class="box-label">Delivery Code</span>:
                <strong>{{ $transfer->delivery_code ?? '-' }}</strong>
            </td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-title">Pengirim</div>
                <div class="signature-line"></div>
            </td>
            <td>
                <div class="signature-title">Supir</div>
                <div class="signature-line"></div>
            </td>
            <td>
                <div class="signature-title">Penerima</div>
                <div class="signature-line"></div>
            </td>
        </tr>
    </table>

</body>
</html>

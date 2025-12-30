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
            font-size: 24px;
            font-weight: 700;
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
            margin: 0 0 10px 0;
        }

        /* Info block (No referensi, tanggal, gudang, cabang) */
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

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 10px;
        }

        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 10px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .items-table thead th {
            background: #f1f1f1;
            font-size: 14px;
            font-weight: 800;
        }

        .col-no { width: 7%; text-align: center; }
        .col-name { width: 58%; }
        .col-qty { width: 13%; text-align: center; }
        .col-unit { width: 10%; text-align: center; }
        .col-note { width: 12%; }

        /* Footer info block (Reference + Delivery Code) */
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

        /* Signature */
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

        /* Watermark */
        .watermark {
            position: fixed;
            top: 42%;
            left: 18%;
            transform: rotate(-28deg);
            font-size: 84px;
            font-weight: 800;
            color: rgba(120,120,120,0.18);
            z-index: 999;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>

    @if (!empty($isReprint) && $isReprint === true)
        <div class="watermark">COPY</div>
    @endif

    <div class="header">
        <div class="company-name">{{ $setting->company_name ?? 'Sentra Autoglass' }}</div>
        <p class="company-meta small">
            {{ $setting->company_address ?? '-' }}
            @if(!empty($setting->company_phone))
                | Telp: {{ $setting->company_phone }}
            @endif
        </p>
        <hr class="divider">
        <div class="title">SURAT JALAN</div>
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
                <strong>{{ $transfer->toBranch->name ?? '-' }}</strong>
            </td>
        </tr>
    </table>

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
                @endphp
                <tr>
                    <td class="col-no">{{ $index + 1 }}</td>
                    <td class="col-name">{{ $displayName }}</td>
                    <td class="col-qty">{{ (int) $item->quantity }}</td>
                    <td class="col-unit">{{ $unit }}</td>
                    <td class="col-note"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- IMPORTANT: Jangan taruh delivery code di tabel items (bikin garis berantakan).
         Kita buat box sendiri biar rapi dan tidak ada garis "kosong". -->
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

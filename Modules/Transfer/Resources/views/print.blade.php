<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $transfer->reference }}</title>
    <style>
        body {
            font-family: "Arial", sans-serif;
            font-size: 13px;
            margin: 0;
            padding: 10px 20px;
            color: #000;
        }

        .header, .footer {
            width: 100%;
            text-align: center;
        }

        .header h2 {
            margin: 0;
        }

        .info-table, .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .info-table td {
            padding: 4px 8px;
        }

        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 10px 6px;
            text-align: left;
        }

        .items-table th {
            background-color: #f2f2f2;
        }

        .signature {
            margin-top: 60px;
            width: 100%;
            text-align: center;
        }

        .signature td {
            padding-top: 70px;
            height: 100px;
            vertical-align: top;
        }

        .small {
            font-size: 11px;
        }
    </style>
</head>
<body>
    @if ($transfer->printed_at && $transfer->status !== 'pending')
        <div style="position: absolute; top: 40%; left: 25%; transform: rotate(-30deg); font-size: 60px; color: rgba(200,200,200,0.3); z-index: 999;">
            COPY
        </div>
    @endif

    <div class="header">
        <h2>{{ $setting->company_name ?? 'Nama Perusahaan' }}</h2>
        <p class="small">
            {{ $setting->company_address ?? '-' }}
            @if(!empty($setting->company_phone)) | Telp: {{ $setting->company_phone }} @endif
        </p>
        <hr>
        <h3 style="margin-top: 10px;">SURAT JALAN</h3>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>No. Referensi:</strong> {{ $transfer->reference }}</td>
            <td><strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($transfer->date)->format('d M Y') }}</td>
        </tr>
        <tr>
            <td><strong>Dari Gudang:</strong> {{ $transfer->fromWarehouse->warehouse_name ?? '-' }}</td>
            <td><strong>Ke Cabang:</strong> {{ $transfer->toBranch->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="items-table" style="margin-top: 25px;">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 50%;">Nama Produk</th>
                <th style="width: 15%;">Jumlah</th>
                <th style="width: 15%;">Satuan</th>
                <th style="width: 15%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transfer->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="padding-top: 14px; padding-bottom: 14px;">{{ $item->product->name ?? '-' }}</td>
                    <td>{{ number_format($item->quantity) }}</td>
                    <td>{{ $item->unit ?? '-' }}</td>
                    <td></td>
                </tr>
            @endforeach
            <tr>
                <td><strong>No. Referensi:</strong> {{ $transfer->reference }}</td>
                <td><strong>Delivery Code:</strong> {{ $transfer->delivery_code ?? '-' }}</td>
            </tr>

        </tbody>
    </table>

    <table class="signature">
        <tr>
            <td><strong>Pengirim</strong></td>
            <td><strong>Supir</strong></td>
            <td><strong>Penerima</strong></td>
        </tr>
        <tr>
            <td>________________________</td>
            <td>________________________</td>
            <td>________________________</td>
        </tr>
    </table>
</body>
</html>

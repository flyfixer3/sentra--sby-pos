<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    @page {
      size: A5 landscape;
      margin: 2mm 4mm;
    }

    body {
      font-family: sans-serif;
      font-size: 10px;
      margin: 0;
      color: #222;
    }

    .header {
      background-color: #c62828;
      color: white;
      padding: 12px;
    }

    .header img {
      height: 40px;
    }

    .info-bar {
      margin-top: 10px;
      display: flex;
      justify-content: space-between;
      font-size: 10px;
    }

    .invoice-title {
      font-size: 13px;
      font-weight: bold;
      text-align: center;
      margin: 15px 0;
      text-transform: uppercase;
    }

    table.detail-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 5px;
    }

    table.detail-table th {
      background-color: #ef5350;
      color: white;
      padding: 6px;
      font-size: 10px;
      border: 1px solid #c62828;
    }

    table.detail-table td {
      border: 1px solid #ddd;
      padding: 5px;
      font-size: 10px;
    }

    .totals-box {
      margin-top: 12px;
      width: 40%;
      float: right;
      font-size: 10px;
    }

    .totals-box table {
      width: 100%;
      border-collapse: collapse;
    }

    .totals-box td {
      padding: 6px;
      border: 1px solid #333;
      font-weight: bold;
    }

    .terms {
      margin-top: 10px;
      font-size: 9.5px;
      line-height: 1.5;
      display: flex;
      justify-content: space-between;
      gap: 20px;
    }

    .terms .col {
      width: 50%;
    }

    .signature {
      margin-top: 30px;
      font-size: 10px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }

    .signature-line {
      border-top: 1px solid #000;
      width: 200px;
      margin-top: 30px;
      margin-bottom: 5px;
    }

    .footer-note {
      text-align: center;
      font-size: 10px;
      margin-top: 10px;
      font-style: italic;
      color: #333;
    }
  </style>
</head>
<body>
  <div class="header">
    <table width="100%">
      <tr>
        <td><img src="{{ public_path('images/logo.png') }}" alt="Logo"></td>
        <td style="text-align: right; font-size: 10px;">
          <strong>Invoice</strong><br>
          No: {{ $sale->note }}<br>
          Tanggal: {{ \Carbon\Carbon::parse($sale->date)->format('d M Y') }}
        </td>
      </tr>
    </table>
  </div>

  <div class="info-bar">
    <div>
      <strong>Kepada Yth:</strong><br>
      {{ $customer->customer_name }}<br>
      {{ $customer->customer_phone }}<br>
      {{ $customer->address }}
    </div>
    <div style="text-align: right;">
      <strong>Metode Pembayaran:</strong><br>
      Transfer ke BCA<br>
      a.n. Alvin Wijaya<br>
      No Rek: 6041794388
    </div>
  </div>

  <div class="invoice-title">Nota Penjualan</div>

  <table class="detail-table">
    <thead>
      <tr>
        <th>No</th>
        <th>Kode</th>
        <th>Nama Barang</th>
        <th>Qty</th>
        <th>Harga Satuan</th>
        <th>Jumlah</th>
      </tr>
    </thead>
    <tbody>
      @foreach($sale->saleDetails as $item)
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $item->product_code }}</td>
        <td>{{ $item->product_name }}</td>
        <td>{{ $item->quantity }} PC</td>
        <td class="text-right">{{ format_currency($item->unit_price) }}</td>
        <td class="text-right">{{ format_currency($item->sub_total) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="totals-box">
    <table>
      <tr>
        <td>Subtotal</td>
        <td class="text-right">{{ format_currency($sale->total_amount + $sale->discount_amount) }}</td>
      </tr>
      <tr>
        <td>Diskon</td>
        <td class="text-right">{{ format_currency($sale->discount_amount) }}</td>
      </tr>
      <tr>
        <td><strong>Total</strong></td>
        <td class="text-right"><strong>{{ format_currency($sale->total_amount) }}</strong></td>
      </tr>
    </table>
  </div>

  <div class="terms">
    <div class="col">
      <strong>Syarat & Ketentuan:</strong>
      <ul>
        <li>Garansi pemasangan 7 hari sejak tanggal invoice.</li>
        <li>Garansi hanya berlaku apabila tidak ada kerusakan akibat benturan atau kecelakaan.</li>
        <li>Komplain setelah masa garansi tidak akan diterima.</li>
      </ul>
    </div>
    <div class="col">
      <strong>Cabang:</strong><br>
      Tangerang | Bekasi | Surabaya<br>
      Hotline: 0812-8800-9878<br>
      Email: sentraautoglass.sby@gmail.com<br><br>
      <strong>Catatan:</strong><br>
      Mohon simpan invoice ini sebagai bukti resmi pemasangan kaca mobil.
    </div>
  </div>

  <div class="signature">
    <div>
      <div class="signature-line"></div>
      Tanda Tangan Penerima<br>
      Tanggal: ______________________
    </div>
  </div>

  <div class="footer-note">
    Terima kasih telah mempercayakan pemasangan kaca mobil kepada kami.
  </div>
</body>
</html>

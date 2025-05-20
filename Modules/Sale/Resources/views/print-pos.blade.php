<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    @page {
      size: A5 landscape;
      margin: 2mm 4mm 2mm 4mm;
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
      height: 36px;
    }

    .invoice-title {
      font-size: 13px;
      font-weight: bold;
      text-align: center;
      margin: 10px 0;
      text-transform: uppercase;
    }

    table.detail-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 5px;
      min-height: 24%;
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
  <!-- Header -->
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

  <!-- Customer & Payment Info -->
  <table width="100%" style="margin-top: 10px;">
    <tr>
      <td style="width: 50%;">
        <strong>Kepada Yth:</strong><br>
        {{ $customer->customer_name }}<br>
        {{ $customer->customer_phone }}
      </td>
      <td style="width: 50%; text-align: right;">
        <strong>Metode Pembayaran:</strong><br>
        Transfer ke BCA<br>
        a.n. Alvin Wijaya<br>
        No Rek: 6041794388
      </td>
    </tr>
  </table>

  <!-- Title -->
  <div class="invoice-title">Nota Penjualan</div>

  <!-- Table Barang -->
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

  <!-- Terms + Signature -->
  <table width="100%" style="margin-top: 15px;">
    <tr valign="top">
      <!-- Terms -->
      <td style="width: 50%; font-size: 10px;">
        <strong>Syarat & Ketentuan:</strong>
        <ul style="margin: 5px 0 10px 15px; padding: 0;">
          <li>Garansi pemasangan 7 hari sejak tanggal invoice.</li>
          <li>Garansi hanya berlaku apabila tidak ada kerusakan akibat benturan atau kecelakaan.</li>
          <li>Komplain setelah masa garansi tidak akan diterima.</li>
        </ul>

        <strong>Catatan:</strong><br>
        Mohon simpan invoice ini sebagai bukti resmi pemasangan kaca mobil.<br><br>

        <strong>Cabang:</strong><br>
        Tangerang | Bekasi | Surabaya<br>
        Hotline: 0812-8800-9878<br>
        Email: sentraautoglass.sby@gmail.com
      </td>

      <!-- Signature & Total -->
      <td style="width: 50%; text-align: right;">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
          <tr>
            <td style="padding: 6px; border: 1px solid #000;">Subtotal</td>
            <td style="padding: 6px; border: 1px solid #000; text-align: right;">
              {{ format_currency($sale->total_amount + $sale->discount_amount) }}
            </td>
          </tr>
          <tr>
            <td style="padding: 6px; border: 1px solid #000;">Diskon</td>
            <td style="padding: 6px; border: 1px solid #000; text-align: right;">
              {{ format_currency($sale->discount_amount) }}
            </td>
          </tr>
          <tr>
            <td style="padding: 6px; border: 1px solid #000;"><strong>Total</strong></td>
            <td style="padding: 6px; border: 1px solid #000; text-align: right;">
              <strong>{{ format_currency($sale->total_amount) }}</strong>
            </td>
          </tr>
        </table>

        <div style="text-align: center; margin-top: 30px;">
            <div style="border-bottom: 1px solid #000; width: 60%; margin: 0 auto;padding-bottom: 80px">    
                Tanda Tangan Penerima<br>
            </div>
        </div>
      </td>
    </tr>
  </table>

  <!-- Footer -->
  <div class="footer-note">
    Terima kasih telah mempercayakan pemasangan kaca mobil kepada kami.
  </div>
</body>
</html>

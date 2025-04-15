<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Sale Details</title>
    <link rel="stylesheet" href="{{ public_path('b3/bootstrap.min.css') }}">
    <style>
          table {
    width: 100%;
    border-collapse: collapse;
  }



  thead, tr {
    display: table;
    width: 100%;
    table-layout: fixed; /* Ensure consistent column widths */
  }

  tbody tr {
    display: table;
    width: 100%;
    table-layout: fixed;
  }
  .body-1{
    width: 60px;
    text-align: center;
  }
  .body-2{
    width: 200px;
    text-align: start;
  }
  .body-3{
    width: 400px;
    text-align: start;
  }
  .body-4{
    width: 40px;
    text-align: center;
  }
  .body-5{
    width: 110px;
    text-align: end;
  }
  .body-6{
    width: 110px;
    text-align: end;
  }
  .header-1{
    width: 60px;
    text-align: center;
  }
  .header-2{
    width: 200px;
    text-align: center;
  }
  .header-3{
    width: 400px;
    text-align: center;
  }
  .header-4{
    width: 40px;
    text-align: center;
  }
  .header-5{
    width: 110px;
    text-align: center;
  }
  .header-6{
    width: 110px;
    text-align: center;
  }
  html, body {
        margin: 0;
        padding: 0;
        height: 100%;
    }
    .page {
        width: 100%;
        height: 1300px; /* Ensure full height */
        display: flex;
        flex-direction: column;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        position: relative;
        z-index: 1;
        overflow: hidden;
    }

    .half {
        width: 100%;
        flex: 1; /* Ensures equal vertical distribution */
        /* border: 1px solid black; Optional for layout visualization */
        box-sizing: border-box;
        overflow: hidden;
    }

    </style>
</head>
<body>
<!-- <div class="watermark">
    <img src="{{ public_path('images/logo-dark.png') }}" 
         alt="Watermark" 
         style="position: fixed; 
                top: 50%; 
                left: 50%; 
                transform: translate(-50%, -50%);
                max-width: 30%; /* Sesuaikan ukuran */
                height: auto; 
                z-index: -1;">
</div> -->
<div class="page">
    
    <div class="half" style="width: 100%;background-color: lightblue; height: 50%; box-sizing: border-box;">
        <div class="col-xs-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-4" style="margin: 0px 0px 0px -32px;padding: 0px;">
                        <div class="col-xs-8 mb-3 mb-md-0 ml-0 pl-0">
                            <!-- <div class="col-xs-12 mb-2 ml-0 pl-0" >
                                <img width="180" src="{{ public_path('images/logo-dark.png') }}" alt="Logo">
                            </div> -->
                            <!-- <h4 class="mb-2" style="border-bottom: 1px solid #dddddd;padding-bottom: 10px;">Company Info:</h4> -->
                            <div class="col-xs-12 mb-2 ml-0 pl-0" >
                                <!-- <img width="180" src="{{ public_path('images/logo-dark.png') }}" alt="Logo"> -->
                                <h4 style="margin: 0; padding: 0;padding-bottom: 1px;text-transform: uppercase;letter-spacing: 1px;border-bottom: 1px solid black"><strong>{{ settings()->company_name }}</strong></h4>  
                                <div>{{ settings()->company_address }}</div>
                                <div> {{ settings()->company_phone }} | {{ settings()->company_email }}</div>
                            </div>
                        </div>

                        <!-- <div class="col-xs-4 mb-3 mb-md-0">
                            <h4 class="mb-2" style="border-bottom: 1px solid #dddddd;padding-bottom: 10px;">Customer Info:</h4>
                            <div><strong>{{ $customer->customer_name }}</strong></div>
                            <div>{{ $customer->address }}</div>
                            <div>Email: {{ $customer->customer_email }}</div>
                            <div>Phone: {{ $customer->customer_phone }}</div>
                        </div> -->

                        <div class="col-xs-4 mb-3 mb-md-0" style="padding-left: 120px;">
                            <div>        
                                <!-- <h4 class="mb-2" style="border-bottom: 1px solid #dddddd;padding-bottom: 10px;">Invoice Info:</h4> -->
                                <div>Tanggal : {{ \Carbon\Carbon::parse($sale->date)->format('d M Y') }}</div>
                                <div>
                                    Kepada Yth. 
                                </div>
                                <div>
                                {{ $customer->customer_name }}
                                </div>
                                <div>
                                {{ $customer->customer_phone }}
                                </div>
                                <div>
                                {{ $customer->address }}
                                </div>
                            </div>
                        </div>

                    </div>
                 
                    <div class="col-xs-12" style="text-align: center; margin: 20px 0px">
                        <h4 style="text-transform: uppercase;">
                            <strong>
                                Nota Penjualan
                            </strong>
                        </h4>
                    </div>
                    <div class="table-responsive-sm" style="margin-top: 30px;">
                        <!-- <span>INV :</span> <strong>{{ $sale->reference }}</strong> -->
                        <table class="">
                            <div> 
                                <strong>&ensp;INV &emsp;&ensp;{{ $sale->note }}</strong>
                            </div>
                            <thead style="border-top: 1px solid black;border-bottom: 1px solid black; font-weight: normal;margin-bottom: 2px;">
                            <tr>
                                <th class="align-middle header-1">No</th>
                                <th class="align-middle header-2">Kode</th>
                                <th class="align-middle header-3">Nama Barang</th>
                                <th class="align-middle header-4">Qty</th>
                                <th class="align-middle header-5">Harga Satuan</th>
                                <!-- <th class="align-middle">Diskon</th> -->
                                <!-- <th class="align-middle">Tax</th> -->
                                <th class="align-middle header-6">Jumlah</th>
                            </tr>
                            </thead>
                            <tbody style="margin: 0;min-height: 180px;display: block;">
                            @foreach($sale->saleDetails as $item)
                                <tr>
                                    <td class="align-middle body-1">
                                        {{ $loop->index +1 }}
                                    </td>
                                    <td class="align-middle body-2">
                                    {{ $item->product_code }}
                                    </td>
                                    <td class="align-middle body-3">
                                    {{ $item->product_name }}
                                    </td>
                                    
                                    <td class="align-middle body-4">
                                        {{ $item->quantity }} PC
                                    </td>
                                    <td class="align-middle body-5">
                                        {{ format_currency($item->unit_price) }}
                                    </td>

                                    <!-- <td class="align-middle">
                                        {{ format_currency($item->product_discount_amount) }}
                                    </td> -->

                                    <!-- <td class="align-middle">
                                        {{ format_currency($item->product_tax_amount) }}
                                    </td> -->

                                    <td class="align-middle body-6">
                                        {{ format_currency($item->sub_total) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="container" style="width: 100%;margin-top: 20px;padding: 20px 0 0 50px;box-sizing: border-box;border-bottom: 1px solid black;">
                        <div><strong> BCA 6041794388 an Alvin WIjaya </strong></div>
                    </div>
                    <div class="container" style="display:table;width: 100%;margin-top: 0px;padding: 0;box-sizing: border-box;">
                        <div class="row" style=" height: 100%;display: table-row;">
                            <div class="col-xs-5" style="height: 100%;display: flex; padding: 60px 0px 0px 0px">
                                <img width="150" src="{{ public_path('images/logo-dark.png') }}" alt="Logo">
                                <div>Trust the Experts, Drive Safer and Clearer</div>
                            </div>
                            <div class="col-xs-3" style="height: 100%;display: flex; padding: 30px 30px 0px 0px; text-align: center;justify-content: center;">
                                <div>Penerima</div>
                                <div style="margin-top: 66px;">......................</div>
                            </div>
                            <div class="col-xs-3" style="height: 100%;display: flex; padding: 30px 30px 0px 30px; text-align: center;justify-content: center;">
                                <div>Kaca yang sudah dipesan/dipasang tidak dapat dibatalkan/dikembalikan.</div>
                            </div>
                            <div class="col-xs-2" style="display: table-cell;float: none;padding: 0;">
                                <table>
                                    <tbody>
                                    <tr>
                                        <td class="left" style="width: 45%;">Total</td>
                                        <td class="right" style="border-left: 1px solid black;padding: 2px;text-align: end;width: 100%;">{{ format_currency($sale->total_amount + $sale->discount_amount ) }}</td>
                                    </tr>
                                    <!-- <tr>
                                        <td class="left">Biaya kirim</td>
                                        <td class="right">{{ format_currency($sale->shipping_amount) }}</td>
                                    </tr> -->
                                    <tr>
                                        <td class="left" style="width: 45%;">Diskon</td>
                                        <td class="right" style="border-left: 1px solid black;padding: 2px;text-align: end;width: 100%;">{{ format_currency($sale->discount_amount) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="left" style="width: 45%;"><strong>Netto</strong></td>
                                        <td class="right" style="border: 1px solid black; padding: 2px; text-align: end;width: 100%;"><strong>{{ format_currency($sale->total_amount) }}</strong></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="half" style="width: 100%; height: 50%; box-sizing: border-box;padding-top: 40px;">
        <div class="col-xs-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-4" style="margin: 0px 0px 0px -32px;padding: 0px;">
                        <div class="col-xs-8 mb-3 mb-md-0 ml-0 pl-0">
                            <div class="col-xs-12 mb-2 ml-0 pl-0" >
                                <h4 style="margin: 0; padding: 0;padding-bottom: 1px;text-transform: uppercase;letter-spacing: 1px;border-bottom: 1px solid black"><strong>{{ settings()->company_name }}</strong></h4>  
                                <div>{{ settings()->company_address }}</div>
                                <div> {{ settings()->company_phone }} | {{ settings()->company_email }}</div>
                            </div>
                        </div>

                        <div class="col-xs-4 mb-3 mb-md-0" style="padding-left: 120px;">
                            <div>        
                                <div>Tanggal : {{ \Carbon\Carbon::parse($sale->date)->format('d M Y') }}</div>
                                <div>
                                    Kepada Yth. 
                                </div>
                                <div>
                                {{ $customer->customer_name }}
                                </div>
                                <div>
                                {{ $customer->customer_phone }}
                                </div>
                                <div>
                                {{ $customer->address }}
                                </div>
                            </div>
                        </div>

                    </div>
                 
                    <div class="col-xs-12" style="text-align: center; margin: 20px 0px">
                        <h4 style="text-transform: uppercase;">
                            <strong>
                                Nota Penjualan
                            </strong>
                        </h4>
                    </div>
                    <div class="table-responsive-sm" style="margin-top: 30px;">
                        <table class="">
                            <div> 
                                <strong>&ensp;INV &emsp;&ensp;{{ $sale->note }}</strong>
                            </div>
                            <thead style="border-top: 1px solid black;border-bottom: 1px solid black; font-weight: normal;margin-bottom: 2px;">
                            <tr>
                                <th class="align-middle header-1">No</th>
                                <th class="align-middle header-2">Kode</th>
                                <th class="align-middle header-3">Nama Barang</th>
                                <th class="align-middle header-4">Qty</th>
                                <th class="align-middle header-5">Harga Satuan</th>
                                <th class="align-middle header-6">Jumlah</th>
                            </tr>
                            </thead>
                            <tbody style="margin: 0;min-height: 180px;display: block;">
                            @foreach($sale->saleDetails as $item)
                                <tr>
                                    <td class="align-middle body-1">
                                        {{ $loop->index +1 }}
                                    </td>
                                    <td class="align-middle body-2">
                                    {{ $item->product_code }}
                                    </td>
                                    <td class="align-middle body-3">
                                    {{ $item->product_name }}
                                    </td>
                                    
                                    <td class="align-middle body-4">
                                        {{ $item->quantity }} PC
                                    </td>
                                    <td class="align-middle body-5">
                                        {{ format_currency($item->unit_price) }}
                                    </td>

                                    <td class="align-middle body-6">
                                        {{ format_currency($item->sub_total) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="container" style="width: 100%;margin-top: 20px;padding: 20px 0 0 50px;box-sizing: border-box;border-bottom: 1px solid black;">
                        <div><strong> BCA 604179388 an Alvin WIjaya </strong></div>
                    </div>
                    <div class="container" style="display:table;width: 100%;margin-top: 0px;padding: 0;box-sizing: border-box;">
                        <div class="row" style=" height: 100%;display: table-row;">
                            <div class="col-xs-5" style="height: 100%;display: flex; padding: 60px 0px 0px 0px">
                                <img width="150" src="{{ public_path('images/logo-dark.png') }}" alt="Logo">
                                <div>Trust the Experts, Drive Safer and Clearer</div>
                            </div>
                            <div class="col-xs-3" style="height: 100%;display: flex; padding: 30px 30px 0px 0px; text-align: center;justify-content: center;">
                                <div>Penerima</div>
                                <div style="margin-top: 66px;">......................</div>
                            </div>
                            <div class="col-xs-3" style="height: 100%;display: flex; padding: 30px 30px 0px 30px; text-align: center;justify-content: center;">
                                <div>Kaca yang sudah dipesan/dipasang tidak dapat dibatalkan/dikembalikan.</div>
                            </div>
                            <div class="col-xs-2" style="display: table-cell;float: none;padding: 0;">
                                <table>
                                    <tbody>
                                    <tr>
                                        <td class="left" style="width: 40%;">Total</td>
                                        <td class="right" style="border-left: 1px solid black;padding: 2px;text-align: end;width: 100%;">{{ format_currency($sale->total_amount + $sale->discount_amount ) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="left" style="width: 40%;">Diskon</td>
                                        <td class="right" style="border-left: 1px solid black;padding: 2px;text-align: end;width: 100%;">{{ format_currency($sale->discount_amount) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="left" style="width: 40%;"><strong>Netto</strong></td>
                                        <td class="right" style="border: 1px solid black; padding: 2px; text-align: end;width: 100%;"><strong>{{ format_currency($sale->total_amount) }}</strong></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
</div>
</body>
</html>

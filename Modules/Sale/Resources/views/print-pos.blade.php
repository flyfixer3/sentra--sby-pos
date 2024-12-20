<!-- <!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title></title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * {
            font-size: 12px;
            line-height: 18px;
            font-family: 'Ubuntu', sans-serif;
        }
        h2 {
            font-size: 16px;
        }
        td,
        th,
        tr,
        table {
            border-collapse: collapse;
        }
        tr {border-bottom: 1px dashed #ddd;}
        td,th {padding: 7px 0;width: 50%;}

        table {width: 100%;}
        tfoot tr th:first-child {text-align: left;}

        .centered {
            text-align: center;
            align-content: center;
        }
        small{font-size:11px;}

        @media print {
            * {
                font-size:12px;
                line-height: 20px;
            }
            td,th {padding: 5px 0;}
            .hidden-print {
                display: none !important;
            }
            tbody::after {
                content: '';
                display: block;
                page-break-after: always;
                page-break-inside: auto;
                page-break-before: avoid;
            }
        }
    </style>
</head>
<body>

<div style="max-width:400px;margin:0 auto">
    <div id="receipt-data">
        <div class="centered">
            <h2 style="margin-bottom: 5px">{{ settings()->company_name }}</h2>

            <p style="font-size: 11px;line-height: 15px;margin-top: 0">
                {{ settings()->company_email }}, {{ settings()->company_phone }}
                <br>{{ settings()->company_address }}
            </p>
        </div>
        <p>
            Date: {{ \Carbon\Carbon::parse($sale->date)->format('d M, Y') }}<br>
            Reference: {{ $sale->reference }}<br>
            Name: {{ $sale->customer_name }}
        </p>
        <table class="table-data">
            <tbody>
            @foreach($sale->saleDetails as $saleDetail)
                <tr>
                    <td colspan="2">
                        {{ $saleDetail->product->product_name }}
                        ({{ $saleDetail->quantity }} x {{ format_currency($saleDetail->price) }})
                    </td>
                    <td style="text-align:right;vertical-align:bottom">{{ format_currency($saleDetail->sub_total) }}</td>
                </tr>
            @endforeach

            @if($sale->tax_percentage)
                <tr>
                    <th colspan="2" style="text-align:left">Tax ({{ $sale->tax_percentage }}%)</th>
                    <th style="text-align:right">{{ format_currency($sale->tax_amount) }}</th>
                </tr>
            @endif
            @if($sale->discount_percentage)
                <tr>
                    <th colspan="2" style="text-align:left">Discount ({{ $sale->discount_percentage }}%)</th>
                    <th style="text-align:right">{{ format_currency($sale->discount_amount) }}</th>
                </tr>
            @endif
            @if($sale->shipping_amount)
                <tr>
                    <th colspan="2" style="text-align:left">Shipping</th>
                    <th style="text-align:right">{{ format_currency($sale->shipping_amount) }}</th>
                </tr>
            @endif
            <tr>
                <th colspan="2" style="text-align:left">Grand Total</th>
                <th style="text-align:right">{{ format_currency($sale->total_amount) }}</th>
            </tr>
            </tbody>
        </table>
        <table>
            <tbody>
                <tr style="background-color:#ddd;">
                    <td class="centered" style="padding: 5px;">
                        Paid By: {{ $sale->payment_method }}
                    </td>
                    <td class="centered" style="padding: 5px;">
                        Amount: {{ format_currency($sale->paid_amount) }}
                    </td>
                </tr>
                <tr style="border-bottom: 0;">
                    <td class="centered" colspan="3">
                        <div style="margin-top: 10px;">
                            {!! \Milon\Barcode\Facades\DNS1DFacade::getBarcodeSVG($sale->reference, 'C128', 1, 25, 'black', false) !!}
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

</body>
</html> -->
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Sale Details</title>
    <link rel="stylesheet" href="{{ public_path('b3/bootstrap.min.css') }}">
</head>
<body>
<div class="container-fluid">
    <div class="row">
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
                                <h4 style="margin: 0; padding: 0;text-transform: uppercase;letter-spacing: 8px;"><strong>{{ settings()->company_name }}</strong></h4>  
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

                        <div class="col-xs-4 mb-3 mb-md-0">
                            <!-- <h4 class="mb-2" style="border-bottom: 1px solid #dddddd;padding-bottom: 10px;">Invoice Info:</h4> -->
                            <div>Invoice: <strong>{{ $sale->note }}</strong></div>
                            <div>Tanggal : {{ \Carbon\Carbon::parse($sale->date)->format('d M Y') }}</div>
                            <div>
                                Kepada Yth. 
                            </div>
                            <div>
                            {{ $customer->customer_name }}
                            </div>
                            <div>
                            ( {{ $customer->customer_phone }} )
                            </div>
                            <div>
                            {{ $customer->address }}
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
                        <table class="table table-striped">
                            <thead>
                            <tr>
                                <th class="align-middle">No</th>
                                <th class="align-middle">Kode</th>
                                <th class="align-middle">Nama Barang</th>
                                <th class="align-middle">Qty</th>
                                <th class="align-middle">Harga Satuan</th>
                                <!-- <th class="align-middle">Diskon</th> -->
                                <!-- <th class="align-middle">Tax</th> -->
                                <th class="align-middle">Jumlah</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($sale->saleDetails as $item)
                                <tr>
                                    <td class="align-middle">
                                        {{ $loop->index +1 }}
                                    </td>
                                    <td class="align-middle">
                                    {{ $item->product_code }}
                                    </td>
                                    <td class="align-middle">
                                    {{ $item->product_name }}
                                    </td>
                                    
                                    <td class="align-middle">
                                        {{ $item->quantity }}
                                    </td>
                                    <td class="align-middle">
                                        {{ format_currency($item->unit_price) }}
                                    </td>

                                    <!-- <td class="align-middle">
                                        {{ format_currency($item->product_discount_amount) }}
                                    </td> -->

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
                    <div class="container" style="width: 100%;margin-top: 20px;padding: 20px 0 0 50px;box-sizing: border-box;border-bottom: 1px solid #a6a6a6;">
                        <div><strong> BCA 7610244321 an Cecilia Michelle </strong></div>
                    </div>
                    <div class="container" style="display:table;width: 100%;margin-top: 0px;padding: 0;box-sizing: border-box;">
                        <div class="row" style=" height: 100%;display: table-row;">
                            <div class="col-xs-8" style="height: 100%;display: flex; padding: 60px 0px 0px 0px">
                                <img width="150" src="{{ public_path('images/logo-dark.png') }}" alt="Logo">
                                <div>Trust the Experts, Drive Safer and Clearer</div>
                            </div>
                            <div class="col-xs-4" style="height: 100%;display: flex; padding: 30px 30px 0px 0px; text-align: center;justify-content: center;">
                                <div>Kaca yang sudah dipesan/dipasang tidak dapat dibatalkan/dikembalikan.</div>
                            </div>
                            <div class="col-xs-3" style="display: table-cell;float: none;">
                                <table class="table">
                                    <tbody>
                                    <tr>
                                        <td class="left">Total</td>
                                        <td class="right">{{ format_currency($sale->total_amount + $sale->discount_amount ) }}</td>
                                    </tr>
                                    <!-- <tr>
                                        <td class="left">Biaya kirim</td>
                                        <td class="right">{{ format_currency($sale->shipping_amount) }}</td>
                                    </tr> -->
                                    <tr>
                                        <td class="left">Diskon</td>
                                        <td class="right">{{ format_currency($sale->discount_amount) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="left"><strong>Netto</strong></td>
                                        <td class="right"><strong>{{ format_currency($sale->total_amount) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="left"><strong>Ket</strong></td>
                                        <td class="right"><strong></strong></td>
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

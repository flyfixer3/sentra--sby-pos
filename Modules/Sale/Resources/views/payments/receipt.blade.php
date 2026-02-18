<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { font-size: 12px; color: #111; }
        .wrap { padding: 14px; }
        .row { width: 100%; clear: both; }
        .col { float: left; }
        .col-6 { width: 50%; }
        .col-4 { width: 33.3333%; }
        .col-8 { width: 66.6666%; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #666; }
        .h1 { font-size: 16px; font-weight: 700; margin: 0; }
        .h2 { font-size: 13px; font-weight: 700; margin: 0; }
        .mb-6 { margin-bottom: 6px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-14 { margin-bottom: 14px; }
        .border { border: 1px solid #e5e5e5; }
        .box { border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px; }
        .divider { border-top: 1px solid #e5e5e5; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 6px; vertical-align: top; }
        .table th { background: #f6f6f6; border-bottom: 1px solid #e5e5e5; font-weight: 700; }
        .table td { border-bottom: 1px solid #f0f0f0; }
        .no-border td { border-bottom: 0; }
        .clearfix::after { content: ""; display: block; clear: both; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #f2f2f2; font-size: 11px; }
    </style>
</head>
<body>
<div class="wrap">

    <div class="text-center mb-10">
        <img src="{{ public_path('images/logo-dark.png') }}" alt="Logo" style="height: 28px; margin-bottom: 6px;">
        <div class="h1">PAYMENT RECEIPT</div>
        <div class="muted">{{ settings()->company_name }}</div>
    </div>

    <div class="box mb-14 clearfix">
        <div class="row clearfix">
            <div class="col col-6">
                <div class="h2 mb-6">Customer</div>
                <div><strong>{{ $customer->customer_name ?? '-' }}</strong></div>
                <div class="muted">{{ $customer->address ?? '-' }}</div>
                <div class="muted">{{ $customer->customer_phone ?? '' }}</div>
            </div>
            <div class="col col-6 text-right">
                <div class="h2 mb-6">Receipt Info</div>
                <div>Receipt Ref: <strong>{{ $salePayment->reference ?? ('PAY-'.$salePayment->id) }}</strong></div>
                <div>Date: <strong>{{ $salePayment->date ?? '-' }}</strong></div>
                <div>Method: <strong>{{ $salePayment->payment_method ?? '-' }}</strong></div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="row clearfix">
            <div class="col col-6">
                <div class="h2 mb-6">Invoice</div>
                <div>Invoice: <strong>INV/{{ $sale->reference ?? '-' }}</strong></div>
                <div class="muted">Invoice Date: {{ \Carbon\Carbon::parse($sale->date ?? now())->format('d M, Y') }}</div>
            </div>
            <div class="col col-6 text-right">
                <div class="h2 mb-6">Status</div>
                <div class="badge">{{ $sale->payment_status ?? '-' }}</div>
            </div>
        </div>
    </div>

    <table class="table mb-14">
        <thead>
        <tr>
            <th>Description</th>
            <th class="text-right">Amount</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                Payment Received
                @if(!empty($salePayment->note))
                    <div class="muted" style="margin-top: 3px;">Note: {{ $salePayment->note }}</div>
                @endif
            </td>
            <td class="text-right"><strong>{{ format_currency((int)($salePayment->amount ?? 0)) }}</strong></td>
        </tr>
        </tbody>
    </table>

    <div class="box clearfix">
        <table class="no-border">
            <tbody>
            <tr>
                <td class="muted">Grand Total</td>
                <td class="text-right">{{ format_currency((int)($sale->total_amount ?? 0)) }}</td>
            </tr>
            <tr>
                <td class="muted">Paid Before This Receipt</td>
                <td class="text-right">{{ format_currency((int)($paidBefore ?? 0)) }}</td>
            </tr>
            <tr>
                <td><strong>Total Paid After This Receipt</strong></td>
                <td class="text-right"><strong>{{ format_currency((int)($paidAfter ?? 0)) }}</strong></td>
            </tr>
            <tr>
                <td><strong>Remaining Due</strong></td>
                <td class="text-right"><strong>{{ format_currency((int)($remaining ?? 0)) }}</strong></td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="text-center muted" style="margin-top: 12px; font-size: 11px;">
        Thank you for your payment.
    </div>

</div>
</body>
</html>

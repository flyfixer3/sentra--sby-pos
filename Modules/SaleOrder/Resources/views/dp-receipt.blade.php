<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DP Receipt</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { font-size: 12px; color: #111; }
        .wrap { padding: 14px; }
        .row { width: 100%; clear: both; }
        .col { float: left; }
        .col-6 { width: 50%; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #666; }
        .h1 { font-size: 16px; font-weight: 700; margin: 0; }
        .h2 { font-size: 13px; font-weight: 700; margin: 0; }
        .mb-6 { margin-bottom: 6px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-14 { margin-bottom: 14px; }
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
        <div class="h1">DP RECEIPT</div>
        <div class="muted">{{ settings()->company_name }}</div>
    </div>

    @php
        $receiptRef  = $salePayment->reference ?? ('SO-' . ($salePayment->id ?? 0));
        $receiptDate = $salePayment->date ?? ($saleOrder->date ?? date('Y-m-d'));
        $method      = $salePayment->payment_method ?? ($saleOrder->deposit_payment_method ?? '-');

        $soRef  = $saleOrder->reference ?? ('SO-' . $saleOrder->id);
        $soDate = $saleOrder->date ? \Carbon\Carbon::parse($saleOrder->date)->format('d M, Y') : '-';

        $grandTotal = (int) ($saleOrder->total_amount ?? 0);
        $paidBefore = (int) ($paidBefore ?? 0);
        $paidAfter  = (int) ($paidAfter ?? 0);
        $remaining  = (int) ($remaining ?? 0);
    @endphp

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
                <div>Receipt Ref: <strong>{{ $receiptRef }}</strong></div>
                <div>Date: <strong>{{ $receiptDate }}</strong></div>
                <div>Method: <strong>{{ $method }}</strong></div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="row clearfix">
            <div class="col col-6">
                <div class="h2 mb-6">Sale Order</div>
                <div>SO: <strong>{{ $soRef }}</strong></div>
                <div class="muted">SO Date: {{ $soDate }}</div>
            </div>
            <div class="col col-6 text-right">
                <div class="h2 mb-6">Status</div>
                <div class="badge">{{ strtoupper($saleOrder->status ?? 'PENDING') }}</div>
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
                Deposit (DP) Received
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
                <td class="muted">Grand Total (Sale Order)</td>
                <td class="text-right">{{ format_currency($grandTotal) }}</td>
            </tr>
            <tr>
                <td class="muted">Paid Before This Receipt</td>
                <td class="text-right">{{ format_currency($paidBefore) }}</td>
            </tr>
            <tr>
                <td><strong>Total Paid After This Receipt</strong></td>
                <td class="text-right"><strong>{{ format_currency($paidAfter) }}</strong></td>
            </tr>
            <tr>
                <td><strong>Remaining Due</strong></td>
                <td class="text-right"><strong>{{ format_currency($remaining) }}</strong></td>
            </tr>
            </tbody>
        </table>

        <div class="muted" style="margin-top:10px;font-size:11px;line-height:1.45;">
            Catatan: DP ini akan ditampilkan sebagai pengurang tagihan saat Invoice dibuat dari Sale Delivery (allocated pro-rata).
        </div>
    </div>

    <div class="text-center muted" style="margin-top: 12px; font-size: 11px;">
        Thank you for your payment.
    </div>

</div>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - {{ $payment->reference }}</title>
    <style>
        *{ box-sizing:border-box; }
        body{
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 12px;
            color:#111;
            margin: 24px 28px;
        }
        .muted{ color:#666; }
        .text-right{ text-align:right; }
        .text-center{ text-align:center; }
        .fw-bold{ font-weight:700; }
        .card{
            border:1px solid #e6e6e6;
            border-radius:12px;
            padding:14px 16px;
            margin-top: 12px;
        }
        .title{
            font-size: 16px;
            letter-spacing:.8px;
            margin: 0;
        }
        .subtitle{ margin:4px 0 0 0; color:#666; }

        .info{
            width:100%;
            border-collapse:collapse;
            margin-top: 10px;
        }
        .info td{
            vertical-align:top;
            padding:0;
        }

        .badge{
            display:inline-block;
            padding:2px 8px;
            border-radius:999px;
            font-size:11px;
            border:1px solid #ddd;
            background:#fafafa;
        }

        .table{
            width:100%;
            border-collapse:collapse;
            margin-top: 10px;
        }
        .table th, .table td{
            border-bottom:1px solid #eee;
            padding:10px 8px;
        }
        .table th{
            background:#fafafa;
            border-top:1px solid #eee;
            text-align:left;
        }

        .footer{
            margin-top: 18px;
            text-align:center;
            color:#777;
            font-size:11px;
        }
    </style>
</head>
<body>

@php
    $companyName = settings()->company_name ?? 'Company';
    $branchName = $companyName; // kalau kamu punya setting per branch, bisa diganti di sini

    $invoiceNo = 'INV/' . ($sale->reference ?? $sale->id);
    $invoiceDate = !empty($sale->date) ? \Carbon\Carbon::parse($sale->date)->format('d M, Y') : '-';

    $receiptRef = $payment->reference ?? ('PAY-' . $payment->id);
    $receiptDate = $payment->date ?? '-';
    $method = $payment->payment_method ?? '-';

    $status = strtoupper((string)($sale->payment_status ?? 'UNPAID'));
@endphp

<div class="text-center">
    <div class="fw-bold title">PAYMENT RECEIPT</div>
    <div class="subtitle">{{ $branchName }}</div>
</div>

<div class="card">
    <table class="info">
        <tr>
            <td style="width:50%; padding-right:10px;">
                <div class="fw-bold">Customer</div>
                <div style="margin-top:6px;">
                    <div class="fw-bold">{{ $customer->customer_name ?? '-' }}</div>
                    <div class="muted">{{ $customer->address ?? '-' }}</div>
                    <div class="muted">{{ $customer->customer_phone ?? '-' }}</div>
                </div>
            </td>
            <td style="width:50%;">
                <div class="fw-bold">Receipt Info</div>
                <div style="margin-top:6px;">
                    <div>Receipt Ref: <span class="fw-bold">{{ $receiptRef }}</span></div>
                    <div>Date: <span class="fw-bold">{{ $receiptDate }}</span></div>
                    <div>Method: <span class="fw-bold">{{ $method }}</span></div>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top:12px;">
        <div class="fw-bold">Invoice</div>
        <div style="margin-top:6px;">
            <div>Invoice: <span class="fw-bold">{{ $invoiceNo }}</span></div>
            <div>Invoice Date: <span class="fw-bold">{{ $invoiceDate }}</span></div>
            <div>Status: <span class="badge">{{ $status }}</span></div>
        </div>
    </div>
</div>

<table class="table">
    <thead>
    <tr>
        <th>Description</th>
        <th class="text-right">Amount</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Payment Received</td>
        <td class="text-right fw-bold">{{ format_currency((int)$payment->amount) }}</td>
    </tr>
    </tbody>
</table>

<div class="card">
    <table class="table" style="margin-top:0;">
        <tbody>
        <tr>
            <td>Grand Total</td>
            <td class="text-right">{{ format_currency((int)$sale->total_amount) }}</td>
        </tr>
        <tr>
            <td>Paid Before This Receipt</td>
            <td class="text-right">{{ format_currency((int)$paidBefore) }}</td>
        </tr>
        <tr>
            <td class="fw-bold">Total Paid After This Receipt</td>
            <td class="text-right fw-bold">{{ format_currency((int)$paidAfter) }}</td>
        </tr>
        <tr>
            <td class="fw-bold">Remaining Due</td>
            <td class="text-right fw-bold">{{ format_currency((int)$remaining) }}</td>
        </tr>
        </tbody>
    </table>
</div>

<div class="footer">
    Thank you for your payment.
</div>

</body>
</html>

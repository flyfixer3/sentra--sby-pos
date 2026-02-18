<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $sale->reference }}</title>
    <style>
        *{ box-sizing:border-box; }
        body{
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 12px;
            color:#111;
            margin: 22px 26px;
        }

        .muted{ color:#666; }
        .text-right{ text-align:right; }
        .text-center{ text-align:center; }
        .fw-bold{ font-weight:700; }
        .fw-semibold{ font-weight:600; }

        .header{
            width:100%;
            margin-bottom: 14px;
        }
        .header td{ vertical-align:top; }
        .logo{
            width: 180px;
            height:auto;
        }
        .title{
            font-size: 18px;
            letter-spacing: .6px;
            margin: 2px 0 0 0;
        }
        .subtitle{
            font-size: 12px;
            margin: 4px 0 0 0;
            color:#666;
        }

        .card{
            width:100%;
            border:1px solid #e6e6e6;
            border-radius:10px;
            padding:12px 14px;
            margin-top: 10px;
        }

        .info-table{
            width:100%;
            border-collapse:collapse;
        }
        .info-table td{
            vertical-align:top;
            padding:0;
        }
        .info-col{
            width:33.33%;
            padding-right:12px;
        }
        .info-title{
            font-weight:700;
            margin:0 0 8px 0;
            padding-bottom:6px;
            border-bottom:1px solid #eee;
            font-size:12px;
        }
        .info-line{
            margin:0 0 4px 0;
            line-height: 1.35;
        }

        .badge{
            display:inline-block;
            padding:2px 8px;
            border-radius:999px;
            font-size:11px;
            border:1px solid #ddd;
        }
        .badge-paid{ background:#e7f7ee; border-color:#bfe9d0; color:#18794e; }
        .badge-partial{ background:#fff7e6; border-color:#ffe2a8; color:#8a5a00; }
        .badge-unpaid{ background:#fdecec; border-color:#f5b9b9; color:#a01616; }

        .items{
            width:100%;
            border-collapse:collapse;
            margin-top: 12px;
        }
        .items th, .items td{
            border-bottom:1px solid #eee;
            padding:10px 8px;
            vertical-align:top;
        }
        .items th{
            text-align:left;
            font-size:12px;
            background:#fafafa;
            border-top:1px solid #eee;
        }
        .items td{
            font-size:12px;
        }

        .code-pill{
            display:inline-block;
            padding:2px 8px;
            border-radius:8px;
            font-size:11px;
            background:#f4f6f8;
            border:1px solid #e3e7ea;
            color:#333;
            margin-top:4px;
        }

        .summary-wrap{
            width:100%;
            margin-top: 14px;
        }
        .summary-wrap td{ vertical-align:top; }

        .note-box{
            width:58%;
            padding-right:12px;
        }
        .summary-box{
            width:42%;
        }

        .summary{
            width:100%;
            border-collapse:collapse;
        }
        .summary td{
            padding:6px 4px;
            border-bottom:1px solid #f0f0f0;
        }
        .summary tr:last-child td{
            border-bottom:none;
        }

        .hr{
            border-top:1px solid #eee;
            margin:14px 0;
        }

        .payments{
            width:100%;
            border-collapse:collapse;
            margin-top: 10px;
        }
        .payments th, .payments td{
            border-bottom:1px solid #eee;
            padding:8px 6px;
        }
        .payments th{
            background:#fafafa;
            border-top:1px solid #eee;
            text-align:left;
        }

        .footer{
            margin-top: 16px;
            text-align:center;
            color:#777;
            font-size:11px;
        }
    </style>
</head>
<body>

@php
    // ========= Company Info (safe fallback) =========
    $companyName = settings()->company_name ?? 'Company';
    $companyAddress = settings()->company_address ?? '-';
    $companyEmail = settings()->company_email ?? '-';
    $companyPhone = settings()->company_phone ?? '-';

    // ========= Invoice numbers =========
    $invoiceNo = 'INV/' . ($sale->reference ?? $sale->id);
    $invoiceDate = !empty($sale->date) ? \Carbon\Carbon::parse($sale->date)->format('d M, Y') : '-';

    // ========= Payment status badge =========
    $ps = strtolower((string)($sale->payment_status ?? 'unpaid'));
    $psBadge = 'badge-unpaid';
    if ($ps === 'paid') $psBadge = 'badge-paid';
    elseif ($ps === 'partial') $psBadge = 'badge-partial';

    // ========= DP from Sale Order (already paid) =========
    $allocatedDp = 0;
    $ratioPercent = null;
    $saleOrderRef = null;

    if(!empty($saleOrderDepositInfo) && isset($saleOrderDepositInfo['allocated'])) {
        $allocatedDp = (int) $saleOrderDepositInfo['allocated'];
        $ratioPercent = $saleOrderDepositInfo['ratio_percent'] ?? null;
        $saleOrderRef = $saleOrderDepositInfo['sale_order_reference'] ?? null;
    }

    // ========= Totals =========
    $grandTotal = (int) ($sale->total_amount ?? 0);
    $paidAmount = (int) ($sale->paid_amount ?? 0);
    $dueAmount  = (int) ($sale->due_amount ?? 0);

    // Net invoice total after DP (DP is from SO, not recorded as invoice payment)
    $netTotalAfterDp = $grandTotal;
    if ($allocatedDp > 0) $netTotalAfterDp = max(0, $grandTotal - $allocatedDp);

    // remaining due AFTER DP, computed as: net - invoice paid
    $dueAfterDp = max(0, $netTotalAfterDp - $paidAmount);
@endphp

<table class="header">
    <tr>
        <td style="width:55%;">
            <img class="logo" src="{{ public_path('images/logo-dark.png') }}" alt="Logo">
            <div class="subtitle">{{ $companyName }}</div>
        </td>
        <td style="width:45%;" class="text-right">
            <div class="title fw-bold">INVOICE</div>
            <div class="subtitle">Reference: <span class="fw-semibold">{{ $sale->reference }}</span></div>
        </td>
    </tr>
</table>

<div class="card">
    <table class="info-table">
        <tr>
            <td class="info-col">
                <div class="info-title">Company Info</div>
                <div class="info-line fw-semibold">{{ $companyName }}</div>
                <div class="info-line">{{ $companyAddress }}</div>
                <div class="info-line">Email: {{ $companyEmail }}</div>
                <div class="info-line">Phone: {{ $companyPhone }}</div>
            </td>

            <td class="info-col">
                <div class="info-title">Customer Info</div>
                <div class="info-line fw-semibold">{{ $customer->customer_name ?? '-' }}</div>
                <div class="info-line">{{ $customer->address ?? '-' }}</div>
                <div class="info-line">Email: {{ $customer->customer_email ?? '-' }}</div>
                <div class="info-line">Phone: {{ $customer->customer_phone ?? '-' }}</div>
            </td>

            <td class="info-col" style="padding-right:0;">
                <div class="info-title">Invoice Info</div>
                <div class="info-line">Invoice: <span class="fw-semibold">{{ $invoiceNo }}</span></div>
                <div class="info-line">Date: {{ $invoiceDate }}</div>
                <div class="info-line">
                    Payment Status:
                    <span class="badge {{ $psBadge }}">{{ strtoupper($sale->payment_status ?? 'UNPAID') }}</span>
                </div>

                @if(!empty($sale->note))
                    <div class="info-line" style="margin-top:8px;">
                        <span class="muted">Note:</span><br>
                        <span class="fw-semibold">{{ $sale->note }}</span>
                    </div>
                @endif

                @if($allocatedDp > 0)
                    <div class="info-line" style="margin-top:10px;">
                        <div class="muted" style="margin-bottom:4px;">Deposit (DP) from Sale Order</div>
                        <div>SO Ref: <span class="fw-semibold">{{ $saleOrderRef ?? '-' }}</span></div>
                        <div>
                            DP Paid Allocated to this Invoice:
                            <span class="fw-semibold">{{ format_currency($allocatedDp) }}</span>
                            @if(!is_null($ratioPercent))
                                <span class="muted">({{ (int)$ratioPercent }}% pro-rata)</span>
                            @endif
                        </div>
                    </div>
                @endif
            </td>
        </tr>
    </table>
</div>

<table class="items">
    <thead>
    <tr>
        <th style="width:44%;">Product</th>
        <th style="width:14%;">Net Unit Price</th>
        <th style="width:10%;" class="text-right">Qty</th>
        <th style="width:12%;" class="text-right">Discount</th>
        <th style="width:10%;" class="text-right">Tax</th>
        <th style="width:10%;" class="text-right">Sub Total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($sale->saleDetails as $item)
        <tr>
            <td>
                <div class="fw-semibold">{{ $item->product_name }}</div>
                @if(!empty($item->product_code))
                    <span class="code-pill">{{ $item->product_code }}</span>
                @endif
            </td>
            <td>{{ format_currency($item->unit_price) }}</td>
            <td class="text-right">{{ number_format((int)$item->quantity) }}</td>
            <td class="text-right">{{ format_currency((int)$item->product_discount_amount) }}</td>
            <td class="text-right">{{ format_currency((int)$item->product_tax_amount) }}</td>
            <td class="text-right">{{ format_currency((int)$item->sub_total) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="summary-wrap">
    <tr>
        <!-- <td class="note-box">
            <div class="card" style="padding:10px 12px;">
                <div class="fw-bold" style="margin-bottom:6px;">Summary Notes</div>
                <div class="muted" style="line-height:1.45;">
                    - Grand Total adalah total invoice normal.<br>
                    @if($allocatedDp > 0)
                        - DP (dari Sale Order) ditampilkan sebagai pengurang tagihan di invoice ini.<br>
                        - DP tidak tercatat sebagai “invoice payment”, karena payment DP sudah tercatat saat Sale Order.<br>
                    @endif
                    - Total Paid (Invoice) adalah pembayaran yang dicatat lewat Add Payment pada invoice ini.
                </div>
            </div>
        </td> -->
        <td class="summary-box">
            <div class="card" style="padding:10px 12px;">
                <table class="summary">
                    <tr>
                        <td class="fw-semibold">Discount ({{ (float)($sale->discount_percentage ?? 0) }}%)</td>
                        <td class="text-right">{{ format_currency((int)($sale->discount_amount ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Tax ({{ (float)($sale->tax_percentage ?? 0) }}%)</td>
                        <td class="text-right">{{ format_currency((int)($sale->tax_amount ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Shipping</td>
                        <td class="text-right">{{ format_currency((int)($sale->shipping_amount ?? 0)) }}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Grand Total</td>
                        <td class="text-right fw-bold">{{ format_currency($grandTotal) }}</td>
                    </tr>

                    @if($allocatedDp > 0)
                        <tr>
                            <td class="fw-semibold">Less: DP Paid (Sale Order)</td>
                            <td class="text-right">- {{ format_currency($allocatedDp) }}</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Net Invoice Total</td>
                            <td class="text-right fw-bold">{{ format_currency($netTotalAfterDp) }}</td>
                        </tr>
                    @endif

                    <tr>
                        <td class="fw-semibold">Total Paid (Invoice)</td>
                        <td class="text-right">{{ format_currency($paidAmount) }}</td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Remaining Due</td>
                        <td class="text-right fw-bold">
                            {{ format_currency($allocatedDp > 0 ? $dueAfterDp : $dueAmount) }}
                        </td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<!-- @if(isset($salePayments) && $salePayments->count() > 0)
    <div class="hr"></div>
    <div class="fw-bold" style="margin-bottom:6px;">Payment History</div>
    <table class="payments">
        <thead>
        <tr>
            <th style="width:18%;">Date</th>
            <th style="width:26%;">Reference</th>
            <th style="width:18%;">Method</th>
            <th style="width:18%;" class="text-right">Amount</th>
            <th style="width:20%;">Note</th>
        </tr>
        </thead>
        <tbody>
        @foreach($salePayments as $p)
            <tr>
                <td>{{ $p->date }}</td>
                <td>{{ $p->reference }}</td>
                <td>{{ $p->payment_method }}</td>
                <td class="text-right">{{ format_currency((int)$p->amount) }}</td>
                <td>{{ $p->note }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif -->

<div class="footer">
    {{ $companyName }} &copy; {{ date('Y') }} — Thank you for your business.
</div>

</body>
</html>

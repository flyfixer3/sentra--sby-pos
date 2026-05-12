<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $documentTitle ?? 'Product Labels' }}</title>
    <link rel="stylesheet" href="{{ public_path('b3/bootstrap.min.css') }}">
    <style>
        body { font-size: 12px; color: #111827; }
        .label-card {
            border: 1px dashed #9ca3af;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            min-height: 220px;
        }
        .label-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .label-condition {
            border: 1px solid #1d4ed8;
            border-radius: 999px;
            color: #1d4ed8;
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
        }
        .label-product {
            font-size: 16px;
            font-weight: 700;
            margin-top: 8px;
        }
        .label-code {
            font-size: 12px;
            color: #4b5563;
            margin-bottom: 6px;
        }
        .label-meta {
            font-size: 11px;
            line-height: 1.4;
        }
        .label-meta-row {
            margin-bottom: 3px;
        }
        .scan-key {
            font-size: 11px;
            font-weight: 700;
            margin-top: 6px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row">
        @foreach($labels as $label)
            <div class="col-xs-4">
                <div class="label-card">
                    <div class="clearfix">
                        <div class="pull-left label-title">{{ $label['title'] }}</div>
                        <div class="pull-right label-condition">{{ $label['condition'] }}</div>
                    </div>
                    <div class="label-product">{{ $label['product_name'] }}</div>
                    <div class="label-code">{{ $label['product_code'] }}</div>
                    <div>{!! $label['barcode_svg'] !!}</div>
                    <div class="scan-key">Scan Key: {{ $label['encoded_value'] }}</div>
                    <div class="label-meta">
                        @foreach($label['details'] as $detailLabel => $detailValue)
                            <div class="label-meta-row"><strong>{{ $detailLabel }}:</strong> {{ $detailValue ?: '-' }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
</body>
</html>

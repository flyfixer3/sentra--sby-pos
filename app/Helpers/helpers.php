<?php

if (!function_exists('settings')) {
    function settings() {
        $settings = cache()->remember('settings', 24*60, function () {
            return \Modules\Setting\Entities\Setting::firstOrFail();
        });

        return $settings;
    }
}

if (!function_exists('format_currency')) {
    function format_currency($value, $format = true) {
        if (!$format) {
            return $value;
        }

        $settings = settings();
        $position = $settings->default_currency_position;
        $symbol = $settings->currency->symbol;
        $decimal_separator = $settings->currency->decimal_separator;
        $thousand_separator = $settings->currency->thousand_separator;

        if ($position == 'prefix') {
            $formatted_value = $symbol . number_format((float) $value, 0, $decimal_separator, $thousand_separator);
        } else {
            $formatted_value = number_format((float) $value, 0, $decimal_separator, $thousand_separator) . $symbol;
        }

        return $formatted_value;
    }
}

if (!function_exists('normalize_currency')) {
    function normalize_currency($value, $default = 0) {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }

        $negative = strpos($value, '-') === 0;
        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return $default;
        }

        $amount = (int) $digits;

        return $negative ? -$amount : $amount;
    }
}

if (!function_exists('normalize_currency_request')) {
    function normalize_currency_request($request, array $fields, $default = 0) {
        $normalized = [];
        $input = $request->all();

        foreach ($fields as $field) {
            if (strpos($field, '.*.') !== false) {
                [$prefix, $suffix] = explode('.*.', $field, 2);
                $items = data_get($input, $prefix);

                if (!is_array($items)) {
                    continue;
                }

                foreach ($items as $index => $item) {
                    if (!is_array($item) || !array_key_exists($suffix, $item)) {
                        continue;
                    }

                    data_set($normalized, $prefix . '.' . $index . '.' . $suffix, normalize_currency($item[$suffix], $default));
                }

                continue;
            }

            if (!$request->exists($field)) {
                continue;
            }

            $normalized[$field] = normalize_currency($request->input($field), $default);
        }

        if (!empty($normalized)) {
            $request->merge($normalized);
        }
    }
}

if (!function_exists('make_sale_id')) {
    function make_sale_id($branchCode, $number, $sale) {
        // $branchCode = 'B'; // Default: Cabang pertama

        // Tentukan bulan (A, B, C, ...)
        $saleDate = \Carbon\Carbon::parse($sale->date);
        $monthCode = chr(65 + $saleDate->month - 1); // Januari = A, Februari = B, dst.

        // Tentukan tahun (2 digit)
        $yearCode = $saleDate->format('y'); // Format: YY

        // Hitung nomor urut
        $key = "{$branchCode}{$monthCode}{$yearCode}";
        // $counters[$key] = ($counters[$key] ?? 0) + 1; // Increment counter
        $lastCode = DB::table('sales')
        ->where('reference', 'LIKE', "{$branchCode}{$monthCode}{$yearCode}%")
        ->orderBy('id', 'desc')
        ->first();

        // Nomor urut dimulai dari 001 setiap kali tahun dan bulan berganti
        $nextCounter = $lastCode ? intval(substr($lastCode->reference, -3)) + 1 : 1;

        // Format nomor urut ke 3 digit
        $counterFormatted = str_pad($nextCounter, 3, '0', STR_PAD_LEFT);

        // Buat kode nota baru
        $newInvoiceCode = "{$branchCode}{$monthCode}{$yearCode}{$counterFormatted}";

        // Update kode nota di database
        // $sale->update([
        //     'reference' => $newInvoiceCode
        // ]);
        // $padded_text = $prefix . '-' . str_pad($number, 5, 0, STR_PAD_LEFT);

        return $newInvoiceCode;
    }
}
if (!function_exists('make_reference_id')) {
    function make_reference_id($prefix ,$number) {

        $padded_text = $prefix . '-' . str_pad($number, 5, 0, STR_PAD_LEFT);
        return $padded_text;
    }
}


if (!function_exists('array_merge_numeric_values')) {
    function array_merge_numeric_values() {
        $arrays = func_get_args();
        $merged = array();
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (!is_numeric($value)) {
                    continue;
                }
                if (!isset($merged[$key])) {
                    $merged[$key] = $value;
                } else {
                    $merged[$key] += $value;
                }
            }
        }

        return $merged;
    }
}

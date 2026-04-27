<?php

namespace App\Support\LegacyImport;

use Illuminate\Support\Str;

class ProductCodeParser
{
    public const BRAND_NAMES = [
        'MU' => 'Mulia Glass',
        'XYG' => 'Xinyi Glass',
        'FY' => 'Fuyao Glass',
        'PIL' => 'Pilkington',
        'AGC' => 'AGC Automotive',
        'ASH' => 'Asahimas',
        'ORI' => 'Orisinil',
        'AUTO' => 'Autosafe',
    ];

    public const ACCESSORY_TOKENS = [
        '1HOLE',
        '2HOLE',
        '3HOLE',
        '4HOLE',
        'LIST',
        'RS4',
        'RS3',
        'RS2',
        'RS1',
        'RS',
        'MB',
        'H',
        'A',
        'X',
    ];

    public static function parse(
        string $productCode,
        ?string $part = null,
        ?string $brand = null,
        ?string $accessorySummary = null,
        ?string $mobileCode = null
    ): array {
        $productCode = strtoupper(trim($productCode));
        $part = strtoupper(trim((string) $part));
        $brand = strtoupper(trim((string) $brand));
        $mobileCode = strtoupper(trim((string) $mobileCode));

        if ($part === '' && Str::contains($productCode, '-')) {
            [$part] = explode('-', $productCode, 2);
            $part = strtoupper(trim($part));
        }

        if ($brand === '') {
            foreach (array_keys(self::BRAND_NAMES) as $candidate) {
                if (Str::endsWith($productCode, $candidate)) {
                    $brand = $candidate;
                    break;
                }
            }
        }

        $baseKey = $mobileCode !== '' ? $mobileCode : $productCode;
        if ($mobileCode === '' && $brand !== '' && Str::endsWith($baseKey, $brand)) {
            $baseKey = substr($baseKey, 0, -strlen($brand));
        }

        $accessories = self::parseAccessoryTokens($accessorySummary, $productCode, $baseKey, $brand);

        return [
            'product_code' => $productCode,
            'part_code' => $part !== '' ? $part : null,
            'brand_code' => $brand !== '' ? $brand : null,
            'brand_name' => $brand !== '' ? (self::BRAND_NAMES[$brand] ?? $brand) : null,
            'accessory_tokens' => $accessories,
            'accessory_summary' => empty($accessories) ? '-' : implode('/', $accessories),
            'price_key' => $baseKey !== '' ? $baseKey : $productCode,
        ];
    }

    private static function parseAccessoryTokens(
        ?string $accessorySummary,
        string $productCode,
        string $baseKey,
        string $brandCode
    ): array {
        $tokens = [];

        $summary = strtoupper(trim((string) $accessorySummary));
        if ($summary !== '') {
            foreach (preg_split('/[^A-Z0-9]+/', $summary) as $token) {
                $token = trim($token);
                if ($token !== '') {
                    $tokens[$token] = $token;
                }
            }
        }

        $suffix = $productCode;
        if ($baseKey !== '' && Str::startsWith($suffix, $baseKey)) {
            $suffix = substr($suffix, strlen($baseKey));
        }
        if ($brandCode !== '' && Str::endsWith($suffix, $brandCode)) {
            $suffix = substr($suffix, 0, -strlen($brandCode));
        }

        $remaining = strtoupper($suffix);
        foreach (self::ACCESSORY_TOKENS as $token) {
            while ($remaining !== '' && Str::contains($remaining, $token)) {
                $tokens[$token] = $token;
                $remaining = preg_replace('/' . preg_quote($token, '/') . '/', '', $remaining, 1);
            }
        }

        return array_values($tokens);
    }
}

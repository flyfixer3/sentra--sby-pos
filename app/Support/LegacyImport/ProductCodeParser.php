<?php

namespace App\Support\LegacyImport;

use Illuminate\Support\Str;

class ProductCodeParser
{
    public const ITEM_TYPES = [
        'glass',
        'service',
        'material',
        'film',
        'accessory',
        'other',
    ];

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

    public const GLASS_PART_CODES = [
        'SWR',
        'SWL',
        'RVL',
        'RVR',
        'RDL',
        'RDR',
        'LFW',
        'TRW',
        'FDL',
        'FDR',
        'FVL',
        'FVR',
        'SFL',
        'SFR',
    ];

    private const SERVICE_KEYWORDS = [
        'SER',
        'JASA',
        'TRANSPORT',
        'ONGKIR',
        'BONGKAR',
        'PASANG',
        'INSTALL',
    ];

    private const FILM_KEYWORDS = [
        'FILM',
        'VKOOL',
        'VK',
        'SOLAR GARD',
        'SGP',
    ];

    private const MATERIAL_KEYWORDS = [
        'SEALANT',
        'SEALEN',
        'LEM',
        'AKTIVATOR',
        'BENANG',
        'LAKBAN',
        'GEL',
        'PRIMER',
        'CUP',
        'GUN',
        'PISAU',
        'KAPE',
        'CORONG',
        'KOP ',
        'KOP-',
        'NANOTECH',
        'SOSIS',
    ];

    private const ACCESSORY_ITEM_KEYWORDS = [
        'LIST',
        'MOULDING',
        'MOLDING',
        'PACKING',
        'CLIP',
        'CAP ',
        'BASE',
        'MB',
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

        $itemType = self::detectItemType($productCode, $part, $mobileCode, $accessorySummary);
        if ($itemType !== 'glass') {
            $brand = '';
        }

        $baseKey = $mobileCode !== '' ? $mobileCode : $productCode;
        if ($mobileCode === '' && $brand !== '' && Str::endsWith($baseKey, $brand)) {
            $baseKey = substr($baseKey, 0, -strlen($brand));
        }

        $accessories = self::parseAccessoryTokens($accessorySummary, $productCode, $baseKey, $brand);

        return [
            'product_code' => $productCode,
            'item_type' => $itemType,
            'part_code' => $part !== '' ? $part : null,
            'brand_code' => $brand !== '' ? $brand : null,
            'brand_name' => $brand !== '' ? (self::BRAND_NAMES[$brand] ?? $brand) : null,
            'accessory_tokens' => $accessories,
            'accessory_summary' => empty($accessories) ? '-' : implode('/', $accessories),
            'price_key' => $baseKey !== '' ? $baseKey : $productCode,
        ];
    }

    private static function detectItemType(
        string $productCode,
        string $partCode,
        string $mobileCode,
        ?string $accessorySummary
    ): string {
        $haystacks = array_filter([
            strtoupper(trim($productCode)),
            strtoupper(trim($partCode)),
            strtoupper(trim($mobileCode)),
            strtoupper(trim((string) $accessorySummary)),
        ]);
        $combined = implode(' ', $haystacks);

        if (self::containsAny($combined, self::SERVICE_KEYWORDS)) {
            return 'service';
        }

        if (self::containsAny($combined, self::FILM_KEYWORDS)) {
            return 'film';
        }

        if (self::containsAny($combined, self::MATERIAL_KEYWORDS)) {
            return 'material';
        }

        if (self::containsAny($combined, self::ACCESSORY_ITEM_KEYWORDS)) {
            return 'accessory';
        }

        if (in_array($partCode, self::GLASS_PART_CODES, true)) {
            return 'glass';
        }

        if (Str::contains($productCode, '-')) {
            $prefix = strtoupper((string) Str::before($productCode, '-'));
            if (in_array($prefix, self::GLASS_PART_CODES, true)) {
                return 'glass';
            }
        }

        return 'other';
    }

    private static function containsAny(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && Str::contains($haystack, strtoupper($keyword))) {
                return true;
            }
        }

        return false;
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

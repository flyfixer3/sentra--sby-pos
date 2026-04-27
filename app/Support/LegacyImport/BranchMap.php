<?php

namespace App\Support\LegacyImport;

use Illuminate\Support\Str;

class BranchMap
{
    public const BRANCH_CODES = [
        'cabang surabaya' => 'SBY',
        'surabaya' => 'SBY',
        'cabang bekasi' => 'BKS',
        'bekasi' => 'BKS',
        'cabang tangerang' => 'TGR',
        'tangerang' => 'TGR',
    ];

    public static function guessBranchCode(?string $branchName): ?string
    {
        $key = strtolower(trim((string) $branchName));
        if ($key === '') {
            return null;
        }

        if (isset(self::BRANCH_CODES[$key])) {
            return self::BRANCH_CODES[$key];
        }

        foreach (self::BRANCH_CODES as $name => $code) {
            if (Str::contains($key, $name)) {
                return $code;
            }
        }

        return null;
    }
}

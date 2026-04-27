<?php

namespace App\Support\LegacyImport;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferenceCodeGenerator
{
    public static function generateSaleReference(?string $branchName, $date): string
    {
        return self::generate('sales', 'SA', $branchName, $date);
    }

    public static function generatePurchaseReference(?string $branchName, $date): string
    {
        return self::generate('purchases', 'PR', $branchName, $date);
    }

    private static function generate(string $table, string $typeCode, ?string $branchName, $date): string
    {
        $branchCode = BranchMap::guessBranchCode($branchName) ?? 'UNK';
        $dt = Carbon::parse($date);
        $monthCode = chr(64 + (int) $dt->month);
        $yearCode = $dt->format('y');
        $prefix = "{$typeCode}-{$branchCode}{$monthCode}{$yearCode}";

        $lastReference = DB::table($table)
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = 1;
        if (is_string($lastReference) && preg_match('/(\d{4})$/', $lastReference, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

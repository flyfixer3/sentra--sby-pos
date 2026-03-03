<?php

namespace Modules\Inventory\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class OpeningStockTemplateSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Template';
    }

    public function array(): array
    {
        return [
            // ✅ Opening Stock + Opening HPP (avg_cost)
            // avg_cost = HPP/unit awal untuk baseline moving average
            ['branch_id', 'warehouse_code', 'rack_code', 'product_code', 'qty_good', 'avg_cost'],

            // contoh
            [1, 'TESTINGGDG', 'R001', 'LFW-LSTWSERT12SGP', 5, 1295000],
        ];
    }
}
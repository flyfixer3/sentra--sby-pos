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
            // ✅ hanya qty_good
            ['branch_id', 'warehouse_code', 'rack_code', 'product_code', 'qty_good'],
            [1, 'TESTINGGDG', 'R001', 'LFW-LSTWSERT12SGP', 5],
        ];
    }
}

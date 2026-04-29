<?php

namespace Modules\Inventory\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Inventory\Entities\StockOpname;

class StockOpnameTemplateSheet implements FromArray, WithTitle
{
    private StockOpname $stockOpname;

    public function __construct(StockOpname $stockOpname)
    {
        $this->stockOpname = $stockOpname;
    }

    public function array(): array
    {
        $rows = [
            ['product_code', 'product_name', 'rack_code', 'rack_name', 'system_qty', 'physical_qty', 'note'],
        ];

        foreach ($this->stockOpname->items()->orderBy('product_code_snapshot')->get() as $item) {
            $rows[] = [
                (string) $item->product_code_snapshot,
                (string) $item->product_name_snapshot,
                (string) ($item->rack_code_snapshot ?? ''),
                (string) ($item->rack_name_snapshot ?? ''),
                (int) ($item->system_qty ?? 0),
                $item->physical_qty,
                (string) ($item->note ?? ''),
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Template';
    }
}

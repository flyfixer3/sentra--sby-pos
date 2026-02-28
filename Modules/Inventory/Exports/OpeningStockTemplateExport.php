<?php

namespace Modules\Inventory\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Inventory\Exports\Sheets\OpeningStockTemplateSheet;
use Modules\Inventory\Exports\Sheets\OpeningStockReferenceSheet;

class OpeningStockTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new OpeningStockTemplateSheet(),
            new OpeningStockReferenceSheet(),
        ];
    }
}

<?php

namespace Modules\Inventory\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Inventory\Entities\StockOpname;
use Modules\Inventory\Exports\Sheets\StockOpnameInstructionSheet;
use Modules\Inventory\Exports\Sheets\StockOpnameTemplateSheet;

class StockOpnameTemplateExport implements WithMultipleSheets
{
    private StockOpname $stockOpname;

    public function __construct(StockOpname $stockOpname)
    {
        $this->stockOpname = $stockOpname;
    }

    public function sheets(): array
    {
        return [
            new StockOpnameTemplateSheet($this->stockOpname),
            new StockOpnameInstructionSheet($this->stockOpname),
        ];
    }
}

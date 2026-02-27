<?php

namespace Modules\Inventory\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Inventory\Exports\Sheets\RackTemplateSheet;
use Modules\Inventory\Exports\Sheets\RackReferenceSheet;

class RackTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new RackTemplateSheet(),
            new RackReferenceSheet(),
        ];
    }
}

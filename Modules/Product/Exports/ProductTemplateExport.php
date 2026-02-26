<?php

namespace Modules\Product\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\Product\Exports\Sheets\ProductTemplateSheet;
use Modules\Product\Exports\Sheets\ProductReferenceSheet;

class ProductTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ProductTemplateSheet(),
            new ProductReferenceSheet(),
        ];
    }
}
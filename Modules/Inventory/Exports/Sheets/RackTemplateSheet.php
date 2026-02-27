<?php

namespace Modules\Inventory\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class RackTemplateSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Template';
    }

    public function array(): array
    {
        return [
            ['warehouse_code', 'branch_id(optional)', 'rack_code', 'rack_name', 'description'],
            ['TESTINGGDG', '', 'R001', 'Rack 01', 'Optional description'],
        ];
    }
}

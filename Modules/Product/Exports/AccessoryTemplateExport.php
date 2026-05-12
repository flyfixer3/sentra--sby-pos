<?php

namespace Modules\Product\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class AccessoryTemplateExport implements FromArray, WithTitle
{
    public function array(): array
    {
        return [
            ['accessory_code', 'accessory_name'],
            ['ACC-SENSOR', 'Rain Sensor'],
            ['ACC-CLIP', 'Mirror Clip'],
        ];
    }

    public function title(): string
    {
        return 'Accessories';
    }
}

<?php

namespace Modules\Product\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductTemplateSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Template';
    }

    public function array(): array
    {
        return [
            [
                'category_code',
                'category_name',
                'accessory_code',
                'accessory_name',
                'product_name',
                'product_code',
                'product_barcode_symbology',
                'product_cost',
                'product_price',
                'product_unit',
                'product_order_tax',
                'product_tax_type',
                'product_note',
            ],
            [
                'LFW',
                'Laminated Front Windshield',
                '-',
                'No Accessory',
                'Toyota Avanza / Xenia 2012-2018 SGP',
                'LFW-LSTWSERT12SGP',
                'C128',
                450000,
                900000,
                'Unit',
                '',
                '',
                'Optional note',
            ],
        ];
    }
}
<?php

namespace Modules\Inventory\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Modules\Inventory\Entities\StockOpname;

class StockOpnameInstructionSheet implements FromArray, WithTitle
{
    private StockOpname $stockOpname;

    public function __construct(StockOpname $stockOpname)
    {
        $this->stockOpname = $stockOpname;
    }

    public function array(): array
    {
        return [
            ['Stock Opname Reference', $this->stockOpname->reference],
            ['Cabang', optional($this->stockOpname->branch)->name],
            ['Gudang Adjustment', optional($this->stockOpname->warehouse)->warehouse_name],
            ['Tanggal Opname', $this->stockOpname->opname_date],
            [''],
            ['Petunjuk'],
            ['1. Isi hanya kolom physical_qty dan note pada sheet Template.'],
            ['2. Jika barang dicek dan tidak ada fisik, isi physical_qty = 0.'],
            ['3. Jika barang belum dicek, biarkan physical_qty kosong.'],
            ['4. Jika menemukan kaca fisik yang tidak ada di list, tambahkan baris baru dengan product_code yang valid.'],
            ['5. Fitur ini khusus kaca (item_type = glass). Product code non-kaca akan ditolak saat import.'],
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }
}

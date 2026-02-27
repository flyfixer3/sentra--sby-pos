<?php

namespace Modules\Inventory\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;

class RackReferenceSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Reference';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['INFO'];
        $rows[] = ['- warehouse_code wajib sesuai tabel warehouses.'];
        $rows[] = ['- branch_id boleh kosong → akan otomatis pakai warehouses.branch_id.'];
        $rows[] = ['- rack_code unik per warehouse.'];
        $rows[] = [''];

        $rows[] = ['Existing Warehouses'];
        $rows[] = ['warehouse_code', 'warehouse_name', 'branch_id'];
        $whs = DB::table('warehouses')->orderBy('warehouse_code')->get(['warehouse_code', 'warehouse_name', 'branch_id']);
        foreach ($whs as $w) {
            $rows[] = [$w->warehouse_code, $w->warehouse_name, (string)($w->branch_id ?? '')];
        }

        $rows[] = [''];
        $rows[] = ['Existing Branches'];
        $rows[] = ['id', 'name'];
        $bs = DB::table('branches')->orderBy('id')->get(['id', 'name']);
        foreach ($bs as $b) {
            $rows[] = [(string)$b->id, $b->name];
        }

        return $rows;
    }
}

<?php

namespace Modules\Inventory\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;

class OpeningStockReferenceSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Reference';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['INFO'];
        $rows[] = ['- Import ini akan membuat MUTATION (In) untuk OPENING STOCK (GOOD only) per rack.'];
        $rows[] = ['- Qty DEFECT/DAMAGED tidak di-import dari Excel. Gunakan Adjustment / Quality Reclass agar detail item valid.'];
        $rows[] = ['- Pastikan product_code sudah ada di tabel products (import products dulu).'];
        $rows[] = ['- Pastikan rack sudah ada (import racks dulu).'];
        $rows[] = ['- Note mutation akan ditandai: AUTO GENERATED: EXCEL'];
        $rows[] = [''];

        $rows[] = ['Branches'];
        $rows[] = ['id', 'name'];
        $bs = DB::table('branches')->orderBy('id')->get(['id', 'name']);
        foreach ($bs as $b) {
            $rows[] = [(string)$b->id, $b->name];
        }

        $rows[] = [''];
        $rows[] = ['Warehouses'];
        $rows[] = ['warehouse_code', 'warehouse_name', 'branch_id'];
        $whs = DB::table('warehouses')->orderBy('warehouse_code')->get(['warehouse_code', 'warehouse_name', 'branch_id']);
        foreach ($whs as $w) {
            $rows[] = [$w->warehouse_code, $w->warehouse_name, (string)($w->branch_id ?? '')];
        }

        $rows[] = [''];
        $rows[] = ['Racks'];
        $rows[] = ['warehouse_code', 'rack_code', 'rack_name', 'branch_id'];
        $racks = DB::table('racks')
            ->join('warehouses', 'warehouses.id', '=', 'racks.warehouse_id')
            ->orderBy('warehouses.warehouse_code')
            ->orderBy('racks.code')
            ->get([
                'warehouses.warehouse_code as warehouse_code',
                'racks.code as rack_code',
                'racks.name as rack_name',
                'racks.branch_id as branch_id',
            ]);

        foreach ($racks as $r) {
            $rows[] = [$r->warehouse_code, $r->rack_code, (string)($r->rack_name ?? ''), (string)($r->branch_id ?? '')];
        }

        return $rows;
    }
}

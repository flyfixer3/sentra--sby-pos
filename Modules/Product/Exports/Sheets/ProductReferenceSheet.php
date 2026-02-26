<?php

namespace Modules\Product\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;

class ProductReferenceSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Reference';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['INFO'];
        $rows[] = ['- Isi Template sheet, lalu upload kembali ke sistem.'];
        $rows[] = ['- category_code & accessory_code boleh auto-create jika belum ada, asal category_name/accessory_name diisi.'];
        $rows[] = [''];

        $rows[] = ['Existing Categories'];
        $rows[] = ['category_code', 'category_name'];
        $cats = DB::table('categories')->orderBy('category_code')->get(['category_code', 'category_name']);
        foreach ($cats as $c) {
            $rows[] = [$c->category_code, $c->category_name];
        }

        $rows[] = [''];
        $rows[] = ['Existing Accessories'];
        $rows[] = ['accessory_code', 'accessory_name'];
        $accs = DB::table('accessories')->orderBy('accessory_code')->get(['accessory_code', 'accessory_name']);
        foreach ($accs as $a) {
            $rows[] = [$a->accessory_code, $a->accessory_name];
        }

        return $rows;
    }
}
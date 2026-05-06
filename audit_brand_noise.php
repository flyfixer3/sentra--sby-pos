<?php
require 'c:/Users/240ce/OneDrive/Desktop/Code/CRM/sentra--sby-pos/vendor/autoload.php';
$app = require 'c:/Users/240ce/OneDrive/Desktop/Code/CRM/sentra--sby-pos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$rows = DB::table('products as p')
    ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
    ->select('p.product_code','p.product_name','p.item_type','b.brand_code','b.brand_name','p.product_note')
    ->whereNotNull('p.brand_id')
    ->whereNotIn('b.brand_code', ['MU','XYG','FY','PIL','AGC','ASH','ORI','AUTO'])
    ->orderBy('b.brand_code')
    ->orderBy('p.product_code')
    ->limit(120)
    ->get();
foreach ($rows as $row) {
    echo implode("\t", [
        $row->brand_code,
        $row->item_type,
        $row->product_code,
        preg_replace('/\s+/', ' ', (string) $row->product_name),
        preg_replace('/\s+/', ' ', (string) $row->product_note),
    ]) . PHP_EOL;
}
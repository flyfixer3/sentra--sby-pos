<?php
require 'c:/Users/240ce/OneDrive/Desktop/Code/CRM/sentra--sby-pos/vendor/autoload.php';
$app = require 'c:/Users/240ce/OneDrive/Desktop/Code/CRM/sentra--sby-pos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
$rows = DB::table('products')
    ->select('product_code','product_name','item_type','product_note')
    ->where('product_note', 'like', '%AUTO_OPENING_BALANCE_FOR_HISTORICAL_SALE%')
    ->orderBy('product_code')
    ->limit(120)
    ->get();
foreach ($rows as $row) {
    echo implode("\t", [
        $row->item_type,
        $row->product_code,
        preg_replace('/\s+/', ' ', (string) $row->product_name),
        preg_replace('/\s+/', ' ', (string) $row->product_note),
    ]) . PHP_EOL;
}
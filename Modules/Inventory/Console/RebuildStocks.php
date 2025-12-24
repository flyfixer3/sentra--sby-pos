<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildStocks extends Command
{
    protected $signature   = 'inventory:rebuild-stocks {--from-products=}';
    protected $description = 'Rebuild tabel stocks dari histori mutations; opsional seed awal dari products.product_quantity ke branch,warehouse default (format: "branch,warehouse").';

    public function handle(): int
    {
        $this->info('Rebuilding stocks from mutations...');

        // 1) reset saldo
        DB::table('stocks')->update(['qty_available' => 0, 'qty_incoming' => 0, 'qty_reserved' => 0]);

        // 2) agregasi delta per (product,branch,warehouse)
        $this->info('Aggregating mutations → delta...');
        $rows = DB::table('mutations')
            ->select([
                'product_id',
                'branch_id',
                'warehouse_id',
                DB::raw('COALESCE(SUM(stock_in - stock_out),0) AS delta_qty'),
            ])
            ->groupBy('product_id', 'branch_id', 'warehouse_id')
            ->get();

        // 3) upsert saldo
        $this->info('Upserting saldo to stocks...');
        foreach ($rows as $r) {
            DB::table('stocks')->updateOrInsert(
                [
                    'product_id'   => $r->product_id,
                    'branch_id'    => $r->branch_id,
                    'warehouse_id' => $r->warehouse_id,
                ],
                [
                    'qty_available' => (int) $r->delta_qty,
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );
        }

        // 4) optional seed dari products.product_quantity ke branch/gudang default
        if ($opt = $this->option('from-products')) {
            [$defaultBranch, $defaultWarehouse] = array_map('intval', explode(',', $opt));
            $this->info("Seeding from products.product_quantity → branch={$defaultBranch}, warehouse={$defaultWarehouse}");

            $products = DB::table('products')->select('id', 'product_quantity', 'product_stock_alert')->get();
            foreach ($products as $p) {
                if ((int)$p->product_quantity === 0) continue;

                DB::table('stocks')->updateOrInsert(
                    [
                        'product_id'   => $p->id,
                        'branch_id'    => $defaultBranch,
                        'warehouse_id' => $defaultWarehouse,
                    ],
                    [
                        'qty_available' => DB::raw('qty_available + '.(int)$p->product_quantity),
                        'min_stock'     => (int)$p->product_stock_alert,
                        'updated_at'    => now(),
                        'created_at'    => now(),
                    ]
                );
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}

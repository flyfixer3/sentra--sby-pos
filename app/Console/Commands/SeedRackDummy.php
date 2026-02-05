<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedRackDummy extends Command
{
    protected $signature = 'sentra:seed-rack-dummy {--warehouse_id=} {--limit=0} {--user_id=1}';
    protected $description = 'Create default racks and stock_racks based on existing stocks (dummy seeding)';

    public function handle()
    {
        if (
            !Schema::hasTable('racks') ||
            !Schema::hasTable('stock_racks') ||
            !Schema::hasTable('stocks') ||
            !Schema::hasTable('warehouses')
        ) {
            $this->error('Required tables not found: racks/stock_racks/stocks/warehouses');
            return 1;
        }

        $warehouseFilter = $this->option('warehouse_id');
        $limit = (int) $this->option('limit');
        $userId = (int) $this->option('user_id') ?: 1;

        DB::transaction(function () use ($warehouseFilter, $limit, $userId) {

            // 1) pastikan tiap warehouse ada rack default "DEF"
            $whQuery = DB::table('warehouses')->select('id', 'branch_id');

            if ($warehouseFilter) {
                $whQuery->where('id', (int) $warehouseFilter);
            }

            $warehouses = $whQuery->get();

            foreach ($warehouses as $w) {
                $exists = DB::table('racks')
                    ->where('warehouse_id', (int) $w->id)
                    ->where('code', 'DEF')
                    ->exists();

                if (!$exists) {
                    DB::table('racks')->insert([
                        'warehouse_id' => (int) $w->id,
                        'code' => 'DEF',
                        'name' => 'Default Rack',
                        'description' => 'Auto seeded default rack',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // ambil id rack DEF per warehouse
            $rackMap = DB::table('racks')
                ->select('id', 'warehouse_id')
                ->where('code', 'DEF')
                ->when($warehouseFilter, function ($q) use ($warehouseFilter) {
                    $q->where('warehouse_id', (int) $warehouseFilter);
                })
                ->get()
                ->groupBy('warehouse_id');

            // 2) isi stock_racks dari stocks qty_available > 0
            // ambil branch_id dari warehouses (karena stocks.branch_id banyak NULL)
            $stockQuery = DB::table('stocks')
                ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
                ->select(
                    'stocks.product_id',
                    'stocks.warehouse_id',
                    'stocks.qty_available',
                    DB::raw('warehouses.branch_id as branch_id')
                )
                ->where('stocks.qty_available', '>', 0);

            if ($warehouseFilter) {
                $stockQuery->where('stocks.warehouse_id', (int) $warehouseFilter);
            }

            if ($limit > 0) {
                $stockQuery->limit($limit);
            }

            $stocks = $stockQuery->get();

            $inserted = 0;

            foreach ($stocks as $s) {
                $warehouseId = (int) ($s->warehouse_id ?? 0);
                $branchId = (int) ($s->branch_id ?? 0);

                if ($warehouseId <= 0 || $branchId <= 0) continue;

                $defRackId = 0;
                if (isset($rackMap[$warehouseId]) && $rackMap[$warehouseId]->count() > 0) {
                    $defRackId = (int) $rackMap[$warehouseId]->first()->id;
                }
                if ($defRackId <= 0) continue;

                $exists = DB::table('stock_racks')
                    ->where('product_id', (int) $s->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('branch_id', $branchId)
                    ->where('rack_id', $defRackId)
                    ->exists();

                if ($exists) continue;

                DB::table('stock_racks')->insert([
                    'product_id' => (int) $s->product_id,
                    'rack_id' => $defRackId,
                    'warehouse_id' => $warehouseId,
                    'branch_id' => $branchId,
                    'qty_available' => (int) ($s->qty_available ?? 0),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $inserted++;
            }

            $this->info("Done. Inserted stock_racks rows: {$inserted}");
        });

        return 0;
    }
}

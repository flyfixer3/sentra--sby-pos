<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\SaleOrder\Entities\SaleOrder;

class SyncSaleOrderReserved extends Command
{
    protected $signature = 'sale-orders:sync-reserved
        {--dry-run : Only show the changes that would be applied}
        {--apply : Apply the reservation updates}
        {--branch_id= : Limit to a branch_id}
        {--sale_order_id= : Limit to a specific Sale Order ID}';

    protected $description = 'Sync missing reserved pool stock for Sale Orders.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run');

        if ($apply && $dryRun) {
            $this->warn('Both --apply and --dry-run provided. Running dry-run only.');
            $apply = false;
        }

        if (!$apply) {
            $dryRun = true;
        }

        $branchId = $this->option('branch_id');
        $saleOrderId = $this->option('sale_order_id');

        $ordersQuery = SaleOrder::withoutGlobalScopes()
            ->whereNull('deleted_at');

        if (is_numeric($branchId)) {
            $ordersQuery->where('branch_id', (int) $branchId);
        }

        if (is_numeric($saleOrderId)) {
            $ordersQuery->where('id', (int) $saleOrderId);
        }

        $orders = $ordersQuery->get(['id', 'reference', 'branch_id']);

        if ($orders->isEmpty()) {
            $this->info('No sale orders found for the selected filters.');
            return Command::SUCCESS;
        }

        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->all();
        $orderMeta = $orders->keyBy('id');

        $orderedRows = DB::table('sale_order_items')
            ->select('sale_order_id', 'product_id', DB::raw('SUM(quantity) as qty'))
            ->whereIn('sale_order_id', $orderIds)
            ->whereNull('deleted_at')
            ->groupBy('sale_order_id', 'product_id')
            ->get();

        $deliveredExpr = "CASE\n            WHEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0)) > 0\n                THEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0))\n            ELSE COALESCE(sdi.quantity,0)\n        END";

        $deliveredRows = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->whereIn('sd.sale_order_id', $orderIds)
            ->whereNull('sd.deleted_at')
            ->whereNull('sdi.deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                    ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select('sd.sale_order_id', 'sdi.product_id', DB::raw("SUM({$deliveredExpr}) as qty"))
            ->groupBy('sd.sale_order_id', 'sdi.product_id')
            ->get();

        $deliveredMap = [];
        foreach ($deliveredRows as $row) {
            $soId = (int) $row->sale_order_id;
            $pid = (int) $row->product_id;
            $qty = (int) $row->qty;

            $deliveredMap[$soId][$pid] = $qty;
        }

        $rows = [];
        $expectedByBranchProduct = [];
        $productIds = [];
        $branchIds = [];

        foreach ($orderedRows as $row) {
            $soId = (int) $row->sale_order_id;
            $pid = (int) $row->product_id;
            $orderedQty = (int) $row->qty;
            $deliveredQty = (int) ($deliveredMap[$soId][$pid] ?? 0);
            $remainingQty = max(0, $orderedQty - $deliveredQty);

            $meta = $orderMeta->get($soId);
            $branchIdValue = (int) ($meta->branch_id ?? 0);

            if ($remainingQty > 0) {
                if (!isset($expectedByBranchProduct[$branchIdValue][$pid])) {
                    $expectedByBranchProduct[$branchIdValue][$pid] = 0;
                }
                $expectedByBranchProduct[$branchIdValue][$pid] += $remainingQty;
            }

            $rows[] = [
                'sale_order_id' => $soId,
                'reference' => (string) ($meta->reference ?? ''),
                'branch_id' => $branchIdValue,
                'product_id' => $pid,
                'ordered_qty' => $orderedQty,
                'delivered_qty' => $deliveredQty,
                'expected_qty' => $remainingQty,
            ];

            $productIds[] = $pid;
            $branchIds[] = $branchIdValue;
        }

        $productIds = array_values(array_unique(array_filter($productIds)));
        $branchIds = array_values(array_unique(array_filter($branchIds)));

        $reservedRows = DB::table('stocks')
            ->whereNull('warehouse_id')
            ->whereIn('product_id', $productIds)
            ->whereIn('branch_id', $branchIds)
            ->get(['branch_id', 'product_id', 'qty_reserved']);

        $reservedMap = [];
        foreach ($reservedRows as $row) {
            $reservedMap[(int) $row->branch_id][(int) $row->product_id] = (int) ($row->qty_reserved ?? 0);
        }

        $missingByBranchProduct = [];
        foreach ($expectedByBranchProduct as $bId => $productMap) {
            foreach ($productMap as $pid => $expectedQty) {
                $currentReserved = (int) ($reservedMap[$bId][$pid] ?? 0);
                $missing = max(0, (int) $expectedQty - $currentReserved);

                $missingByBranchProduct[$bId][$pid] = $missing;
            }
        }

        $tableRows = [];
        foreach ($rows as $row) {
            $bId = (int) $row['branch_id'];
            $pid = (int) $row['product_id'];
            $currentReserved = (int) ($reservedMap[$bId][$pid] ?? 0);
            $missing = (int) ($missingByBranchProduct[$bId][$pid] ?? 0);

            $tableRows[] = [
                $row['sale_order_id'],
                $row['reference'],
                $bId,
                $pid,
                $row['ordered_qty'],
                $row['delivered_qty'],
                $row['expected_qty'],
                $currentReserved,
                $missing,
            ];
        }

        $this->table(
            ['SO ID', 'SO Ref', 'Branch', 'Product', 'Ordered', 'Delivered', 'Expected Remaining', 'Pool Reserved', 'Missing For Product'],
            $tableRows
        );

        if ($dryRun) {
            $this->info('Dry-run only. Use --apply to update missing reserved quantities.');
            return Command::SUCCESS;
        }

        $updated = 0;

        DB::transaction(function () use ($missingByBranchProduct, &$updated) {
            foreach ($missingByBranchProduct as $branchIdValue => $productMap) {
                foreach ($productMap as $productId => $missing) {
                    $missing = (int) $missing;
                    if ($missing <= 0) {
                        continue;
                    }

                    $row = DB::table('stocks')
                        ->where('branch_id', (int) $branchIdValue)
                        ->whereNull('warehouse_id')
                        ->where('product_id', (int) $productId)
                        ->lockForUpdate()
                        ->first();

                    if (!$row) {
                        DB::table('stocks')->insert([
                            'product_id'     => (int) $productId,
                            'branch_id'      => (int) $branchIdValue,
                            'warehouse_id'   => null,
                            'qty_total'      => 0,
                            'qty_reserved'   => 0,
                            'qty_incoming'   => 0,
                            'min_stock'      => 0,
                            'note'           => 'Auto created by sale-orders:sync-reserved',
                            'created_by'     => auth()->id(),
                            'updated_by'     => auth()->id(),
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]);

                        $row = (object) ['qty_reserved' => 0];
                    }

                    $current = (int) ($row->qty_reserved ?? 0);

                    DB::table('stocks')
                        ->where('branch_id', (int) $branchIdValue)
                        ->whereNull('warehouse_id')
                        ->where('product_id', (int) $productId)
                        ->update([
                            'qty_reserved' => $current + $missing,
                            'updated_by' => auth()->id(),
                            'updated_at' => now(),
                        ]);

                    $updated += $missing;
                }
            }
        });

        $this->info('Applied missing reserved quantities. Total added: ' . $updated);

        return Command::SUCCESS;
    }
}

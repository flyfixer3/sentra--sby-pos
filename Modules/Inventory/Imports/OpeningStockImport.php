<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use App\Support\BranchContext;
use Modules\Mutation\Http\Controllers\MutationController;
use Modules\Product\Entities\ProductHpp;

class OpeningStockImport implements OnEachRow, WithHeadingRow
{
    private int $userId;
    private string $date;
    private string $reference;
    private string $note;

    private MutationController $mutationController;

    /**
     * Aggregator untuk weighted avg_cost per (branch_id + product_id)
     * key => ['qty' => int, 'value' => float]
     */
    private array $openingCostAgg = [];

    /**
     * counter untuk memastikan ada row yang benar-benar ter-import
     */
    private int $importedCount = 0;

    public function __construct(int $userId, string $date, string $reference, string $note)
    {
        $this->userId = (int) $userId;
        $this->date = $date;
        $this->reference = $reference;
        $this->note = $note;

        $this->mutationController = app(MutationController::class);
    }

    public function getImportedCount(): int
    {
        return (int) $this->importedCount;
    }

    public function onRow(Row $row)
    {
        $excelRow = (int) $row->getIndex(); // ✅ row number asli di Excel
        $r = $row->toArray();

        $branchId = (int) ($r['branch_id'] ?? 0);
        $warehouseCode = trim((string) ($r['warehouse_code'] ?? ''));
        $rackCode = trim((string) ($r['rack_code'] ?? ''));
        $productCode = trim((string) ($r['product_code'] ?? ''));

        $qtyGoodRaw = $r['qty_good'] ?? 0;
        $qtyGood = (int) (($qtyGoodRaw === '' || $qtyGoodRaw === null) ? 0 : $qtyGoodRaw);

        $avgCostRaw = $r['avg_cost'] ?? 0;
        $avgCost = (float) (($avgCostRaw === '' || $avgCostRaw === null) ? 0 : $avgCostRaw);
        if ($avgCost < 0) $avgCost = 0;

        // ✅ skip baris kosong (biar user bisa taro baris kosong tanpa error)
        // tapi ingat: kalau semua baris ke-skip, controller akan bilang "0 row imported"
        if ($branchId <= 0 && $warehouseCode === '' && $rackCode === '' && $productCode === '' && $qtyGood <= 0) {
            return;
        }

        // =========================
        // ✅ VALIDASI BASIC
        // =========================
        if ($branchId <= 0) {
            throw new \RuntimeException("Row {$excelRow}: branch_id is required and must be >= 1.");
        }
        if ($warehouseCode === '') {
            throw new \RuntimeException("Row {$excelRow}: warehouse_code is required.");
        }
        if ($rackCode === '') {
            throw new \RuntimeException("Row {$excelRow}: rack_code is required.");
        }
        if ($productCode === '') {
            throw new \RuntimeException("Row {$excelRow}: product_code is required.");
        }

        // opening stock harus meaningful
        if ($qtyGood < 1) {
            throw new \RuntimeException("Row {$excelRow}: qty_good must be >= 1.");
        }

        // avg_cost boleh 0, tapi wajib ada kolomnya (opening baseline)
        if (!array_key_exists('avg_cost', $r)) {
            throw new \RuntimeException("Row {$excelRow}: avg_cost column is missing. Please use the latest template.");
        }
        if ($avgCost < 0) {
            throw new \RuntimeException("Row {$excelRow}: avg_cost must be >= 0.");
        }

        // =========================
        // ✅ ENFORCE ACTIVE BRANCH
        // =========================
        $active = BranchContext::id();
        if ($active !== 'all' && $active !== null && $active !== '') {
            if ((int) $active !== (int) $branchId) {
                throw new \RuntimeException("Row {$excelRow}: Active branch is {$active}, but file row branch_id is {$branchId}. Switch branch to ALL or the same branch before import.");
            }
        }

        // =========================
        // ✅ VALIDASI DB + APPLY
        // =========================
        DB::transaction(function () use ($excelRow, $branchId, $warehouseCode, $rackCode, $productCode, $qtyGood, $avgCost) {

            $warehouse = DB::table('warehouses')
                ->where('warehouse_code', $warehouseCode)
                ->first();

            if (!$warehouse) {
                throw new \RuntimeException("Row {$excelRow}: Warehouse code not found: {$warehouseCode}");
            }

            if ((int) ($warehouse->branch_id ?? 0) !== (int) $branchId) {
                throw new \RuntimeException("Row {$excelRow}: Warehouse {$warehouseCode} belongs to branch_id {$warehouse->branch_id}, but row branch_id is {$branchId}");
            }

            $rack = DB::table('racks')
                ->where('warehouse_id', (int) $warehouse->id)
                ->where('code', $rackCode)
                ->first();

            if (!$rack) {
                throw new \RuntimeException("Row {$excelRow}: Rack not found: warehouse_code={$warehouseCode}, rack_code={$rackCode}");
            }

            $product = DB::table('products')
                ->where('product_code', $productCode)
                ->first();

            if (!$product) {
                throw new \RuntimeException("Row {$excelRow}: Product code not found: {$productCode}");
            }

            // ✅ MUTATION IN opening stock (GOOD only)
            $this->mutationController->applyInOut(
                (int) $branchId,
                (int) $warehouse->id,
                (int) $product->id,
                'In',
                (int) $qtyGood,
                $this->reference,
                $this->note,
                $this->date,
                (int) $rack->id,
                'good',
                'summary'
            );

            // ✅ OPENING HPP baseline (weighted average across rows)
            $this->applyOpeningHpp(
                (int) $branchId,
                (int) $product->id,
                (int) $qtyGood,
                (float) $avgCost
            );

            $this->importedCount++;
        });
    }

    private function applyOpeningHpp(int $branchId, int $productId, int $qty, float $avgCost): void
    {
        if ($qty <= 0) return;
        if ($avgCost < 0) $avgCost = 0;

        $key = $branchId . ':' . $productId;

        if (!isset($this->openingCostAgg[$key])) {
            $this->openingCostAgg[$key] = ['qty' => 0, 'value' => 0.0];
        }

        $this->openingCostAgg[$key]['qty'] += (int) $qty;
        $this->openingCostAgg[$key]['value'] += ((float) $avgCost * (int) $qty);

        $totalQty = (int) $this->openingCostAgg[$key]['qty'];
        $totalVal = (float) $this->openingCostAgg[$key]['value'];

        $newAvg = 0.0;
        if ($totalQty > 0) {
            $newAvg = $totalVal / $totalQty;
        }

        $hppRow = ProductHpp::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$hppRow) {
            ProductHpp::create([
                'branch_id'          => $branchId,
                'product_id'         => $productId,
                'avg_cost'           => round($newAvg, 2),
                'last_purchase_cost' => round($avgCost, 2),
            ]);
        } else {
            $hppRow->update([
                'avg_cost'           => round($newAvg, 2),
                'last_purchase_cost' => round($avgCost, 2),
            ]);
        }
    }
}
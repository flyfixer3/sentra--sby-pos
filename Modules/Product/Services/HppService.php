<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Modules\Product\Entities\ProductHpp;

class HppService
{
    /**
     * Update HPP moving average untuk 1 product pada 1 branch, berdasarkan penerimaan barang (incoming).
     *
     * @param int $branchId
     * @param int $productId
     * @param int $incomingQty
     * @param float|int $incomingUnitCost
     * @param int $incomingGoodQty (opsional, kalau mau basis khusus good)
     * @param int $incomingDefectQty
     * @param int $incomingDamagedQty
     * @return void
     */
    public function applyIncoming(
        int $branchId,
        int $productId,
        int $incomingQty,
        $incomingUnitCost,
        int $incomingGoodQty = 0,
        int $incomingDefectQty = 0,
        int $incomingDamagedQty = 0
    ): void {
        $incomingQty = max(0, (int) $incomingQty);
        if ($incomingQty <= 0) {
            return;
        }

        $unitCost = (float) $incomingUnitCost;
        if ($unitCost < 0) {
            $unitCost = 0;
        }

        DB::transaction(function () use (
            $branchId,
            $productId,
            $incomingQty,
            $unitCost
        ) {
            // Lock row HPP kalau ada
            $hppRow = ProductHpp::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $oldAvg = $hppRow ? (float) $hppRow->avg_cost : 0.0;

            /**
             * Ambil stok saat ini (setelah mutation masuk dibuat).
             * Karena receiving sudah menambah stock_racks, maka totalAfter sudah termasuk incoming.
             * Kita butuh oldQty sebelum incoming => oldQty = totalAfter - incomingQty.
             */
            $stockAgg = DB::table('stock_racks')
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->selectRaw('
                    COALESCE(SUM(qty_good), 0) as sum_good,
                    COALESCE(SUM(qty_defect), 0) as sum_defect,
                    COALESCE(SUM(qty_damaged), 0) as sum_damaged,
                    COALESCE(SUM(qty_available), 0) as sum_available
                ')
                ->first();

            $totalAfter = 0;
            if ($stockAgg) {
                // Basis qty untuk costing: total kualitas (good+defect+damaged).
                // Ini paling aman karena receiving kamu bisa split kualitas.
                $totalAfter = (int) (($stockAgg->sum_good ?? 0) + ($stockAgg->sum_defect ?? 0) + ($stockAgg->sum_damaged ?? 0));

                // fallback kalau kolom kualitas belum populated (jaga-jaga)
                if ($totalAfter <= 0) {
                    $totalAfter = (int) ($stockAgg->sum_available ?? 0);
                }
            }

            $oldQty = max(0, (int) $totalAfter - (int) $incomingQty);

            $denom = $oldQty + $incomingQty;

            if ($denom <= 0) {
                $newAvg = 0.0;
            } elseif ($oldQty <= 0) {
                $newAvg = $unitCost;
            } else {
                $newAvg = (($oldAvg * $oldQty) + ($unitCost * $incomingQty)) / $denom;
            }

            if (!$hppRow) {
                ProductHpp::create([
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'avg_cost' => round($newAvg, 2),
                    'last_purchase_cost' => round($unitCost, 2),
                ]);
            } else {
                $hppRow->update([
                    'avg_cost' => round($newAvg, 2),
                    'last_purchase_cost' => round($unitCost, 2),
                ]);
            }
        });
    }

    /**
     * Ambil HPP saat ini untuk product pada branch.
     * Kalau belum ada row, return 0.
     */
    public function getCurrentHpp(int $branchId, int $productId): float
    {
        $val = ProductHpp::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->value('avg_cost');

        return (float) ($val ?? 0);
    }
}
<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Modules\Product\Entities\ProductHpp;

class HppService
{
    private function baseLedgerQuery(int $branchId, int $productId)
    {
        return ProductHpp::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId);
    }

    private function orderedLedgerQuery(int $branchId, int $productId, $asOf = null)
    {
        $query = $this->baseLedgerQuery($branchId, $productId);

        if ($asOf !== null) {
            try {
                $t = \Carbon\Carbon::parse($asOf);
            } catch (\Throwable $e) {
                $t = null;
            }

            if ($t) {
                $query->where(function ($q) use ($t) {
                    $q->where(function ($qq) use ($t) {
                        $qq->whereNotNull('effective_at')
                            ->where('effective_at', '<=', $t);
                    })->orWhere(function ($qq) use ($t) {
                        $qq->whereNull('effective_at')
                            ->where('created_at', '<=', $t);
                    });
                });
            }
        }

        return $query
            ->orderByRaw('COALESCE(effective_at, created_at) DESC')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function getHppRowAsOf(int $branchId, int $productId, $asOf): ?ProductHpp
    {
        return $this->orderedLedgerQuery($branchId, $productId, $asOf)
            ->first();
    }

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
        int $incomingDamagedQty = 0,
        $effectiveAt = null,               // ✅ NEW (optional)
        ?string $sourceType = null,         // ✅ NEW (optional)
        ?int $sourceId = null               // ✅ NEW (optional)
    ): void {
        $incomingQty = max(0, (int) $incomingQty);
        if ($incomingQty <= 0) {
            return;
        }

        $unitCost = (float) $incomingUnitCost;
        if ($unitCost < 0) $unitCost = 0;

        // effectiveAt fallback: now()
        try {
            $eff = $effectiveAt ? \Carbon\Carbon::parse($effectiveAt) : now();
        } catch (\Throwable $e) {
            $eff = now();
        }

        DB::transaction(function () use (
            $branchId,
            $productId,
            $incomingQty,
            $unitCost,
            $eff,
            $sourceType,
            $sourceId
        ) {
            /**
             * ✅ Ambil HPP snapshot terakhir yang berlaku <= effective_at
             * (kalau tidak ada, fallback latest)
             */
            $prev = $this->orderedLedgerQuery($branchId, $productId, $eff)
                ->lockForUpdate()
                ->first();

            $oldAvg = $prev ? (float) $prev->avg_cost : 0.0;

            /**
             * Ambil stok saat ini (setelah mutation masuk dibuat).
             * Karena receiving sudah menambah stock_racks, maka totalAfter sudah termasuk incoming.
             * oldQty sebelum incoming => oldQty = totalAfter - incomingQty.
             */
            $stockAgg = DB::table('stock_racks')
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->selectRaw('
                    COALESCE(SUM(qty_good), 0) as sum_good,
                    COALESCE(SUM(qty_defect), 0) as sum_defect,
                    COALESCE(SUM(qty_damaged), 0) as sum_damaged,
                    COALESCE(SUM(qty_total), 0) as sum_available
                ')
                ->first();

            $totalAfter = 0;
            if ($stockAgg) {
                $totalAfter = (int) (($stockAgg->sum_good ?? 0) + ($stockAgg->sum_defect ?? 0) + ($stockAgg->sum_damaged ?? 0));

                // fallback kalau kolom kualitas belum populated
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

            $newAvg = round($newAvg, 2);

            /**
             * ✅ LEDGER: INSERT ROW BARU (append-only)
             * avg_cost = snapshot terbaru
             * last_purchase_cost = unit cost terakhir
             */
            ProductHpp::create([
                'branch_id'          => $branchId,
                'product_id'         => $productId,

                'effective_at'       => $eff,
                'source_type'        => $sourceType,
                'source_id'          => $sourceId,

                'avg_cost'           => $newAvg,
                'last_purchase_cost' => round($unitCost, 2),

                'incoming_qty'       => (int) $incomingQty,
                'incoming_unit_cost' => round($unitCost, 2),

                'old_qty'            => (int) $oldQty,
                'old_avg_cost'       => round($oldAvg, 2),
                'new_avg_cost'       => $newAvg,
            ]);
        }, 5);
    }

    /**
     * Ambil HPP saat ini untuk product pada branch.
     * Kalau belum ada row, return 0.
     */
    public function getCurrentHpp(int $branchId, int $productId): float
    {
        $row = $this->orderedLedgerQuery($branchId, $productId)
            ->first(['avg_cost']);

        return (float) ($row?->avg_cost ?? 0);
    }

    public function getHppAsOf(int $branchId, int $productId, $asOf): float
    {
        $row = $this->orderedLedgerQuery($branchId, $productId, $asOf)
            ->first(['avg_cost']);

        return (float) ($row?->avg_cost ?? 0);
    }
}

<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Modules\Product\Entities\ProductHpp;

class HppCorrectionService
{
    /**
     * Apply correction entry to product_hpps ledger for a set of product changes.
     *
     * Strategy (pragmatic & safe):
     * - We DO NOT rebuild full historical ledger (too heavy and risky).
     * - We append a correction row effective at end-of-day of purchase date,
     *   so sales on the same day can refetch correct cost snapshot.
     * - We adjust avg_cost using an approximation based on remaining qty from the corrected incoming batch.
     *
     * @param int $branchId
     * @param int $purchaseId
     * @param string $purchaseDate (Y-m-d)
     * @param int|null $purchaseDeliveryId
     * @param array $changes keyed by product_id:
     *        [
     *          product_id => [
     *              'old_unit_cost' => float,
     *              'new_unit_cost' => float
     *          ],
     *        ]
     * @return array summary
     */
    public function applyPurchasePriceCorrection(
        int $branchId,
        int $purchaseId,
        string $purchaseDate,
        ?int $purchaseDeliveryId,
        array $changes
    ): array {
        $hppService = new HppService();

        // effectiveAt = end-of-day purchase date
        try {
            $effectiveAt = \Carbon\Carbon::parse($purchaseDate)->setTime(23, 59, 59);
        } catch (\Throwable $e) {
            $effectiveAt = now();
        }

        $summary = [
            'corrected' => [],
            'skipped'   => [],
        ];

        foreach ($changes as $productId => $row) {
            $productId = (int) $productId;

            $oldCost = (float) ($row['old_unit_cost'] ?? 0);
            $newCost = (float) ($row['new_unit_cost'] ?? 0);

            if (round($oldCost, 2) === round($newCost, 2)) {
                $summary['skipped'][] = [
                    'product_id' => $productId,
                    'reason'     => 'No cost change',
                ];
                continue;
            }

            $delta = $newCost - $oldCost;

            // total onhand (approx) from stock_racks
            $stockAgg = DB::table('stock_racks')
                ->where('branch_id', (int) $branchId)
                ->where('product_id', (int) $productId)
                ->selectRaw('
                    COALESCE(SUM(qty_good), 0) as sum_good,
                    COALESCE(SUM(qty_defect), 0) as sum_defect,
                    COALESCE(SUM(qty_damaged), 0) as sum_damaged,
                    COALESCE(SUM(qty_available), 0) as sum_available
                ')
                ->first();

            $totalOnHand = 0;
            if ($stockAgg) {
                $totalOnHand = (int) (($stockAgg->sum_good ?? 0) + ($stockAgg->sum_defect ?? 0) + ($stockAgg->sum_damaged ?? 0));
                if ($totalOnHand <= 0) {
                    $totalOnHand = (int) ($stockAgg->sum_available ?? 0);
                }
            }

            // incoming qty from PD confirm (if exists)
            $incomingQty = 0;
            $soldSince   = 0;

            if (!empty($purchaseDeliveryId)) {
                $incoming = DB::table('purchase_delivery_details')
                    ->where('purchase_delivery_id', (int) $purchaseDeliveryId)
                    ->where('product_id', (int) $productId)
                    ->selectRaw('
                        COALESCE(SUM(qty_received), 0) as rcv,
                        COALESCE(SUM(qty_defect), 0) as def,
                        COALESCE(SUM(qty_damaged), 0) as dmg,
                        COALESCE(SUM(quantity), 0) as qty
                    ')
                    ->first();

                if ($incoming) {
                    $incomingQty = (int) (($incoming->rcv ?? 0) + ($incoming->def ?? 0) + ($incoming->dmg ?? 0));

                    // fallback jika sistem lama belum isi qty_received/defect/damaged
                    if ($incomingQty <= 0) {
                        $incomingQty = (int) ($incoming->qty ?? 0);
                    }
                }

                // sold qty since purchase date (approx)
                // We only use this to estimate remaining qty that is still on hand from that incoming batch.
                $soldSince = (int) DB::table('sale_details as sd')
                    ->join('sales as s', 's.id', '=', 'sd.sale_id')
                    ->whereNull('s.deleted_at')
                    ->where('s.branch_id', (int) $branchId)
                    ->where('sd.product_id', (int) $productId)
                    ->whereDate('s.date', '>=', $purchaseDate)
                    ->selectRaw('COALESCE(SUM(sd.quantity), 0) as sum_qty')
                    ->value('sum_qty');
            }

            $remainingApprox = max(0, (int) $incomingQty - (int) $soldSince);

            // current avg from ledger latest
            $currentAvg = (float) $hppService->getCurrentHpp((int) $branchId, (int) $productId);

            // if no onhand or no remaining, we still create a correction row but keep avg as-is,
            // while updating last_purchase_cost to newCost (so future receiving sees correct last cost).
            $newAvg = $currentAvg;

            if ($totalOnHand > 0 && $remainingApprox > 0) {
                $newAvg = $currentAvg + (($delta * $remainingApprox) / $totalOnHand);
            }

            $newAvg = round((float) $newAvg, 2);

            ProductHpp::create([
                'branch_id'          => (int) $branchId,
                'product_id'         => (int) $productId,

                'effective_at'       => $effectiveAt,
                'source_type'        => 'purchase_price_correction',
                'source_id'          => (int) $purchaseId,

                'avg_cost'           => $newAvg,
                'last_purchase_cost' => round((float) $newCost, 2),

                'incoming_qty'       => 0,
                'incoming_unit_cost' => round((float) $newCost, 2),

                'old_qty'            => (int) $totalOnHand,
                'old_avg_cost'       => round((float) $currentAvg, 2),
                'new_avg_cost'       => (float) $newAvg,
            ]);

            $summary['corrected'][] = [
                'product_id'        => $productId,
                'old_unit_cost'     => round($oldCost, 2),
                'new_unit_cost'     => round($newCost, 2),
                'delta'             => round($delta, 2),
                'total_onhand'      => $totalOnHand,
                'incoming_qty'      => $incomingQty,
                'sold_since'        => $soldSince,
                'remaining_approx'  => $remainingApprox,
                'old_avg'           => round($currentAvg, 2),
                'new_avg'           => $newAvg,
                'effective_at'      => $effectiveAt->toDateTimeString(),
            ];
        }

        return $summary;
    }

    /**
     * Update sale_details.product_cost for sales on the same day as purchase date.
     * This is the "snapshot fix" so profit calculation becomes correct.
     */
    public function refreshSaleCostSnapshotSameDay(int $branchId, string $purchaseDate, array $productIds): int
    {
        $hppService = new HppService();

        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (empty($productIds)) return 0;

        $sales = DB::table('sales')
            ->whereNull('deleted_at')
            ->where('branch_id', (int) $branchId)
            ->whereDate('date', '=', $purchaseDate)
            ->pluck('id')
            ->toArray();

        if (empty($sales)) return 0;

        $updated = 0;

        foreach ($productIds as $pid) {
            $newCost = (float) $hppService->getHppAsOf((int) $branchId, (int) $pid, $purchaseDate . ' 23:59:59');

            $count = DB::table('sale_details')
                ->whereIn('sale_id', $sales)
                ->where('product_id', (int) $pid)
                ->update([
                    'product_cost' => round($newCost, 2),
                    'updated_at'   => now(),
                ]);

            $updated += (int) $count;
        }

        return $updated;
    }
}
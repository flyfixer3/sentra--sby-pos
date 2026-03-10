<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Modules\Product\Entities\ProductHpp;

class HppCorrectionService
{
    private function getChronologicalIncomingLedgerRows(int $branchId, int $productId)
    {
        return ProductHpp::query()
            ->where('branch_id', (int) $branchId)
            ->where('product_id', (int) $productId)
            ->where('incoming_qty', '>', 0)
            ->orderByRaw('COALESCE(effective_at, created_at) ASC')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    private function replayCorrectedAverageFromAffectedIncomingRows(
        int $branchId,
        int $productId,
        ?int $purchaseDeliveryId,
        float $newCost
    ): ?array {
        if (empty($purchaseDeliveryId)) {
            return null;
        }

        $rows = $this->getChronologicalIncomingLedgerRows($branchId, $productId);
        if ($rows->isEmpty()) {
            return null;
        }

        $sourceType = \Modules\PurchaseDelivery\Entities\PurchaseDelivery::class;

        $affectedRows = $rows->filter(function ($row) use ($purchaseDeliveryId, $sourceType) {
            return (string) ($row->source_type ?? '') === $sourceType
                && (int) ($row->source_id ?? 0) === (int) $purchaseDeliveryId;
        })->values();

        if ($affectedRows->isEmpty()) {
            return null;
        }

        $firstAffectedId = (int) $affectedRows->first()->id;
        $runningAvg = round((float) ($affectedRows->first()->old_avg_cost ?? 0), 2);
        $processed = 0;

        foreach ($rows as $row) {
            if ((int) $row->id < $firstAffectedId) {
                continue;
            }

            $incomingQty = max(0, (int) ($row->incoming_qty ?? 0));
            $oldQty = max(0, (int) ($row->old_qty ?? 0));

            if ($incomingQty <= 0) {
                continue;
            }

            $unitCost = (float) ($row->incoming_unit_cost ?? 0);
            if (
                (string) ($row->source_type ?? '') === $sourceType
                && (int) ($row->source_id ?? 0) === (int) $purchaseDeliveryId
            ) {
                $unitCost = (float) $newCost;
            }

            $denom = $oldQty + $incomingQty;

            if ($denom <= 0) {
                $runningAvg = 0.0;
            } elseif ($oldQty <= 0) {
                $runningAvg = round($unitCost, 2);
            } else {
                $runningAvg = round((($runningAvg * $oldQty) + ($unitCost * $incomingQty)) / $denom, 2);
            }

            $processed++;
        }

        return [
            'replayed_avg' => round((float) $runningAvg, 2),
            'affected_rows' => (int) $affectedRows->count(),
            'processed_rows' => (int) $processed,
            'first_affected_effective_at' => (string) optional($affectedRows->first()->effective_at)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Apply correction entry to product_hpps ledger for a set of product changes.
     *
     * Strategy:
     * - Keep ledger append-only.
     * - Recompute the resulting average by replaying actual incoming HPP rows
     *   from the affected Purchase Delivery source forward for the same product/branch.
     * - Then append one correction snapshot row with the corrected resulting avg_cost.
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

            // current onhand for audit metadata on the appended correction row
            $stockAgg = DB::table('stock_racks')
                ->where('branch_id', (int) $branchId)
                ->where('product_id', (int) $productId)
                ->selectRaw('
                    COALESCE(SUM(qty_good), 0) as sum_good,
                    COALESCE(SUM(qty_defect), 0) as sum_defect,
                    COALESCE(SUM(qty_damaged), 0) as sum_damaged,
                    COALESCE(SUM(qty_total), 0) as sum_available
                ')
                ->first();

            $totalOnHand = 0;
            if ($stockAgg) {
                $totalOnHand = (int) (($stockAgg->sum_good ?? 0) + ($stockAgg->sum_defect ?? 0) + ($stockAgg->sum_damaged ?? 0));
                if ($totalOnHand <= 0) {
                    $totalOnHand = (int) ($stockAgg->sum_available ?? 0);
                }
            }

            // old_avg_cost on appended correction row should reflect the current
            // latest ledger snapshot before this correction row is inserted.
            $currentAvg = (float) $hppService->getHppAsOf((int) $branchId, (int) $productId, $effectiveAt);

            $replayed = $this->replayCorrectedAverageFromAffectedIncomingRows(
                (int) $branchId,
                (int) $productId,
                $purchaseDeliveryId ? (int) $purchaseDeliveryId : null,
                (float) $newCost
            );

            if (!$replayed) {
                $summary['skipped'][] = [
                    'product_id' => $productId,
                    'reason'     => 'No matching incoming HPP ledger row found for the related Purchase Delivery.',
                ];
                continue;
            }

            $newAvg = round((float) ($replayed['replayed_avg'] ?? $currentAvg), 2);

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
                'total_onhand'      => $totalOnHand,
                'affected_rows'     => (int) ($replayed['affected_rows'] ?? 0),
                'processed_rows'    => (int) ($replayed['processed_rows'] ?? 0),
                'replay_started_at' => (string) ($replayed['first_affected_effective_at'] ?? ''),
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

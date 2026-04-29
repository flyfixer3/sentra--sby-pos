<?php

namespace Modules\Quotation\Services;

use Illuminate\Support\Facades\DB;
use Modules\Quotation\Entities\Quotation;
use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\SaleOrder\Entities\SaleOrder;

class QuotationStatusService
{
    /**
     * Sync status quotation berdasarkan turunan aktif (SO/SD yang belum soft deleted).
     *
     * Rule:
     * - Kalau ada SaleOrder aktif atau SaleDelivery aktif -> status = "Completed"
     * - Kalau tidak ada sama sekali -> status = "Pending"
     */
    public static function sync(int $quotationId): void
    {
        // Kalau quotation sudah soft-deleted, kita skip supaya tidak "menghidupkan" status record yang sudah dibuang.
        $quotation = Quotation::query()
            ->withTrashed()
            ->where('id', $quotationId)
            ->first();

        if (!$quotation) {
            return;
        }

        if (!empty($quotation->deleted_at)) {
            return;
        }

        $hasActiveSaleOrder = SaleOrder::query()
            ->where('quotation_id', $quotationId)
            ->whereNull('deleted_at')
            ->exists();

        $hasActiveSaleDelivery = SaleDelivery::query()
            ->where('quotation_id', $quotationId)
            ->whereNull('deleted_at')
            ->exists();

        $newStatus = ($hasActiveSaleOrder || $hasActiveSaleDelivery) ? 'completed' : 'pending';

        // Hindari update kalau status sudah sama (biar tidak spam updated_at)
        $currentStatus = strtolower(trim((string) ($quotation->status ?? '')));
        if ($currentStatus === 'sent') {
            $currentStatus = 'completed';
        }

        if ($currentStatus !== $newStatus) {
            $quotation->update([
                'status' => $newStatus,
            ]);
        }
    }

    /**
     * Helper untuk cek apakah quotation punya turunan aktif (SO/SD belum soft delete).
     * Kalau true -> quotation tidak boleh didelete.
     */
    public static function hasActiveDescendant(int $quotationId): bool
    {
        $hasActiveSaleOrder = SaleOrder::query()
            ->where('quotation_id', $quotationId)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasActiveSaleOrder) {
            return true;
        }

        $hasActiveSaleDelivery = SaleDelivery::query()
            ->where('quotation_id', $quotationId)
            ->whereNull('deleted_at')
            ->exists();

        return $hasActiveSaleDelivery;
    }
}

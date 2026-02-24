<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;
use Modules\Mutation\Http\Controllers\MutationController;

use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Modules\PurchaseDelivery\Entities\PurchaseDeliveryDetails;

class StockQualityController extends Controller
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    /**
     * Resolve rack_id for defect/damaged item.
     * Primary: item.rack_id (new schema)
     * Fallback: legacy source reference (old data)
     */
    private function resolveRackId(object $item): ?int
    {
        // 1) NEW: directly from item
        $direct = (int) ($item->rack_id ?? 0);
        if ($direct > 0) {
            return $direct;
        }

        // 2) LEGACY fallback
        return $this->resolveRackIdFromSource($item);
    }

    /**
     * LEGACY resolver (for old records that don't have rack_id yet)
     * Source: PurchaseDeliveryDetails.rack_id by reference_id & product_id
     *
     * NOTE: not reliable for multi-rack scenarios, only for older data.
     */
    private function resolveRackIdFromSource(object $item): ?int
    {
        $refType   = (string) ($item->reference_type ?? '');
        $refId     = (int) ($item->reference_id ?? 0);
        $productId = (int) ($item->product_id ?? 0);

        if ($refId <= 0 || $productId <= 0) return null;

        if ($refType === PurchaseDelivery::class) {
            $pdDetail = PurchaseDeliveryDetails::withoutGlobalScopes()
                ->where('purchase_delivery_id', $refId)
                ->where('product_id', $productId)
                ->orderByDesc('id')
                ->first();

            $rackId = (int) ($pdDetail->rack_id ?? 0);
            return $rackId > 0 ? $rackId : null;
        }

        return null;
    }

    public function deleteDefect(int $id)
    {
        abort_if(Gate::denies('delete_inventory'), 403);

        $item = ProductDefectItem::withoutGlobalScopes()->findOrFail($id);

        // ✅ blok biar gak double sold
        if (!empty($item->moved_out_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Defect item already moved out.'
            ], 422);
        }

        DB::transaction(function () use ($item) {

            $qty = (int) ($item->quantity ?? 0);
            if ($qty <= 0) $qty = 1;

            $rackId = $this->resolveRackId($item); // prefer item.rack_id, fallback legacy

            $ref  = 'DEF-SOLD-' . $item->id;
            $note = "Defect SOLD | defect_item_id={$item->id}";

            // ✅ SINGLE SOURCE OF TRUTH:
            // MutationController akan:
            // - create/merge mutation log (summary per rack)
            // - update stocks.qty_available (header)
            // - update stock_racks.qty_available + bucket (defect)
            $this->mutationController->applyInOut(
                (int) $item->branch_id,
                (int) $item->warehouse_id,
                (int) $item->product_id,
                'Out',
                $qty,
                $ref,
                $note,
                now()->toDateString(),
                $rackId,        // keep per-rack correctness
                'defect',       // ✅ bucket
                'summary'       // ✅ merge log per rack
            );

            // ✅ soft delete bisnis (move out marker)
            $item->moved_out_at = now();
            $item->moved_out_by = auth()->id();
            $item->moved_out_reference_type = 'sold';
            $item->moved_out_reference_id = null; // kalau ada invoice/sale id, isi di sini
            $item->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Defect item moved out (sold) & stock updated'
        ]);
    }

    public function deleteDamaged(int $id)
    {
        abort_if(Gate::denies('delete_inventory'), 403);

        $item = ProductDamagedItem::withoutGlobalScopes()->findOrFail($id);

        if (!empty($item->moved_out_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Damaged item already moved out.'
            ], 422);
        }

        DB::transaction(function () use ($item) {

            $qty = (int) ($item->quantity ?? 0);
            if ($qty <= 0) $qty = 1;

            $rackId = $this->resolveRackId($item);

            $ref  = 'DMG-SOLD-' . $item->id;
            $note = "Damaged SOLD | damaged_item_id={$item->id}";

            // ✅ SINGLE SOURCE OF TRUTH (MutationController handle stocks + stock_racks)
            $this->mutationController->applyInOut(
                (int) $item->branch_id,
                (int) $item->warehouse_id,
                (int) $item->product_id,
                'Out',
                $qty,
                $ref,
                $note,
                now()->toDateString(),
                $rackId,
                'damaged',      // ✅ bucket
                'summary'       // ✅ merge log per rack
            );

            $item->moved_out_at = now();
            $item->moved_out_by = auth()->id();
            $item->moved_out_reference_type = 'sold';
            $item->moved_out_reference_id = null;
            $item->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Damaged item moved out (sold) & stock updated'
        ]);
    }

}

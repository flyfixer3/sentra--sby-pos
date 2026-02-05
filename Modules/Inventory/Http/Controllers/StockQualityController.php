<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;
use Modules\Mutation\Http\Controllers\MutationController;

use Modules\Inventory\Entities\StockRack;
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
     * Try resolve rack_id for defect/damaged item.
     * For now, only reliable source: PurchaseDelivery detail (because we store rack_id in PD details).
     */
    private function resolveRackIdFromSource(object $item): ?int
    {
        $refType = (string) ($item->reference_type ?? '');
        $refId   = (int) ($item->reference_id ?? 0);
        $productId = (int) ($item->product_id ?? 0);

        if ($refId <= 0 || $productId <= 0) return null;

        // Source: PurchaseDelivery
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

    private function decreaseStockRack(
        int $branchId,
        int $warehouseId,
        int $rackId,
        int $productId,
        int $qty,
        string $qualityColumn // 'qty_defect' or 'qty_damaged' (or 'qty_good' later)
    ): void {
        if ($qty <= 0) return;

        $row = StockRack::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('rack_id', $rackId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            // kalau gak ada row-nya, ya skip aja (jangan bikin minus / create baru)
            return;
        }

        $row->qty_available = (int) ($row->qty_available ?? 0);
        $row->qty_good      = (int) ($row->qty_good ?? 0);
        $row->qty_defect    = (int) ($row->qty_defect ?? 0);
        $row->qty_damaged   = (int) ($row->qty_damaged ?? 0);

        // total always decreases
        $row->qty_available -= $qty;
        if ($row->qty_available < 0) $row->qty_available = 0;

        // decrease specific quality bucket
        if (in_array($qualityColumn, ['qty_good', 'qty_defect', 'qty_damaged'], true)) {
            $row->{$qualityColumn} = (int) ($row->{$qualityColumn} ?? 0);
            $row->{$qualityColumn} -= $qty;
            if ($row->{$qualityColumn} < 0) $row->{$qualityColumn} = 0;
        }

        $row->updated_by = auth()->id();
        $row->save();
    }

    /**
     * HARD DELETE defect item (karena KEJUAL) + STOCK OUT
     */
    public function deleteDefect(int $id)
    {
        abort_if(Gate::denies('delete_inventory'), 403);

        $item = ProductDefectItem::withoutGlobalScopes()->findOrFail($id);

        DB::transaction(function () use ($item) {

            $qty = (int) ($item->quantity ?? 0);
            if ($qty <= 0) $qty = 1;

            // 1) stok keluar dulu (karena item kejual) => stok global
            $ref  = 'DEF-SOLD-' . $item->id;
            $note = "Defect SOLD (hard delete) | defect_item_id={$item->id}";

            $this->mutationController->applyInOut(
                (int) $item->branch_id,
                (int) $item->warehouse_id,
                (int) $item->product_id,
                'Out',
                $qty,
                $ref,
                $note,
                now()->toDateString()
            );

            // 2) update stock_racks juga (kalau bisa resolve rack_id)
            $rackId = $this->resolveRackIdFromSource($item);
            if ($rackId) {
                $this->decreaseStockRack(
                    (int) $item->branch_id,
                    (int) $item->warehouse_id,
                    (int) $rackId,
                    (int) $item->product_id,
                    $qty,
                    'qty_defect'
                );
            }

            // 3) baru hapus record defect
            $item->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Defect item sold & deleted (stock OUT applied)'
        ]);
    }

    /**
     * HARD DELETE damaged item (karena KEJUAL) + STOCK OUT
     * CATATAN: ini hanya valid kalau DAMAGED memang masuk ke Total (jadi stoknya ada).
     */
    public function deleteDamaged(int $id)
    {
        abort_if(Gate::denies('delete_inventory'), 403);

        $item = ProductDamagedItem::withoutGlobalScopes()->findOrFail($id);

        DB::transaction(function () use ($item) {

            $qty = (int) ($item->quantity ?? 0);
            if ($qty <= 0) $qty = 1;

            // 1) stok keluar dulu (stok global)
            $ref  = 'DMG-SOLD-' . $item->id;
            $note = "Damaged SOLD (hard delete) | damaged_item_id={$item->id}";

            $this->mutationController->applyInOut(
                (int) $item->branch_id,
                (int) $item->warehouse_id,
                (int) $item->product_id,
                'Out',
                $qty,
                $ref,
                $note,
                now()->toDateString()
            );

            // 2) kurangi stock_racks juga (kalau bisa resolve rack_id)
            $rackId = $this->resolveRackIdFromSource($item);
            if ($rackId) {
                $this->decreaseStockRack(
                    (int) $item->branch_id,
                    (int) $item->warehouse_id,
                    (int) $rackId,
                    (int) $item->product_id,
                    $qty,
                    'qty_damaged'
                );
            }

            // 3) baru hapus
            $item->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Damaged item sold & deleted (stock OUT applied)'
        ]);
    }
}

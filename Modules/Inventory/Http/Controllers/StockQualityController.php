<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;
use Modules\Mutation\Http\Controllers\MutationController;

class StockQualityController extends Controller
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
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

            // 1) stok keluar dulu (karena item kejual)
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

            // 2) baru hapus record defect
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

            // 1) stok keluar dulu (karena item kejual)
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

            // 2) baru hapus record damaged
            $item->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Damaged item sold & deleted (stock OUT applied)'
        ]);
    }
}

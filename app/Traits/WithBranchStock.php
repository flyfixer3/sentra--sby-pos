<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait WithBranchStock
{
    /**
     * Tambahkan kolom agregat 'stock_on_hand' (sum qty_available) untuk cabang aktif.
     * Pemakaian:
     *   Product::query()->withBranchStock()->get();
     *   Product::query()->withBranchStock($branchId)->having('stock_on_hand', '>', 0)->get();
     */
    public function scopeWithBranchStock(Builder $query, ?int $branchId = null): Builder
    {
        $branchId = $branchId ?? session('active_branch');

        // Nama relasi: stockRacks. Kolom qty: qty_available (sesuaikan kalau di schema kamu pakai 'qty')
        return $query->withSum(['stockRacks as stock_on_hand' => function ($q) use ($branchId) {
            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
        }], 'qty_available');
    }
}

<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Entities\Stock;
use Modules\Inventory\Entities\StockRack;

/**
 * StockService
 *
 * Kelas ini menangani semua logika stok:
 * - menambah / mengurangi stok gudang
 * - menambah / mengurangi stok per rak
 * - transfer antar rak
 * Semua proses dijalankan lewat transaksi DB agar aman dari error parsial.
 */
class StockService
{
    /**
     * Ambil atau buat data stok level GUDANG (tabel stocks)
     */
    public function getOrCreateStock(int $productId, ?int $branchId, ?int $warehouseId): Stock
    {
        return Stock::firstOrCreate([
            'product_id'   => $productId,
            'branch_id'    => $branchId,
            'warehouse_id' => $warehouseId,
        ]);
    }

    /**
     * Ambil atau buat data stok level RAK (tabel stock_racks)
     */
    public function getOrCreateStockRack(int $productId, int $rackId, int $warehouseId, int $branchId): StockRack
    {
        return StockRack::firstOrCreate([
            'product_id'   => $productId,
            'rack_id'      => $rackId,
            'warehouse_id' => $warehouseId,
            'branch_id'    => $branchId,
        ]);
    }

    /**
     * Penyesuaian stok level gudang
     * (dipakai untuk mutasi tanpa rack_id)
     */
    public function adjust(int $productId, ?int $branchId, ?int $warehouseId, int $qty, string $direction = 'in'): Stock
    {
        return DB::transaction(function () use ($productId, $branchId, $warehouseId, $qty, $direction) {
            $row = $this->getOrCreateStock($productId, $branchId, $warehouseId);

            if ($direction === 'in') {
                $row->qty_available += $qty;
            } else {
                $row->qty_available -= $qty;
            }

            if ($row->qty_available < 0) {
                $row->qty_available = 0;
            }

            $row->save();
            return $row;
        });
    }

    /**
     * Penyesuaian stok level RAK
     * + otomatis sinkronkan stok gudang
     */
    public function adjustRack(int $productId, int $branchId, int $warehouseId, int $rackId, int $qty, string $direction = 'in'): StockRack
    {
        return DB::transaction(function () use ($productId, $branchId, $warehouseId, $rackId, $qty, $direction) {
            $row = $this->getOrCreateStockRack($productId, $rackId, $warehouseId, $branchId);

            if ($direction === 'in') {
                $row->qty_available += $qty;
                $this->adjust($productId, $branchId, $warehouseId, $qty, 'in');
            } else {
                $row->qty_available -= $qty;
                if ($row->qty_available < 0) {
                    $row->qty_available = 0;
                }
                $this->adjust($productId, $branchId, $warehouseId, $qty, 'out');
            }

            $row->save();
            return $row;
        });
    }

    /**
     * Transfer antar rak dalam gudang yang sama.
     * Tidak mengubah stok total gudang.
     */
    public function transferBetweenRacks(int $productId, int $branchId, int $warehouseId, int $fromRackId, int $toRackId, int $qty): void
    {
        DB::transaction(function () use ($productId, $branchId, $warehouseId, $fromRackId, $toRackId, $qty) {
            // Kurangi stok di rak asal
            $from = $this->getOrCreateStockRack($productId, $fromRackId, $warehouseId, $branchId);
            $from->qty_available -= $qty;
            if ($from->qty_available < 0) $from->qty_available = 0;
            $from->save();

            // Tambah stok di rak tujuan
            $to = $this->getOrCreateStockRack($productId, $toRackId, $warehouseId, $branchId);
            $to->qty_available += $qty;
            $to->save();

            // Tidak mengubah stok di tabel "stocks" karena masih di warehouse yang sama
        });
    }
}

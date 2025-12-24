<?php

namespace Modules\Inventory\Observers;

use Modules\Inventory\Services\StockService;

/**
 * Class MutationObserver
 *
 * Observer ini memantau setiap event eloquent.created pada model Mutation.
 * Saat ada transaksi baru (sales, purchase, transfer, adjustment),
 * observer akan otomatis memanggil StockService untuk mengupdate stok
 * baik di level gudang (warehouse) maupun di level rak (rack).
 *
 * Semua logic update stok dipusatkan di StockService agar terisolasi
 * dan mudah diuji.
 */
class MutationObserver
{
    /**
     * Service utama untuk penyesuaian stok.
     *
     * @var StockService
     */
    protected StockService $service;

    /**
     * Inject StockService ke observer.
     *
     * @param StockService $service
     */
    public function __construct(StockService $service)
    {
        $this->service = $service;
    }

    /**
     * Method ini dijalankan setiap kali ada record Mutation baru dibuat.
     * Event-nya didaftarkan di InventoryServiceProvider melalui Event::listen().
     *
     * @param  mixed  $mutation
     * @return void
     */
    public function handleCreated($mutation): void
    {
        // Ambil nilai in/out dari mutasi
        $in  = (int) ($mutation->stock_in ?? 0);
        $out = (int) ($mutation->stock_out ?? 0);

        // Kalau tidak ada perubahan stok sama sekali, hentikan
        if ($in === 0 && $out === 0) {
            return;
        }

        // Ambil informasi utama
        $productId   = (int) $mutation->product_id;
        $branchId    = $mutation->branch_id ? (int) $mutation->branch_id : null;
        $warehouseId = $mutation->warehouse_id ? (int) $mutation->warehouse_id : null;
        $rackId      = $mutation->rack_id ? (int) $mutation->rack_id : null;

        // Tentukan arah mutasi: masuk atau keluar
        $direction = $in > 0 ? 'in' : 'out';
        $qty       = $in > 0 ? $in : $out;

        // Validasi produk
        if (!$productId) {
            logger()->warning('Mutation tanpa product_id dilewati', [
                'mutation_id' => $mutation->id ?? null,
            ]);
            return;
        }

        // Jika mutasi berhubungan dengan rak tertentu
        if ($rackId !== null) {
            // Jika branch atau warehouse tidak diketahui, fallback ke adjust umum
            if ($branchId === null || $warehouseId === null) {
                $this->service->adjust(
                    productId: $productId,
                    branchId: $branchId,
                    warehouseId: $warehouseId,
                    qty: $qty,
                    direction: $direction
                );
                return;
            }

            // Jalankan penyesuaian stok per rak (sekalian update stok gudang & total)
            try {
                $this->service->adjustRack(
                    productId: $productId,
                    branchId: $branchId,
                    warehouseId: $warehouseId,
                    rackId: $rackId,
                    qty: $qty,
                    direction: $direction
                );
            } catch (\Throwable $e) {
                logger()->error('Gagal adjust stok per rak', [
                    'mutation_id' => $mutation->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            return;
        }

        // Mutasi tanpa rack → update hanya stok di level gudang
        try {
            $this->service->adjust(
                productId: $productId,
                branchId: $branchId,
                warehouseId: $warehouseId,
                qty: $qty,
                direction: $direction
            );
        } catch (\Throwable $e) {
            logger()->error('Gagal adjust stok gudang', [
                'mutation_id' => $mutation->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

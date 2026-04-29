<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use Modules\Inventory\Entities\StockOpname;
use Modules\Inventory\Entities\StockOpnameItem;
use Modules\Inventory\Services\StockOpnameService;
use Modules\Product\Entities\Product;

class StockOpnameResultImport implements OnEachRow, WithHeadingRow
{
    private StockOpname $stockOpname;
    private StockOpnameService $service;
    private int $userId;
    private int $importedCount = 0;

    public function __construct(StockOpname $stockOpname, StockOpnameService $service, int $userId)
    {
        $this->stockOpname = $stockOpname;
        $this->service = $service;
        $this->userId = $userId;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function onRow(Row $row)
    {
        $excelRow = (int) $row->getIndex();
        $r = $row->toArray();

        $productCode = trim((string) ($r['product_code'] ?? ''));
        $physicalRaw = $r['physical_qty'] ?? null;
        $note = trim((string) ($r['note'] ?? ''));

        if ($productCode === '') {
            return;
        }

        if ($physicalRaw === null || $physicalRaw === '') {
            return;
        }

        if (!is_numeric($physicalRaw)) {
            throw new \RuntimeException("Row {$excelRow}: physical_qty harus berupa angka.");
        }

        $physicalQty = (int) $physicalRaw;
        if ($physicalQty < 0) {
            throw new \RuntimeException("Row {$excelRow}: physical_qty tidak boleh negatif.");
        }

        $product = Product::withoutGlobalScopes()
            ->where('product_code', $productCode)
            ->where('item_type', 'glass')
            ->first(['id', 'product_code', 'product_name']);

        if (!$product) {
            throw new \RuntimeException("Row {$excelRow}: product_code {$productCode} tidak ditemukan atau bukan item kaca.");
        }

        DB::transaction(function () use ($product, $physicalQty, $note) {
            $item = StockOpnameItem::query()
                ->where('stock_opname_id', $this->stockOpname->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                $snapshot = $this->service->buildSingleRow((int) $this->stockOpname->branch_id, (int) $product->id);
                if (!$snapshot) {
                    throw new \RuntimeException("Product {$product->product_code} tidak bisa dibuat sebagai row opname.");
                }

                $item = StockOpnameItem::query()->create([
                    'stock_opname_id' => (int) $this->stockOpname->id,
                    'product_id' => (int) $product->id,
                    'rack_id' => $snapshot['rack_id'],
                    'product_code_snapshot' => $snapshot['product_code_snapshot'],
                    'product_name_snapshot' => $snapshot['product_name_snapshot'],
                    'rack_code_snapshot' => $snapshot['rack_code_snapshot'],
                    'rack_name_snapshot' => $snapshot['rack_name_snapshot'],
                    'system_qty' => (int) $snapshot['system_qty'],
                ]);
            }

            $item->update([
                'physical_qty' => $physicalQty,
                'diff_qty' => $physicalQty - (int) $item->system_qty,
                'review_status' => 'pending',
                'resolution_type' => null,
                'resolution_reference' => null,
                'resolution_note' => null,
                'resolved_at' => null,
                'resolved_by' => null,
                'note' => $note !== '' ? $note : $item->note,
                'counted_at' => now(),
            ]);
        });

        $this->importedCount++;
    }
}

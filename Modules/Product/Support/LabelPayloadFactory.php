<?php

namespace Modules\Product\Support;

use Illuminate\Support\Facades\DB;
use Milon\Barcode\Facades\DNS1DFacade;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductDamagedItem;
use Modules\Product\Entities\ProductDefectItem;

class LabelPayloadFactory
{
    public function buildProductLabels(Product $product, int $quantity = 1, ?int $branchId = null): array
    {
        $quantity = max(1, min(100, $quantity));
        $location = $this->resolveGoodLocationHelper((int) $product->id, $branchId);
        $note = trim((string) ($product->product_note ?? ''));

        $base = [
            'title' => 'GOOD STOCK',
            'condition' => 'GOOD',
            'product_name' => (string) $product->product_name,
            'product_code' => (string) $product->product_code,
            'encoded_value' => (string) $product->product_code,
            'barcode_svg' => DNS1DFacade::getBarCodeSVG(
                (string) $product->product_code,
                (string) ($product->product_barcode_symbology ?: 'C128'),
                1.8,
                52,
                'black',
                false
            ),
            'details' => [
                'Branch' => $location['branch_name'],
                'Warehouse' => $location['warehouse_name'],
                'Rack' => $location['rack_name'],
                'Note' => $note !== '' ? $note : '-',
            ],
        ];

        $labels = [];
        for ($i = 0; $i < $quantity; $i++) {
            $labels[] = $base;
        }

        return $labels;
    }

    public function buildDefectLabel(ProductDefectItem $item): array
    {
        $item->loadMissing(['product', 'branch', 'warehouse', 'rack']);

        return [
            'title' => 'DEFECT STOCK',
            'condition' => 'DEFECT',
            'product_name' => (string) optional($item->product)->product_name,
            'product_code' => (string) optional($item->product)->product_code,
            'encoded_value' => 'D:' . $item->id,
            'barcode_svg' => DNS1DFacade::getBarCodeSVG('D:' . $item->id, 'C128', 1.8, 52, 'black', false),
            'details' => [
                'Defect ID' => 'D:' . $item->id,
                'Defect Type' => $item->defect_types_text ?: '-',
                'Branch' => optional($item->branch)->name ?? '-',
                'Warehouse' => optional($item->warehouse)->warehouse_name ?? '-',
                'Rack' => $this->formatRack(optional($item->rack)->code, optional($item->rack)->name),
                'Note' => trim((string) ($item->description ?? '')) ?: '-',
            ],
        ];
    }

    public function buildDamagedLabel(ProductDamagedItem $item): array
    {
        $item->loadMissing(['product', 'branch', 'warehouse', 'rack']);

        return [
            'title' => 'DAMAGED STOCK',
            'condition' => 'DAMAGED',
            'product_name' => (string) optional($item->product)->product_name,
            'product_code' => (string) optional($item->product)->product_code,
            'encoded_value' => 'M:' . $item->id,
            'barcode_svg' => DNS1DFacade::getBarCodeSVG('M:' . $item->id, 'C128', 1.8, 52, 'black', false),
            'details' => [
                'Damaged ID' => 'M:' . $item->id,
                'Damage Type' => trim((string) ($item->damage_type ?? 'damaged')) ?: 'damaged',
                'Branch' => optional($item->branch)->name ?? '-',
                'Warehouse' => optional($item->warehouse)->warehouse_name ?? '-',
                'Rack' => $this->formatRack(optional($item->rack)->code, optional($item->rack)->name),
                'Note' => trim((string) ($item->reason ?? '')) ?: '-',
            ],
        ];
    }

    private function resolveGoodLocationHelper(int $productId, ?int $branchId): array
    {
        $query = DB::table('stock_racks as sr')
            ->leftJoin('branches as b', 'b.id', '=', 'sr.branch_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->leftJoin('racks as r', 'r.id', '=', 'sr.rack_id')
            ->where('sr.product_id', $productId)
            ->whereRaw('COALESCE(sr.qty_total, 0) > 0');

        if (!empty($branchId)) {
            $query->where('sr.branch_id', (int) $branchId);
        }

        $rows = $query->orderBy('w.warehouse_name')
            ->orderBy('r.code')
            ->get([
                'b.name as branch_name',
                'w.warehouse_name',
                'r.code as rack_code',
                'r.name as rack_name',
            ]);

        if ($rows->isEmpty()) {
            return [
                'branch_name' => !empty($branchId) ? optional(Branch::find($branchId))->name ?? '-' : '-',
                'warehouse_name' => '-',
                'rack_name' => '-',
            ];
        }

        $branchNames = $rows->pluck('branch_name')->filter()->unique()->values()->all();
        $warehouseNames = $rows->pluck('warehouse_name')->filter()->unique()->values()->all();
        $rackNames = $rows->map(fn ($row) => $this->formatRack($row->rack_code, $row->rack_name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'branch_name' => $this->formatSummary($branchNames),
            'warehouse_name' => $this->formatSummary($warehouseNames),
            'rack_name' => $this->formatSummary($rackNames),
        ];
    }

    private function formatSummary(array $values): string
    {
        $values = array_values(array_filter(array_map(fn ($value) => trim((string) $value), $values)));
        if (empty($values)) {
            return '-';
        }

        if (count($values) === 1) {
            return $values[0];
        }

        $preview = implode(', ', array_slice($values, 0, 2));
        $remaining = count($values) - 2;

        return $remaining > 0 ? $preview . ' +' . $remaining . ' more' : $preview;
    }

    private function formatRack(?string $code, ?string $name): string
    {
        $code = trim((string) $code);
        $name = trim((string) $name);

        if ($code !== '' && $name !== '') {
            return $code . ' - ' . $name;
        }

        return $code !== '' ? $code : ($name !== '' ? $name : '-');
    }
}

<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Row;
use App\Support\BranchContext;
use Modules\Mutation\Http\Controllers\MutationController;

class OpeningStockImport implements OnEachRow, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    private int $userId;
    private string $date;
    private string $reference;
    private string $note;

    private MutationController $mutationController;

    public function __construct(int $userId, string $date, string $reference, string $note)
    {
        $this->userId = (int) $userId;
        $this->date = $date;
        $this->reference = $reference;
        $this->note = $note;

        $this->mutationController = app(MutationController::class);
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'min:1'],
            'warehouse_code' => ['required', 'string', 'max:255'],
            'rack_code' => ['required', 'string', 'max:50'],
            'product_code' => ['required', 'string', 'max:255'],
            'qty_good' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function onRow(Row $row)
    {
        $r = $row->toArray();

        $branchId = (int) ($r['branch_id'] ?? 0);
        $warehouseCode = trim((string)($r['warehouse_code'] ?? ''));
        $rackCode = trim((string)($r['rack_code'] ?? ''));
        $productCode = trim((string)($r['product_code'] ?? ''));

        $qtyGoodRaw = ($r['qty_good'] ?? 0);
        $qtyGood = (int) (($qtyGoodRaw === '' || $qtyGoodRaw === null) ? 0 : $qtyGoodRaw);

        if ($qtyGood <= 0) {
            return; // skip kalau qty 0
        }

        // Enforce active branch jika user sedang tidak "ALL"
        $active = BranchContext::id();
        if ($active !== 'all' && $active !== null && $active !== '') {
            if ((int)$active !== (int)$branchId) {
                throw new \RuntimeException("Active branch is {$active}, but file row branch_id is {$branchId}. Switch branch to ALL or the same branch before import.");
            }
        }

        DB::transaction(function () use ($branchId, $warehouseCode, $rackCode, $productCode, $qtyGood) {

            $warehouse = DB::table('warehouses')
                ->where('warehouse_code', $warehouseCode)
                ->first();

            if (!$warehouse) {
                throw new \RuntimeException("Warehouse code not found: {$warehouseCode}");
            }

            // warehouse branch must match
            if ((int)($warehouse->branch_id ?? 0) !== (int)$branchId) {
                throw new \RuntimeException("Warehouse {$warehouseCode} belongs to branch_id {$warehouse->branch_id}, but row branch_id is {$branchId}");
            }

            $rack = DB::table('racks')
                ->where('warehouse_id', (int)$warehouse->id)
                ->where('code', $rackCode)
                ->first();

            if (!$rack) {
                throw new \RuntimeException("Rack not found: warehouse_code={$warehouseCode}, rack_code={$rackCode}");
            }

            $product = DB::table('products')
                ->where('product_code', $productCode)
                ->first();

            if (!$product) {
                throw new \RuntimeException("Product code not found: {$productCode}");
            }

            // ✅ Opening Balance hanya GOOD (detail defect/damaged wajib lewat Adjustment/Quality Reclass)
            // mode = 'summary' biar per reference merge dan tidak kebanyakan row
            $this->mutationController->applyInOut(
                (int)$branchId,
                (int)$warehouse->id,
                (int)$product->id,
                'In',
                (int)$qtyGood,
                $this->reference,
                $this->note,
                $this->date,
                (int)$rack->id,
                'good',
                'summary'
            );
        });
    }
}

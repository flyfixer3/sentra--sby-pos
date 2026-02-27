<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Row;

class RacksImport implements OnEachRow, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = (int) $userId;
    }

    public function rules(): array
    {
        return [
            'warehouse_code' => ['required', 'string', 'max:255'],
            'branch_id(optional)' => ['nullable'],
            'rack_code' => ['required', 'string', 'max:50'],
            'rack_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function onRow(Row $row)
    {
        $r = $row->toArray();

        $warehouseCode = trim((string)($r['warehouse_code'] ?? ''));
        $rackCode = trim((string)($r['rack_code'] ?? ''));
        $rackName = trim((string)($r['rack_name'] ?? ''));
        $desc = trim((string)($r['description'] ?? ''));

        // heading row library akan ubah key jadi snake-case kadang,
        // tapi karena kita pakai "branch_id(optional)" di template,
        // key-nya di array akan jadi "branch_id(optional)" juga.
        $branchRaw = $r['branch_id(optional)'] ?? null;
        $branchId = ($branchRaw === '' || $branchRaw === null) ? null : (int) $branchRaw;

        DB::transaction(function () use ($warehouseCode, $rackCode, $rackName, $desc, $branchId) {

            $warehouse = DB::table('warehouses')->where('warehouse_code', $warehouseCode)->first();
            if (!$warehouse) {
                throw new \RuntimeException("Warehouse code not found: {$warehouseCode}");
            }

            $resolvedBranchId = $branchId;
            if (!$resolvedBranchId) {
                $resolvedBranchId = (int) ($warehouse->branch_id ?? 0);
            }
            if ($resolvedBranchId <= 0) {
                throw new \RuntimeException("branch_id is required (warehouse {$warehouseCode} has no branch_id and template branch is empty).");
            }

            // upsert by (warehouse_id + code)
            $existing = DB::table('racks')
                ->where('warehouse_id', (int)$warehouse->id)
                ->where('code', $rackCode)
                ->first();

            $payload = [
                'warehouse_id' => (int) $warehouse->id,
                'branch_id' => (int) $resolvedBranchId,
                'code' => $rackCode,
                'name' => ($rackName === '' ? null : $rackName),
                'description' => ($desc === '' ? null : $desc),
                'updated_by' => $this->userId,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('racks')->where('id', (int)$existing->id)->update($payload);
            } else {
                $payload['created_by'] = $this->userId;
                $payload['created_at'] = now();
                DB::table('racks')->insert($payload);
            }
        });
    }
}

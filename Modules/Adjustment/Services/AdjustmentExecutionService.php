<?php

namespace Modules\Adjustment\Services;

use App\Support\DefectTypeSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Adjustment\Entities\AdjustedProduct;
use Modules\Adjustment\Entities\Adjustment;
use Modules\Mutation\Http\Controllers\MutationController;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductDamagedItem;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\Warehouse;

class AdjustmentExecutionService
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    public function approve(Adjustment $adjustment, int $userId, ?string $approvalNote = null): Adjustment
    {
        return DB::transaction(function () use ($adjustment, $userId, $approvalNote) {
            $locked = Adjustment::query()
                ->where('id', (int) $adjustment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$locked->isPending()) {
                throw new \RuntimeException('Only pending adjustment requests can be approved.');
            }

            Auth::onceUsingId($userId);

            $requestType = (string) $locked->request_type;

            if ($requestType === 'stock_add') {
                $this->executeStockAdd($locked);
            } elseif ($requestType === 'stock_sub') {
                $this->executeStockSub($locked);
            } elseif (in_array($requestType, ['quality_good_to_defect', 'quality_good_to_damaged'], true)) {
                $this->executeQualityGoodToIssue($locked);
            } elseif (in_array($requestType, ['quality_defect_to_good', 'quality_damaged_to_good'], true)) {
                $this->executeQualityIssueToGood($locked);
            } else {
                throw new \RuntimeException("Unsupported adjustment request type: {$requestType}");
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_note' => $approvalNote,
                'executed_by' => $userId,
                'executed_at' => now(),
            ]);

            activity()
                ->performedOn($locked)
                ->causedBy(auth()->user())
                ->log('Approved adjustment request');

            return $locked->fresh(['requestItems', 'adjustedProducts']);
        }, 5);
    }

    private function executeStockAdd(Adjustment $adjustment): void
    {
        $payload = (array) ($adjustment->payload ?? []);
        $items = (array) ($payload['items'] ?? []);
        $branchId = (int) $adjustment->branch_id;
        $warehouseId = (int) $adjustment->warehouse_id;
        $date = $this->normalizeDate($payload['date'] ?? $adjustment->getRawOriginal('date'));
        $note = (string) ($adjustment->note ?? '');
        $reference = (string) $adjustment->reference;

        $this->assertWarehouseInBranch($warehouseId, $branchId);

        foreach ($items as $idx => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $good = (int) ($item['qty_good'] ?? 0);
            $defect = (int) ($item['qty_defect'] ?? 0);
            $damaged = (int) ($item['qty_damaged'] ?? 0);
            $total = $good + $defect + $damaged;

            if ($total <= 0) {
                continue;
            }

            $this->resolveAdjustmentProduct($productId, $branchId, (int) $idx + 1);

            $baseNote = trim(
                'Adjustment Add #' . (int) $adjustment->id
                . ($note !== '' ? ' | ' . $note : '')
            );

            $goodAllocations = (array) ($item['good_allocations'] ?? []);
            if ($good > 0) {
                $sumAlloc = 0;
                foreach ($goodAllocations as $ga) {
                    $gaQty = (int) ($ga['qty'] ?? 0);
                    $sumAlloc += $gaQty;
                    if ($gaQty > 0 && empty($ga['to_rack_id'])) {
                        throw new \RuntimeException("Line #" . ($idx + 1) . ': GOOD allocation rack is required.');
                    }
                }

                if ($sumAlloc !== $good) {
                    throw new \RuntimeException("Line #" . ($idx + 1) . ": GOOD allocation total ({$sumAlloc}) must equal GOOD ({$good}).");
                }

                foreach ($goodAllocations as $ga) {
                    $qty = (int) ($ga['qty'] ?? 0);
                    $rackId = (int) ($ga['to_rack_id'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }

                    $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

                    $this->mutationController->applyInOut(
                        $branchId,
                        $warehouseId,
                        $productId,
                        'In',
                        $qty,
                        $reference,
                        $baseNote . ' | GOOD',
                        $date,
                        $rackId,
                        'good',
                        'summary'
                    );

                    AdjustedProduct::query()->create([
                        'adjustment_id' => (int) $adjustment->id,
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'rack_id' => $rackId,
                        'quantity' => $qty,
                        'type' => 'add',
                        'note' => 'COND=GOOD',
                    ]);
                }
            }

            $defects = (array) ($item['defects'] ?? []);
            if ($defect > 0 && count($defects) !== $defect) {
                throw new \RuntimeException("Line #" . ($idx + 1) . ": Defect qty ({$defect}) does not match detail rows (" . count($defects) . ').');
            }

            if ($defect > 0) {
                $countByRack = [];
                foreach ($defects as $i => $detail) {
                    $rackId = (int) ($detail['to_rack_id'] ?? 0);
                    $defectTypes = DefectTypeSupport::extractFromPayload((array) $detail);
                    $desc = (string) ($detail['defect_description'] ?? $detail['description'] ?? '');

                    if ($rackId <= 0 || empty($defectTypes)) {
                        throw new \RuntimeException("Line #" . ($idx + 1) . ': defect detail #' . ($i + 1) . ' rack/type is required.');
                    }

                    $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

                    ProductDefectItem::query()->create([
                        'branch_id' => $branchId,
                        'warehouse_id' => $warehouseId,
                        'rack_id' => $rackId,
                        'product_id' => $productId,
                        'reference_id' => (int) $adjustment->id,
                        'reference_type' => Adjustment::class,
                        'quantity' => 1,
                        'defect_types' => !empty($defectTypes) ? $defectTypes : null,
                        'description' => trim($desc) !== '' ? $desc : null,
                        'photo_path' => $detail['photo_path'] ?? null,
                        'created_by' => (int) Auth::id(),
                    ]);

                    $countByRack[$rackId] = ($countByRack[$rackId] ?? 0) + 1;
                }

                foreach ($countByRack as $rackId => $qty) {
                    $this->mutationController->applyInOut(
                        $branchId,
                        $warehouseId,
                        $productId,
                        'In',
                        (int) $qty,
                        $reference,
                        $baseNote . ' | DEFECT',
                        $date,
                        (int) $rackId,
                        'defect',
                        'summary'
                    );

                    AdjustedProduct::query()->create([
                        'adjustment_id' => (int) $adjustment->id,
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'rack_id' => (int) $rackId,
                        'quantity' => (int) $qty,
                        'type' => 'add',
                        'note' => 'COND=DEFECT',
                    ]);
                }
            }

            $damages = (array) ($item['damaged_items'] ?? []);
            if ($damaged > 0 && count($damages) !== $damaged) {
                throw new \RuntimeException("Line #" . ($idx + 1) . ": Damaged qty ({$damaged}) does not match detail rows (" . count($damages) . ').');
            }

            if ($damaged > 0) {
                $rowsByRack = [];
                foreach ($damages as $i => $detail) {
                    $rackId = (int) ($detail['to_rack_id'] ?? 0);
                    $damageType = strtolower(trim((string) ($detail['damage_type'] ?? 'damaged')));
                    if (!in_array($damageType, ['damaged', 'missing'], true)) {
                        $damageType = 'damaged';
                    }
                    $reason = trim((string) ($detail['reason'] ?? ''));

                    if ($rackId <= 0 || $reason === '') {
                        throw new \RuntimeException("Line #" . ($idx + 1) . ': damaged detail #' . ($i + 1) . ' rack/reason is required.');
                    }

                    $this->assertRackBelongsToWarehouse($rackId, $warehouseId);

                    $row = ProductDamagedItem::query()->create([
                        'branch_id' => $branchId,
                        'warehouse_id' => $warehouseId,
                        'rack_id' => $rackId,
                        'product_id' => $productId,
                        'reference_id' => (int) $adjustment->id,
                        'reference_type' => Adjustment::class,
                        'quantity' => 1,
                        'damage_type' => $damageType,
                        'reason' => $reason,
                        'photo_path' => $detail['photo_path'] ?? null,
                        'cause' => null,
                        'responsible_user_id' => null,
                        'resolution_status' => 'pending',
                        'resolution_note' => null,
                        'mutation_in_id' => null,
                        'mutation_out_id' => null,
                        'created_by' => (int) Auth::id(),
                    ]);

                    $rowsByRack[$rackId][] = $row;
                }

                foreach ($rowsByRack as $rackId => $rows) {
                    $qty = count($rows);
                    $mutationInId = $this->mutationController->applyInOutAndGetMutationId(
                        $branchId,
                        $warehouseId,
                        $productId,
                        'In',
                        $qty,
                        $reference,
                        $baseNote . ' | DAMAGED',
                        $date,
                        (int) $rackId,
                        'damaged',
                        'summary'
                    );

                    foreach ($rows as $row) {
                        $row->update(['mutation_in_id' => (int) $mutationInId]);
                    }

                    AdjustedProduct::query()->create([
                        'adjustment_id' => (int) $adjustment->id,
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'rack_id' => (int) $rackId,
                        'quantity' => $qty,
                        'type' => 'add',
                        'note' => 'COND=DAMAGED',
                    ]);
                }
            }
        }
    }

    private function executeStockSub(Adjustment $adjustment): void
    {
        $payload = (array) ($adjustment->payload ?? []);
        $items = (array) ($payload['items'] ?? []);
        $branchId = (int) $adjustment->branch_id;
        $date = $this->normalizeDate($payload['date'] ?? $adjustment->getRawOriginal('date'));
        $reference = (string) $adjustment->reference;
        $headerNote = (string) ($adjustment->note ?? '');

        foreach ($items as $idx => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $expected = (int) ($item['qty'] ?? 0);
            $itemNote = html_entity_decode((string) ($item['note'] ?? ''), ENT_QUOTES, 'UTF-8');

            if ($productId <= 0 || $expected <= 0) {
                throw new \RuntimeException('Invalid stock SUB item at line #' . ($idx + 1));
            }
            if (trim($itemNote) === '') {
                throw new \RuntimeException('Note is required at line #' . ($idx + 1));
            }

            $this->resolveAdjustmentProduct($productId, $branchId, (int) $idx + 1);

            $goodGroups = [];
            $goodTotal = 0;
            foreach ((array) ($item['good_allocations'] ?? []) as $allocation) {
                $wid = (int) ($allocation['warehouse_id'] ?? 0);
                $rid = (int) ($allocation['from_rack_id'] ?? 0);
                $qty = (int) ($allocation['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $this->assertWarehouseInBranch($wid, $branchId);
                $this->assertRackBelongsToWarehouse($rid, $wid);
                $goodTotal += $qty;
                $key = $wid . '|' . $rid;
                $goodGroups[$key] = ($goodGroups[$key] ?? 0) + $qty;
            }

            $defIds = $this->normalizeIds($item['selected_defect_ids'] ?? ($item['defect_unit_ids'] ?? []));
            $damIds = $this->normalizeIds($item['selected_damaged_ids'] ?? ($item['damaged_unit_ids'] ?? []));
            $defTotal = count($defIds);
            $damTotal = count($damIds);
            $selected = $goodTotal + $defTotal + $damTotal;

            if ($selected !== $expected) {
                throw new \RuntimeException("Line #" . ($idx + 1) . ": selected qty changed. Expected={$expected}, Selected={$selected}.");
            }

            foreach ($goodGroups as $key => $qty) {
                [$wid, $rid] = explode('|', $key);
                $wid = (int) $wid;
                $rid = (int) $rid;
                $qty = (int) $qty;

                $mutationNote = trim(
                    'Adjustment Sub #' . (int) $adjustment->id
                    . ($headerNote ? ' | ' . $headerNote : '')
                    . ' | GOOD | ' . trim($itemNote)
                );

                AdjustedProduct::query()->create([
                    'adjustment_id' => (int) $adjustment->id,
                    'product_id' => $productId,
                    'warehouse_id' => $wid,
                    'rack_id' => $rid,
                    'quantity' => $qty,
                    'type' => 'sub',
                    'note' => 'COND=GOOD | ' . trim($itemNote),
                ]);

                $this->mutationController->applyInOut(
                    $branchId,
                    $wid,
                    $productId,
                    'Out',
                    $qty,
                    $reference,
                    $mutationNote,
                    $date,
                    $rid,
                    'good',
                    'summary'
                );
            }

            if ($defTotal > 0) {
                $rows = ProductDefectItem::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->whereIn('id', $defIds)
                    ->lockForUpdate()
                    ->get(['id', 'warehouse_id', 'rack_id']);

                if ($rows->count() !== $defTotal) {
                    throw new \RuntimeException('Line #' . ($idx + 1) . ": invalid DEFECT selection. Need={$defTotal}, Found={$rows->count()}.");
                }

                $groups = [];
                foreach ($rows as $row) {
                    $wid = (int) $row->warehouse_id;
                    $rid = (int) $row->rack_id;
                    $this->assertWarehouseInBranch($wid, $branchId);
                    $this->assertRackBelongsToWarehouse($rid, $wid);
                    $groups[$wid . '|' . $rid][] = (int) $row->id;
                }

                ProductDefectItem::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->whereIn('id', $defIds)
                    ->update([
                        'moved_out_at' => now(),
                        'moved_out_by' => (int) Auth::id(),
                        'moved_out_reference_type' => Adjustment::class,
                        'moved_out_reference_id' => (int) $adjustment->id,
                        'updated_at' => now(),
                    ]);

                foreach ($groups as $key => $ids) {
                    [$wid, $rid] = explode('|', $key);
                    $wid = (int) $wid;
                    $rid = (int) $rid;
                    $qty = count($ids);
                    $mutationNote = trim(
                        'Adjustment Sub #' . (int) $adjustment->id
                        . ($headerNote ? ' | ' . $headerNote : '')
                        . ' | DEFECT | ' . trim($itemNote)
                    );

                    AdjustedProduct::query()->create([
                        'adjustment_id' => (int) $adjustment->id,
                        'product_id' => $productId,
                        'warehouse_id' => $wid,
                        'rack_id' => $rid,
                        'quantity' => $qty,
                        'type' => 'sub',
                        'note' => 'COND=DEFECT | ' . trim($itemNote),
                    ]);

                    $this->mutationController->applyInOut($branchId, $wid, $productId, 'Out', $qty, $reference, $mutationNote, $date, $rid, 'defect', 'summary');
                }
            }

            if ($damTotal > 0) {
                $rows = ProductDamagedItem::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->whereIn('id', $damIds)
                    ->lockForUpdate()
                    ->get(['id', 'warehouse_id', 'rack_id']);

                if ($rows->count() !== $damTotal) {
                    throw new \RuntimeException('Line #' . ($idx + 1) . ": invalid DAMAGED selection. Need={$damTotal}, Found={$rows->count()}.");
                }

                $groups = [];
                foreach ($rows as $row) {
                    $wid = (int) $row->warehouse_id;
                    $rid = (int) $row->rack_id;
                    $this->assertWarehouseInBranch($wid, $branchId);
                    $this->assertRackBelongsToWarehouse($rid, $wid);
                    $groups[$wid . '|' . $rid][] = (int) $row->id;
                }

                ProductDamagedItem::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->whereIn('id', $damIds)
                    ->update([
                        'moved_out_at' => now(),
                        'moved_out_by' => (int) Auth::id(),
                        'moved_out_reference_type' => Adjustment::class,
                        'moved_out_reference_id' => (int) $adjustment->id,
                        'updated_by' => (int) Auth::id(),
                        'updated_at' => now(),
                    ]);

                foreach ($groups as $key => $ids) {
                    [$wid, $rid] = explode('|', $key);
                    $wid = (int) $wid;
                    $rid = (int) $rid;
                    $qty = count($ids);
                    $mutationNote = trim(
                        'Adjustment Sub #' . (int) $adjustment->id
                        . ($headerNote ? ' | ' . $headerNote : '')
                        . ' | DAMAGED | ' . trim($itemNote)
                    );

                    AdjustedProduct::query()->create([
                        'adjustment_id' => (int) $adjustment->id,
                        'product_id' => $productId,
                        'warehouse_id' => $wid,
                        'rack_id' => $rid,
                        'quantity' => $qty,
                        'type' => 'sub',
                        'note' => 'COND=DAMAGED | ' . trim($itemNote),
                    ]);

                    $mutationOutId = $this->mutationController->applyInOutAndGetMutationId($branchId, $wid, $productId, 'Out', $qty, $reference, $mutationNote, $date, $rid, 'damaged', 'summary');

                    ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->whereIn('id', $ids)
                        ->update([
                            'mutation_out_id' => (int) $mutationOutId,
                            'updated_by' => (int) Auth::id(),
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    private function executeQualityGoodToIssue(Adjustment $adjustment): void
    {
        $payload = (array) ($adjustment->payload ?? []);
        $type = $adjustment->request_type === 'quality_good_to_damaged' ? 'damaged' : 'defect';
        $items = (array) ($payload['items'] ?? []);
        $branchId = (int) $adjustment->branch_id;
        $warehouseId = (int) $adjustment->warehouse_id;
        $date = $this->normalizeDate($payload['date'] ?? $adjustment->getRawOriginal('date'));
        $globalNote = trim((string) ($payload['user_note'] ?? ''));
        $reference = (string) $adjustment->reference;

        $this->assertWarehouseInBranch($warehouseId, $branchId);

        foreach ($items as $idx => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $rackId = (int) ($item['rack_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            if ($productId <= 0 || $rackId <= 0 || $qty <= 0) {
                throw new \RuntimeException('Invalid quality item at line #' . ($idx + 1));
            }

            $this->assertRackBelongsToWarehouse($rackId, $warehouseId);
            $this->resolveAdjustmentProduct($productId, $branchId, (int) $idx + 1);

            $itemNote = trim(
                'Quality Reclass #' . (int) $adjustment->id . ' | GOOD->' . strtoupper($type) . ($globalNote ? ' | ' . $globalNote : '')
            );

            if ($type === 'defect') {
                $details = (array) ($item['defects'] ?? []);
                if (count($details) !== $qty) {
                    throw new \RuntimeException('Line #' . ($idx + 1) . ": defect detail rows must match qty.");
                }
                foreach ($details as $i => $detail) {
                    if (empty(DefectTypeSupport::extractFromPayload((array) $detail))) {
                        throw new \RuntimeException('Line #' . ($idx + 1) . ': defect types are required for unit #' . ($i + 1));
                    }
                }

                $this->mutationController->applyInOut($branchId, $warehouseId, $productId, 'Out', $qty, $reference, $itemNote, $date, $rackId, 'good', 'summary');

                foreach ($details as $detail) {
                    $detail = (array) $detail;
                    ProductDefectItem::query()->create([
                        'branch_id' => $branchId,
                        'warehouse_id' => $warehouseId,
                        'rack_id' => $rackId,
                        'product_id' => $productId,
                        'reference_id' => (int) $adjustment->id,
                        'reference_type' => Adjustment::class,
                        'quantity' => 1,
                        'defect_types' => DefectTypeSupport::extractFromPayload($detail),
                        'description' => trim((string) ($detail['description'] ?? '')) ?: null,
                        'photo_path' => $detail['photo_path'] ?? null,
                        'created_by' => (int) Auth::id(),
                    ]);
                }

                $this->mutationController->applyInOut($branchId, $warehouseId, $productId, 'In', $qty, $reference, $itemNote, $date, $rackId, 'defect', 'summary');
            } else {
                $details = (array) ($item['damaged_items'] ?? []);
                if (count($details) !== $qty) {
                    throw new \RuntimeException('Line #' . ($idx + 1) . ": damaged detail rows must match qty.");
                }
                foreach ($details as $i => $detail) {
                    if (trim((string) ($detail['reason'] ?? '')) === '') {
                        throw new \RuntimeException('Line #' . ($idx + 1) . ': damaged reason is required for unit #' . ($i + 1));
                    }
                }

                $this->mutationController->applyInOut($branchId, $warehouseId, $productId, 'Out', $qty, $reference, $itemNote, $date, $rackId, 'good', 'summary');
                $mutationInId = $this->mutationController->applyInOutAndGetMutationId($branchId, $warehouseId, $productId, 'In', $qty, $reference, $itemNote, $date, $rackId, 'damaged', 'summary');

                foreach ($details as $detail) {
                    $detail = (array) $detail;
                    $damageType = strtolower(trim((string) ($detail['damage_type'] ?? 'damaged')));
                    if (!in_array($damageType, ['damaged', 'missing'], true)) {
                        $damageType = 'damaged';
                    }

                    ProductDamagedItem::query()->create([
                        'branch_id' => $branchId,
                        'warehouse_id' => $warehouseId,
                        'rack_id' => $rackId,
                        'product_id' => $productId,
                        'reference_id' => (int) $adjustment->id,
                        'reference_type' => Adjustment::class,
                        'quantity' => 1,
                        'damage_type' => $damageType,
                        'reason' => trim((string) ($detail['reason'] ?? '')),
                        'photo_path' => $detail['photo_path'] ?? null,
                        'cause' => null,
                        'responsible_user_id' => null,
                        'resolution_status' => 'pending',
                        'resolution_note' => trim((string) ($detail['description'] ?? '')) ?: null,
                        'mutation_in_id' => (int) $mutationInId,
                        'mutation_out_id' => null,
                        'created_by' => (int) Auth::id(),
                    ]);
                }
            }

            $noteQrc = 'QRC GOOD->' . strtoupper($type) . ($globalNote ? ' | ' . $globalNote : '');
            if (mb_strlen($noteQrc) > 255) {
                $noteQrc = mb_substr($noteQrc, 0, 255);
            }

            AdjustedProduct::query()->create([
                'adjustment_id' => (int) $adjustment->id,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'rack_id' => $rackId,
                'quantity' => $qty,
                'type' => 'add',
                'note' => $noteQrc,
            ]);
        }
    }

    private function executeQualityIssueToGood(Adjustment $adjustment): void
    {
        $payload = (array) ($adjustment->payload ?? []);
        $items = (array) ($payload['items'] ?? []);
        $branchId = (int) $adjustment->branch_id;
        $date = $this->normalizeDate($payload['date'] ?? $adjustment->getRawOriginal('date'));
        $reference = (string) $adjustment->reference;
        $fromCondition = $adjustment->request_type === 'quality_damaged_to_good' ? 'damaged' : 'defect';
        $globalNote = trim((string) ($payload['user_note'] ?? ''));

        foreach ($items as $idx => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $expected = (int) ($item['qty'] ?? 0);
            $pickedIds = $this->normalizeIds($item['selected_unit_ids'] ?? []);
            $itemNote = trim(html_entity_decode((string) ($item['item_note'] ?? $item['user_note'] ?? $globalNote), ENT_QUOTES, 'UTF-8'));

            if ($productId <= 0 || $expected <= 0 || count($pickedIds) !== $expected) {
                throw new \RuntimeException('Invalid Issue -> GOOD item at line #' . ($idx + 1));
            }
            if ($itemNote === '') {
                throw new \RuntimeException('Item note is required at line #' . ($idx + 1));
            }

            $this->resolveAdjustmentProduct($productId, $branchId, (int) $idx + 1);

            $query = $fromCondition === 'defect'
                ? ProductDefectItem::query()
                : ProductDamagedItem::query();

            $units = $query
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->whereIn('id', $pickedIds)
                ->whereNull('moved_out_at')
                ->lockForUpdate()
                ->get(['id', 'warehouse_id', 'rack_id']);

            if ($units->count() !== count($pickedIds)) {
                throw new \RuntimeException('Some picked IDs are invalid / already moved out at line #' . ($idx + 1));
            }

            $groups = [];
            foreach ($units as $unit) {
                $wid = (int) $unit->warehouse_id;
                $rid = (int) $unit->rack_id;
                $this->assertWarehouseInBranch($wid, $branchId);
                $this->assertRackBelongsToWarehouse($rid, $wid);
                $groups[$wid . '|' . $rid][] = (int) $unit->id;
            }

            foreach ($groups as $key => $ids) {
                [$wid, $rid] = explode('|', $key);
                $wid = (int) $wid;
                $rid = (int) $rid;
                $qty = count($ids);
                $mutationNote = trim('Quality Reclass #' . (int) $adjustment->id . ' | ' . strtoupper($fromCondition) . '->GOOD | ' . $itemNote);
                $now = now();

                if ($fromCondition === 'defect') {
                    ProductDefectItem::query()
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->whereIn('id', $ids)
                        ->whereNull('moved_out_at')
                        ->update([
                            'moved_out_at' => $now,
                            'moved_out_by' => (int) Auth::id(),
                            'moved_out_reference_type' => Adjustment::class,
                            'moved_out_reference_id' => (int) $adjustment->id,
                            'updated_at' => $now,
                        ]);
                } else {
                    ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->whereIn('id', $ids)
                        ->whereNull('moved_out_at')
                        ->update([
                            'moved_out_at' => $now,
                            'moved_out_by' => (int) Auth::id(),
                            'moved_out_reference_type' => Adjustment::class,
                            'moved_out_reference_id' => (int) $adjustment->id,
                            'resolution_status' => 'resolved',
                            'updated_by' => (int) Auth::id(),
                            'updated_at' => $now,
                        ]);
                }

                $mutationOutId = $this->mutationController->applyInOutAndGetMutationId($branchId, $wid, $productId, 'Out', $qty, $reference, $mutationNote, $date, $rid, $fromCondition, 'summary');
                $this->mutationController->applyInOutAndGetMutationId($branchId, $wid, $productId, 'In', $qty, $reference, $mutationNote, $date, $rid, 'good', 'summary');

                $noteQrc = 'QRC ' . strtoupper($fromCondition) . '->GOOD' . ($itemNote !== '' ? ' | ' . $itemNote : '');
                if (mb_strlen($noteQrc) > 255) {
                    $noteQrc = mb_substr($noteQrc, 0, 255);
                }

                AdjustedProduct::query()->create([
                    'adjustment_id' => (int) $adjustment->id,
                    'product_id' => $productId,
                    'warehouse_id' => $wid,
                    'rack_id' => $rid,
                    'quantity' => $qty,
                    'type' => 'add',
                    'note' => $noteQrc,
                ]);

                if ($fromCondition === 'damaged' && (int) $mutationOutId > 0) {
                    ProductDamagedItem::query()
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->whereIn('id', $ids)
                        ->update([
                            'mutation_out_id' => (int) $mutationOutId,
                            'updated_by' => (int) Auth::id(),
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    private function normalizeIds($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        $raw = is_array($raw) ? $raw : [];
        $raw = array_map('intval', $raw);
        return array_values(array_unique(array_filter($raw, fn ($id) => $id > 0)));
    }

    private function normalizeDate($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        $value = trim((string) $value);
        if ($value === '') {
            return now()->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd M, Y', 'd F, Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Throwable $e) {
                //
            }
        }

        return Carbon::parse($value)->toDateString();
    }

    private function assertWarehouseInBranch(int $warehouseId, int $branchId): void
    {
        $warehouse = Warehouse::query()->where('id', $warehouseId)->first();
        if (!$warehouse || (int) $warehouse->branch_id !== $branchId) {
            throw new \RuntimeException("Selected warehouse_id={$warehouseId} is not in active branch.");
        }
    }

    private function assertRackBelongsToWarehouse(int $rackId, int $warehouseId): void
    {
        $ok = DB::table('racks')
            ->where('id', $rackId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (!$ok) {
            throw new \RuntimeException("Invalid rack: rack_id={$rackId} does not belong to warehouse_id={$warehouseId}.");
        }
    }

    private function resolveAdjustmentProduct(int $productId, int $branchId, int $lineNumber): Product
    {
        if ($productId <= 0) {
            throw new \RuntimeException("Line #{$lineNumber}: selected product is invalid. Please re-select the product.");
        }

        $product = Product::withoutGlobalScopes()
            ->where('id', $productId)
            ->where(function ($query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', (int) $branchId);
            })
            ->first();

        if (!$product) {
            throw new \RuntimeException("Line #{$lineNumber}: selected product was not found for the active branch. Please re-select the product.");
        }

        return $product;
    }
}

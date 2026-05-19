<?php

namespace Modules\Adjustment\Entities;

use App\Models\BaseModel;
use App\Models\User;
use App\Support\DefectTypeSupport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Adjustment extends BaseModel
{
    use HasFactory;

    /**
     * Catatan:
     * - Tabel adjustments sudah punya kolom created_by (lihat phpMyAdmin).
     * - Jadi kita tinggal expose di model + relasi creator().
     */
    protected $fillable = [
        'reference',
        'date',
        'note',
        'branch_id',
        'warehouse_id',
        'status',
        'request_type',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'approval_note',
        'rejection_reason',
        'executed_by',
        'executed_at',
        'payload',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\Modules\Product\Entities\Warehouse::class, 'warehouse_id');
    }

    public function adjustedProducts(): HasMany
    {
        return $this->hasMany(AdjustedProduct::class, 'adjustment_id', 'id');
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(AdjustmentRequestItem::class, 'adjustment_id', 'id')
            ->orderBy('line_no');
    }

    /**
     * Creator (audit trail)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /**
     * WARNING:
     * Kamu sebelumnya format date di accessor jadi string.
     * Aku biarkan supaya tidak merusak flow UI existing kamu.
     */
    public function getDateAttribute($value)
    {
        return Carbon::parse($value)->format('d M, Y');
    }

    public function isPending(): bool
    {
        return strtolower((string) $this->status) === 'pending';
    }

    public function isApproved(): bool
    {
        return strtolower((string) ($this->status ?? 'approved')) === 'approved';
    }

    public function isRejected(): bool
    {
        return strtolower((string) $this->status) === 'rejected';
    }

    public function requestProductVariationsCount(): int
    {
        if ($this->relationLoaded('requestItems')) {
            return (int) $this->requestItems
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->count();
        }

        return (int) $this->requestItems()
            ->whereNotNull('product_id')
            ->distinct('product_id')
            ->count('product_id');
    }

    public function requestTotalQuantity(): int
    {
        $total = $this->relationLoaded('requestItems')
            ? (int) $this->requestItems->sum('quantity')
            : (int) $this->requestItems()->sum('quantity');

        if ($total > 0) {
            return $total;
        }

        return collect((array) data_get($this->payload, 'items', []))
            ->sum(function ($item) {
                $item = (array) $item;
                return (int) ($item['qty'] ?? 0)
                    ?: ((int) ($item['qty_good'] ?? 0) + (int) ($item['qty_defect'] ?? 0) + (int) ($item['qty_damaged'] ?? 0));
            });
    }

    public function displayProductVariationsCount(): int
    {
        if ($this->isApproved()) {
            return (int) ($this->adjusted_products_count ?? $this->adjustedProducts()->count());
        }

        return $this->requestProductVariationsCount();
    }

    public function displayTotalQuantity(): int
    {
        if ($this->isApproved()) {
            return (int) ($this->adjusted_products_sum_quantity ?? $this->adjustedProducts()->sum('quantity'));
        }

        return $this->requestTotalQuantity();
    }

    public function pendingDisplayLines(): array
    {
        $items = $this->relationLoaded('requestItems')
            ? $this->requestItems
            : $this->requestItems()->with(['product', 'warehouse', 'rack'])->get();

        $rackMap = $this->pendingRackLabelMap($items);
        $lines = [];

        foreach ($items as $item) {
            $payload = (array) ($item->payload ?? []);
            $base = [
                'line_no' => (int) ($item->line_no ?? 0),
                'product_name' => optional($item->product)->product_name ?? '-',
                'product_code' => optional($item->product)->product_code ?? '-',
                'warehouse_name' => optional($item->warehouse)->warehouse_name ?? '-',
                'rack_display' => ['-'],
                'flow' => '-',
                'qty' => 0,
                'details' => [],
            ];

            switch ((string) $this->request_type) {
                case 'stock_add':
                    $lines = array_merge($lines, $this->pendingStockAddLines($base, $payload, $rackMap));
                    break;

                case 'stock_sub':
                    $lines = array_merge($lines, $this->pendingStockSubLines($base, $payload, $rackMap));
                    break;

                case 'quality_good_to_defect':
                    $lines[] = $this->pendingIssueLine($base, $payload, $rackMap, 'GOOD -> DEFECT', 'defects');
                    break;

                case 'quality_good_to_damaged':
                    $lines[] = $this->pendingIssueLine($base, $payload, $rackMap, 'GOOD -> DAMAGED', 'damaged_items');
                    break;

                case 'quality_defect_to_good':
                    $lines[] = $this->pendingIssueToGoodLine($base, $payload, 'DEFECT -> GOOD', 'defect');
                    break;

                case 'quality_damaged_to_good':
                    $lines[] = $this->pendingIssueToGoodLine($base, $payload, 'DAMAGED -> GOOD', 'damaged');
                    break;

                default:
                    $base['rack_display'] = [$this->rackLabel((int) ($item->rack_id ?? 0), $rackMap)];
                    $base['flow'] = strtoupper((string) ($item->condition_from ?? '-')) . ' -> ' . strtoupper((string) ($item->condition_to ?? '-'));
                    $base['qty'] = (int) ($item->quantity ?? 0);
                    $lines[] = $base;
                    break;
            }
        }

        return array_values(array_filter($lines, fn ($line) => (int) ($line['qty'] ?? 0) > 0));
    }

    private function pendingStockAddLines(array $base, array $payload, array $rackMap): array
    {
        $lines = [];
        $goodQty = (int) ($payload['qty_good'] ?? 0);
        $defectQty = (int) ($payload['qty_defect'] ?? 0);
        $damagedQty = (int) ($payload['qty_damaged'] ?? 0);

        if ($goodQty > 0) {
            $allocations = $this->allocationDetails((array) ($payload['good_allocations'] ?? []), 'to_rack_id', $rackMap);
            $line = $base;
            $line['flow'] = 'NEW -> GOOD';
            $line['qty'] = $goodQty;
            $line['rack_display'] = $this->allocationRackDisplay($allocations);
            $line['details'] = $allocations;
            $lines[] = $line;
        }

        if ($defectQty > 0) {
            $line = $base;
            $line['flow'] = 'NEW -> DEFECT';
            $line['qty'] = $defectQty;
            $line['rack_display'] = $this->unitRackDisplay((array) ($payload['defects'] ?? []), 'to_rack_id', $rackMap);
            $line['details'] = $this->unitDetails((array) ($payload['defects'] ?? []), 'defect', $rackMap);
            $lines[] = $line;
        }

        if ($damagedQty > 0) {
            $line = $base;
            $line['flow'] = 'NEW -> DAMAGED';
            $line['qty'] = $damagedQty;
            $line['rack_display'] = $this->unitRackDisplay((array) ($payload['damaged_items'] ?? []), 'to_rack_id', $rackMap);
            $line['details'] = $this->unitDetails((array) ($payload['damaged_items'] ?? []), 'damaged', $rackMap);
            $lines[] = $line;
        }

        return $lines;
    }

    private function pendingStockSubLines(array $base, array $payload, array $rackMap): array
    {
        $lines = [];
        $allocations = $this->allocationDetails((array) ($payload['good_allocations'] ?? []), 'from_rack_id', $rackMap);
        $goodQty = collect($allocations)->sum(fn ($allocation) => (int) ($allocation['qty'] ?? 0));

        if ($goodQty > 0) {
            $line = $base;
            $line['flow'] = 'GOOD -> OUT';
            $line['qty'] = (int) $goodQty;
            $line['rack_display'] = $this->allocationRackDisplay($allocations);
            $line['details'] = $allocations;
            $lines[] = $line;
        }

        $defectIds = $this->normalizeDisplayIds($payload['selected_defect_ids'] ?? []);
        if (!empty($defectIds)) {
            $line = $base;
            $line['flow'] = 'DEFECT -> OUT';
            $line['qty'] = count($defectIds);
            $line['rack_display'] = $this->selectedUnitRackDisplay($defectIds, 'defect');
            $line['details'] = [[
                'label' => 'Selected Units',
                'text' => implode(', ', $defectIds),
            ]];
            $line['details'] = array_merge($line['details'], $this->selectedUnitDetails($defectIds, 'defect'));
            $lines[] = $line;
        }

        $damagedIds = $this->normalizeDisplayIds($payload['selected_damaged_ids'] ?? []);
        if (!empty($damagedIds)) {
            $line = $base;
            $line['flow'] = 'DAMAGED -> OUT';
            $line['qty'] = count($damagedIds);
            $line['rack_display'] = $this->selectedUnitRackDisplay($damagedIds, 'damaged');
            $line['details'] = [[
                'label' => 'Selected Units',
                'text' => implode(', ', $damagedIds),
            ]];
            $line['details'] = array_merge($line['details'], $this->selectedUnitDetails($damagedIds, 'damaged'));
            $lines[] = $line;
        }

        return $lines;
    }

    private function pendingIssueLine(array $base, array $payload, array $rackMap, string $flow, string $detailKey): array
    {
        $rackId = (int) ($payload['rack_id'] ?? 0);
        $line = $base;
        $line['flow'] = $flow;
        $line['qty'] = (int) ($payload['qty'] ?? count((array) ($payload[$detailKey] ?? [])));
        $line['rack_display'] = [$this->rackLabel($rackId, $rackMap)];
        $line['details'] = $this->unitDetails((array) ($payload[$detailKey] ?? []), $detailKey === 'defects' ? 'defect' : 'damaged', $rackMap, $rackId);

        return $line;
    }

    private function pendingIssueToGoodLine(array $base, array $payload, string $flow, string $kind): array
    {
        $ids = $this->normalizeDisplayIds($payload['selected_unit_ids'] ?? []);
        $line = $base;
        $line['flow'] = $flow;
        $line['qty'] = (int) ($payload['qty'] ?? count($ids));
        $line['rack_display'] = $this->selectedUnitRackDisplay($ids, $kind);
        $line['details'] = [[
            'label' => 'Selected Units',
            'text' => !empty($ids) ? implode(', ', $ids) : '-',
        ]];
        $line['details'] = array_merge($line['details'], $this->selectedUnitDetails($ids, $kind));

        $note = trim((string) ($payload['item_note'] ?? $payload['user_note'] ?? ''));
        if ($note !== '') {
            $line['details'][] = [
                'label' => 'Note',
                'text' => $note,
            ];
        }

        return $line;
    }

    private function pendingRackLabelMap($items): array
    {
        $rackIds = collect();

        foreach ($items as $item) {
            $payload = (array) ($item->payload ?? []);
            $rackIds->push((int) ($item->rack_id ?? 0), (int) ($payload['rack_id'] ?? 0));

            foreach ((array) ($payload['good_allocations'] ?? []) as $allocation) {
                $rackIds->push((int) ($allocation['to_rack_id'] ?? 0), (int) ($allocation['from_rack_id'] ?? 0));
            }

            foreach ((array) ($payload['defects'] ?? []) as $detail) {
                $detail = (array) $detail;
                $rackIds->push((int) ($detail['to_rack_id'] ?? 0), (int) ($detail['rack_id'] ?? 0));
            }

            foreach ((array) ($payload['damaged_items'] ?? []) as $detail) {
                $detail = (array) $detail;
                $rackIds->push((int) ($detail['to_rack_id'] ?? 0), (int) ($detail['rack_id'] ?? 0));
            }
        }

        $rackIds = $rackIds->filter()->unique()->values();

        if ($rackIds->isEmpty()) {
            return [];
        }

        return DB::table('racks')
            ->whereIn('id', $rackIds->all())
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(function ($rack) {
                $label = trim(trim((string) ($rack->code ?? '')) . ' - ' . trim((string) ($rack->name ?? '')), ' -');
                return [(int) $rack->id => $label !== '' ? $label : 'Rack#' . (int) $rack->id];
            })
            ->all();
    }

    private function allocationDetails(array $allocations, string $rackKey, array $rackMap): array
    {
        $grouped = [];

        foreach ($allocations as $allocation) {
            $allocation = (array) $allocation;
            $qty = (int) ($allocation['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $rackId = (int) ($allocation[$rackKey] ?? 0);
            $label = $this->rackLabel($rackId, $rackMap);
            $key = $rackId > 0 ? (string) $rackId : $label;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'label' => 'Rack Allocation',
                    'rack' => $label,
                    'qty' => 0,
                    'text' => '',
                ];
            }

            $grouped[$key]['qty'] += $qty;
        }

        return array_values(array_map(function ($allocation) {
            $allocation['text'] = $allocation['rack'] . ': ' . (int) $allocation['qty'];
            return $allocation;
        }, $grouped));
    }

    private function allocationRackDisplay(array $allocations): array
    {
        $display = collect($allocations)
            ->map(fn ($allocation) => (string) ($allocation['text'] ?? '-'))
            ->filter()
            ->values()
            ->all();

        return !empty($display) ? $display : ['-'];
    }

    private function unitRackDisplay(array $units, string $rackKey, array $rackMap): array
    {
        $display = collect($units)
            ->map(function ($unit) use ($rackKey, $rackMap) {
                $unit = (array) $unit;
                return $this->rackLabel((int) ($unit[$rackKey] ?? $unit['rack_id'] ?? 0), $rackMap);
            })
            ->reject(fn ($label) => $label === '-')
            ->countBy()
            ->map(fn ($count, $label) => $label . ': ' . (int) $count)
            ->values()
            ->all();

        return !empty($display) ? $display : ['-'];
    }

    private function unitDetails(array $units, string $type, array $rackMap, ?int $fallbackRackId = null): array
    {
        $details = [];

        foreach (array_values($units) as $index => $unit) {
            $unit = (array) $unit;
            $rackId = (int) ($unit['to_rack_id'] ?? $unit['rack_id'] ?? $fallbackRackId ?? 0);
            $pieces = ['Rack: ' . $this->rackLabel($rackId, $rackMap)];

            if ($type === 'defect') {
                $defectText = DefectTypeSupport::text(DefectTypeSupport::extractFromPayload($unit), '-');
                if ($defectText !== '-') {
                    $pieces[] = 'Defect: ' . $defectText;
                }
                $description = trim((string) ($unit['defect_description'] ?? $unit['description'] ?? ''));
                if ($description !== '') {
                    $pieces[] = 'Note: ' . $description;
                }
            } else {
                $damageType = trim((string) ($unit['damage_type'] ?? ''));
                $reason = trim((string) ($unit['reason'] ?? ''));
                $description = trim((string) ($unit['description'] ?? $unit['resolution_note'] ?? ''));
                if ($damageType !== '') {
                    $pieces[] = 'Damage: ' . $damageType;
                }
                if ($reason !== '') {
                    $pieces[] = 'Reason: ' . $reason;
                }
                if ($description !== '') {
                    $pieces[] = 'Note: ' . $description;
                }
            }

            $details[] = [
                'label' => 'Unit ' . ($index + 1),
                'text' => implode(' | ', $pieces),
                'photo_path' => $unit['photo_path'] ?? null,
            ];
        }

        return $details;
    }

    private function selectedUnitDetails(array $ids, string $kind): array
    {
        if (empty($ids)) {
            return [];
        }

        $table = $kind === 'damaged' ? 'product_damaged_items' : 'product_defect_items';
        $rows = DB::table($table . ' as units')
            ->leftJoin('warehouses as warehouses', 'warehouses.id', '=', 'units.warehouse_id')
            ->leftJoin('racks as racks', 'racks.id', '=', 'units.rack_id')
            ->whereIn('units.id', $ids)
            ->orderBy('units.id')
            ->get([
                'units.id',
                'units.rack_id',
                'units.moved_out_at',
                'units.photo_path',
                'warehouses.warehouse_name',
                'racks.code as rack_code',
                'racks.name as rack_name',
                $kind === 'damaged' ? 'units.damage_type' : 'units.defect_types',
                $kind === 'damaged' ? 'units.reason' : 'units.description',
                $kind === 'damaged' ? 'units.resolution_note' : 'units.description as defect_description',
            ])
            ->keyBy('id');

        $details = [];
        foreach ($ids as $id) {
            $row = $rows->get($id);

            if (!$row) {
                $details[] = [
                    'label' => 'Unit #' . $id,
                    'text' => 'Unit snapshot unavailable',
                ];
                continue;
            }

            $rack = trim(trim((string) ($row->rack_code ?? '')) . ' - ' . trim((string) ($row->rack_name ?? '')), ' -');
            $pieces = [
                'Warehouse: ' . ((string) ($row->warehouse_name ?? '') !== '' ? $row->warehouse_name : '-'),
                'Rack: ' . ($rack !== '' ? $rack : ((int) ($row->rack_id ?? 0) > 0 ? 'Rack#' . (int) $row->rack_id : '-')),
                'Status: ' . (empty($row->moved_out_at) ? 'Available' : 'Moved Out'),
            ];

            if ($kind === 'defect') {
                $defectText = DefectTypeSupport::text($row->defect_types ?? [], '-');
                if ($defectText !== '-') {
                    $pieces[] = 'Defect: ' . $defectText;
                }
                if (!empty($row->defect_description)) {
                    $pieces[] = 'Note: ' . $row->defect_description;
                }
            } else {
                if (!empty($row->damage_type)) {
                    $pieces[] = 'Damage: ' . $row->damage_type;
                }
                if (!empty($row->reason)) {
                    $pieces[] = 'Reason: ' . $row->reason;
                }
                if (!empty($row->resolution_note)) {
                    $pieces[] = 'Note: ' . $row->resolution_note;
                }
            }

            $details[] = [
                'label' => 'Unit #' . $id,
                'text' => implode(' | ', $pieces),
                'photo_path' => $row->photo_path ?? null,
            ];
        }

        return $details;
    }

    private function selectedUnitRackDisplay(array $ids, string $kind): array
    {
        if (empty($ids)) {
            return ['-'];
        }

        $table = $kind === 'damaged' ? 'product_damaged_items' : 'product_defect_items';
        $rows = DB::table($table . ' as units')
            ->leftJoin('racks as racks', 'racks.id', '=', 'units.rack_id')
            ->whereIn('units.id', $ids)
            ->get([
                'units.rack_id',
                'racks.code as rack_code',
                'racks.name as rack_name',
            ]);

        $display = $rows
            ->map(function ($row) {
                $label = trim(trim((string) ($row->rack_code ?? '')) . ' - ' . trim((string) ($row->rack_name ?? '')), ' -');
                return $label !== '' ? $label : ((int) ($row->rack_id ?? 0) > 0 ? 'Rack#' . (int) $row->rack_id : '-');
            })
            ->reject(fn ($label) => $label === '-')
            ->countBy()
            ->map(fn ($count, $label) => $label . ': ' . (int) $count)
            ->values()
            ->all();

        return !empty($display) ? $display : ['-'];
    }

    private function rackLabel(int $rackId, array $rackMap): string
    {
        return $rackId > 0 ? ($rackMap[$rackId] ?? 'Rack#' . $rackId) : '-';
    }

    private function normalizeDisplayIds($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw), fn ($id) => $id > 0)));
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $number = Adjustment::max('id') + 1;
            $model->reference = make_reference_id('ADJ', $number);
        });
    }
}

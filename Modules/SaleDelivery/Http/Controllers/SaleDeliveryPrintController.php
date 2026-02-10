<?php

namespace Modules\SaleDelivery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;

use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\SaleDelivery\Entities\SaleDeliveryPrintLog;
use Modules\Setting\Entities\Setting;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;

use Modules\SaleDelivery\Http\Controllers\Concerns\SaleDeliveryShared;

class SaleDeliveryPrintController extends Controller
{
    use SaleDeliveryShared;

    public function preparePrint(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('show_sale_deliveries'), 403);

        try {
            $this->ensureSpecificBranchSelected();
            $branchId = BranchContext::id();

            $saleDelivery = SaleDelivery::withoutGlobalScopes()
                ->findOrFail($saleDelivery->id);

            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                abort(403, 'Unauthorized. Only owner branch can print sale delivery.');
            }

            $status = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));
            if (!in_array($status, ['confirmed'], true)) {
                abort(422, "Sale Delivery can be printed only when status is CONFIRMED.");
            }

            DB::transaction(function () use ($saleDelivery) {
                SaleDeliveryPrintLog::create([
                    'sale_delivery_id' => (int) $saleDelivery->id,
                    'user_id'          => (int) auth()->id(),
                    'printed_at'       => now(),
                    'ip_address'       => request()->ip(),
                ]);
            });

            $copyNumber = (int) SaleDeliveryPrintLog::query()
                ->where('sale_delivery_id', (int) $saleDelivery->id)
                ->count();

            if ($copyNumber <= 0) $copyNumber = 1;

            return response()->json([
                'ok'          => true,
                'status'      => $status,
                'copy_number' => $copyNumber,
                'pdf_url'     => route('sale-deliveries.print.pdf', [
                    'saleDelivery' => $saleDelivery->id,
                    'copy' => $copyNumber,
                ]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function printPdf(Request $request, SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('show_sale_deliveries'), 403);

        try {
            $this->ensureSpecificBranchSelected();
            $branchId = BranchContext::id();

            $saleDelivery = SaleDelivery::withoutGlobalScopes()
                ->with(['items.product', 'warehouse', 'customer', 'saleOrder'])
                ->findOrFail($saleDelivery->id);

            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            $status = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));
            if (!in_array($status, ['confirmed'], true)) {
                throw new \RuntimeException("Sale Delivery can be printed only when status is CONFIRMED.");
            }

            $setting = Setting::first();

            $copyNumber = (int) ($request->query('copy') ?? 0);
            if ($copyNumber <= 0) {
                $copyNumber = (int) SaleDeliveryPrintLog::query()
                    ->where('sale_delivery_id', (int) $saleDelivery->id)
                    ->count();

                if ($copyNumber <= 0) $copyNumber = 1;
            }

            $senderBranch = Branch::withoutGlobalScopes()->find((int) $saleDelivery->branch_id);

            $movedDefects = ProductDefectItem::query()
                ->where('moved_out_reference_type', SaleDelivery::class)
                ->where('moved_out_reference_id', (int) $saleDelivery->id)
                ->orderBy('id', 'asc')
                ->get();

            $movedDamaged = ProductDamagedItem::query()
                ->where('moved_out_reference_type', SaleDelivery::class)
                ->where('moved_out_reference_id', (int) $saleDelivery->id)
                ->where('damage_type', 'damaged')
                ->orderBy('id', 'asc')
                ->get();

            $defectsByProduct = $movedDefects->groupBy('product_id');
            $damagedByProduct = $movedDamaged->groupBy('product_id');

            $truncate = function (?string $text, int $max = 45): ?string {
                $text = trim((string) ($text ?? ''));
                if ($text === '') return null;
                if (mb_strlen($text) <= $max) return $text;
                return mb_substr($text, 0, $max) . '...';
            };

            $notesByItemId = [];

            foreach ($saleDelivery->items as $item) {
                $itemId = (int) $item->id;
                $pid = (int) $item->product_id;

                $good = (int) ($item->qty_good ?? 0);
                $defect = (int) ($item->qty_defect ?? 0);
                $damaged = (int) ($item->qty_damaged ?? 0);

                $expected = (int) ($item->quantity ?? 0);
                $sum = $good + $defect + $damaged;

                if ($sum <= 0 && $expected > 0) { $notesByItemId[$itemId] = 'GOOD'; continue; }
                if ($good > 0 && $defect === 0 && $damaged === 0) { $notesByItemId[$itemId] = 'GOOD'; continue; }

                $chunks = [];
                if ($good > 0) $chunks[] = "GOOD {$good}";

                if ($defect > 0) {
                    $rows = $defectsByProduct->get($pid, collect());
                    $types = $rows->pluck('defect_type')->filter()->unique()->values()->take(3)->toArray();
                    $typeText = !empty($types) ? implode(', ', $types) : 'Defect';

                    $desc = $rows->pluck('description')->filter()->first();
                    $desc = $truncate($desc, 45);

                    $txt = "DEFECT {$defect} ({$typeText})";
                    if (!empty($desc)) $txt .= " - {$desc}";
                    $chunks[] = $txt;
                }

                if ($damaged > 0) {
                    $rows = $damagedByProduct->get($pid, collect());
                    $reason = $rows->pluck('reason')->filter()->first();
                    $reason = $truncate($reason, 45);

                    $txt = "DAMAGED {$damaged}";
                    if (!empty($reason)) $txt .= " - {$reason}";
                    $chunks[] = $txt;
                }

                $notesByItemId[$itemId] = implode(' | ', $chunks);
            }

            $pdf = Pdf::loadView('saledelivery::print', [
                'saleDelivery'   => $saleDelivery,
                'setting'        => $setting,
                'copyNumber'     => $copyNumber,
                'senderBranch'   => $senderBranch,
                'notesByItemId'  => $notesByItemId,
            ])->setPaper('A4', 'portrait');

            $ref = $saleDelivery->reference ?? ('SDO-' . $saleDelivery->id);
            return $pdf->download("Surat_Jalan_SaleDelivery_{$ref}_COPY_{$copyNumber}.pdf");
        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }
}

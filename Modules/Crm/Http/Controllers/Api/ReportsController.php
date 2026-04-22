<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Entities\CtaClick;
use Modules\Crm\Entities\Lead;

class ReportsController extends Controller
{
    protected function scopeByBranch($query, string $column = 'branch_id')
    {
        if (!BranchContext::isAll()) {
            $id = BranchContext::id();
            if ($id !== null) {
                $query->where($column, (int) $id);
            }
        }

        return $query;
    }

    protected function applyDateRange($query, Request $request, string $column = 'created_at')
    {
        $from = $request->query('date_from');
        $to = $request->query('date_to');

        if ($from && $to) {
            $query->whereBetween($column, [$from . ' 00:00:00', $to . ' 23:59:59']);
        } elseif ($from) {
            $query->where($column, '>=', $from . ' 00:00:00');
        } elseif ($to) {
            $query->where($column, '<=', $to . ' 23:59:59');
        }

        return $query;
    }

    protected function applyDateRangeForDate($query, Request $request, string $column = 'date')
    {
        $from = $request->query('date_from');
        $to = $request->query('date_to');

        if ($from && $to) {
            $query->whereBetween($column, [$from, $to]);
        } elseif ($from) {
            $query->where($column, '>=', $from);
        } elseif ($to) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }

    protected function sourceExpression(?string $table = null): string
    {
        $prefix = $table ? $table . '.' : '';

        return "COALESCE(NULLIF({$prefix}source,''), NULLIF({$prefix}utm_source,''), 'direct')";
    }

    protected function summaryPayload(Request $request): array
    {
        $leadBase = Lead::query()->whereNull('deleted_at');
        $this->scopeByBranch($leadBase);
        $this->applyDateRange($leadBase, $request);

        $leadTotal = (clone $leadBase)->count();
        $byStatus = (clone $leadBase)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $completedLeads = (clone $leadBase)->where('status', 'selesai')->count();
        $scheduledLeads = (clone $leadBase)->whereIn('status', ['terjadwal', 'dalam_pengerjaan', 'pending_pengerjaan'])->count();
        $salesConverted = (clone $leadBase)->whereNotNull('sale_order_id')->count();
        $quotedValue = (int) DB::table('crm_lead_products')
            ->join('crm_leads', 'crm_leads.id', '=', 'crm_lead_products.lead_id')
            ->whereNull('crm_leads.deleted_at')
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('crm_leads.branch_id', (int) $id);
                }
            })
            ->tap(fn ($query) => $this->applyDateRange($query, $request, 'crm_leads.created_at'))
            ->sum('crm_lead_products.total_price');

        $ctaBase = CtaClick::query();
        $this->applyDateRange($ctaBase, $request);
        $ctaTotal = (clone $ctaBase)->count();
        $ctaConverted = (clone $ctaBase)->whereNotNull('lead_id')->count();
        $ctaPending = max($ctaTotal - $ctaConverted, 0);

        $serviceOrderBase = DB::table('crm_service_orders')->whereNull('deleted_at');
        $this->scopeByBranch($serviceOrderBase);
        $this->applyDateRange($serviceOrderBase, $request, 'scheduled_at');
        $serviceOrderTotal = (clone $serviceOrderBase)->count();

        $saleOrderBase = DB::table('sale_orders')->whereNull('deleted_at');
        $this->scopeByBranch($saleOrderBase);
        $this->applyDateRangeForDate($saleOrderBase, $request, 'date');
        $saleOrderTotal = (clone $saleOrderBase)->count();
        $saleOrderValue = (int) (clone $saleOrderBase)->sum('total_amount');

        $stockRows = DB::table('crm_lead_products')
            ->join('crm_leads', 'crm_leads.id', '=', 'crm_lead_products.lead_id')
            ->whereNull('crm_leads.deleted_at')
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('crm_leads.branch_id', (int) $id);
                }
            })
            ->tap(fn ($query) => $this->applyDateRange($query, $request, 'crm_leads.created_at'))
            ->selectRaw("COALESCE(crm_lead_products.stock_status, 'tidak_terdata') as stock_status, COUNT(*) as total_items")
            ->groupBy('stock_status')
            ->pluck('total_items', 'stock_status');

        $todayStart = Carbon::today()->startOfDay()->toDateTimeString();
        $todayEnd = Carbon::today()->endOfDay()->toDateTimeString();
        $overdueFollowUp = (clone $leadBase)
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', Carbon::now()->toDateTimeString())
            ->whereNotIn('status', ['selesai', 'batal'])
            ->count();
        $scheduledToday = DB::table('crm_service_orders')
            ->whereNull('deleted_at')
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('branch_id', (int) $id);
                }
            })
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
            ->count();

        return [
            'total' => $leadTotal,
            'by_status' => $byStatus,
            'conversions' => $completedLeads,
            'estimated_sales' => $quotedValue,
            'completed_estimated_sales' => $quotedValue,
            'realized_sales' => $saleOrderValue,
            'cta_tracking_total' => $ctaTotal,
            'cta_tracking_converted' => $ctaConverted,
            'cta_tracking_pending' => $ctaPending,
            'cta_conversion_rate' => $ctaTotal > 0 ? round(($ctaConverted / $ctaTotal) * 100, 2) : 0,
            'service_orders_total' => $serviceOrderTotal,
            'sale_orders_total' => $saleOrderTotal,
            'lead_to_sale_rate' => $leadTotal > 0 ? round(($salesConverted / $leadTotal) * 100, 2) : 0,
            'scheduled_leads' => $scheduledLeads,
            'quoted_value' => $quotedValue,
            'sales_order_value' => $saleOrderValue,
            'stock_ready_items' => (int) ($stockRows['tersedia'] ?? 0),
            'stock_indent_items' => (int) ($stockRows['perlu_order'] ?? 0),
            'stock_unavailable_items' => (int) ($stockRows['tidak_tersedia'] ?? 0),
            'overdue_follow_up' => $overdueFollowUp,
            'scheduled_today' => $scheduledToday,
        ];
    }

    protected function sourcePerformance(Request $request): Collection
    {
        $sourceExpr = $this->sourceExpression('clicks');

        return DB::table('crm_cta_clicks as clicks')
            ->leftJoin('crm_leads as leads', 'leads.id', '=', 'clicks.lead_id')
            ->tap(fn ($query) => $this->applyDateRange($query, $request, 'clicks.created_at'))
            ->selectRaw("
                {$sourceExpr} as source_label,
                COUNT(clicks.id) as cta_total,
                SUM(CASE WHEN clicks.lead_id IS NOT NULL THEN 1 ELSE 0 END) as converted_to_lead,
                SUM(CASE WHEN leads.sale_order_id IS NOT NULL THEN 1 ELSE 0 END) as converted_to_sale
            ")
            ->groupBy('source_label')
            ->orderByDesc('cta_total')
            ->get()
            ->map(function ($row) {
                $row->cta_total = (int) $row->cta_total;
                $row->converted_to_lead = (int) $row->converted_to_lead;
                $row->converted_to_sale = (int) $row->converted_to_sale;
                $row->lead_conversion_rate = $row->cta_total > 0
                    ? round(($row->converted_to_lead / $row->cta_total) * 100, 2)
                    : 0;
                $row->sale_conversion_rate = $row->cta_total > 0
                    ? round(($row->converted_to_sale / $row->cta_total) * 100, 2)
                    : 0;
                return $row;
            });
    }

    protected function branchPerformance(Request $request): Collection
    {
        $leadRows = DB::table('crm_leads')
            ->whereNull('deleted_at')
            ->tap(fn ($query) => $this->applyDateRange($query, $request))
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('branch_id', (int) $id);
                }
            })
            ->selectRaw("
                branch_id,
                COUNT(*) as lead_total,
                SUM(CASE WHEN sale_order_id IS NOT NULL THEN 1 ELSE 0 END) as sale_total,
                SUM(CASE WHEN status IN ('terjadwal','dalam_pengerjaan','pending_pengerjaan') THEN 1 ELSE 0 END) as scheduled_total
            ")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $jobRows = DB::table('crm_service_orders')
            ->whereNull('deleted_at')
            ->tap(fn ($query) => $this->applyDateRange($query, $request, 'scheduled_at'))
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('branch_id', (int) $id);
                }
            })
            ->selectRaw('branch_id, COUNT(*) as job_total')
            ->groupBy('branch_id')
            ->pluck('job_total', 'branch_id');

        $stockRows = DB::table('crm_lead_products')
            ->join('crm_leads', 'crm_leads.id', '=', 'crm_lead_products.lead_id')
            ->whereNull('crm_leads.deleted_at')
            ->tap(fn ($query) => $this->applyDateRange($query, $request, 'crm_leads.created_at'))
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('crm_leads.branch_id', (int) $id);
                }
            })
            ->selectRaw("
                crm_leads.branch_id,
                SUM(CASE WHEN crm_lead_products.stock_status = 'tersedia' THEN 1 ELSE 0 END) as ready_items,
                SUM(CASE WHEN crm_lead_products.stock_status = 'perlu_order' THEN 1 ELSE 0 END) as indent_items,
                SUM(CASE WHEN crm_lead_products.stock_status = 'tidak_tersedia' THEN 1 ELSE 0 END) as unavailable_items
            ")
            ->groupBy('crm_leads.branch_id')
            ->get()
            ->keyBy('branch_id');

        $branchIds = collect()
            ->merge($leadRows->keys())
            ->merge($jobRows->keys())
            ->merge($stockRows->keys())
            ->filter()
            ->unique()
            ->values();

        return $branchIds->map(function ($branchId) use ($leadRows, $jobRows, $stockRows) {
            $lead = $leadRows->get($branchId);
            $stock = $stockRows->get($branchId);

            return [
                'branch_id' => (int) $branchId,
                'lead_total' => (int) ($lead->lead_total ?? 0),
                'scheduled_total' => (int) ($lead->scheduled_total ?? 0),
                'job_total' => (int) ($jobRows[$branchId] ?? 0),
                'sale_total' => (int) ($lead->sale_total ?? 0),
                'ready_items' => (int) ($stock->ready_items ?? 0),
                'indent_items' => (int) ($stock->indent_items ?? 0),
                'unavailable_items' => (int) ($stock->unavailable_items ?? 0),
            ];
        })->values()->sortByDesc('lead_total')->values();
    }

    protected function assigneePerformance(Request $request): Collection
    {
        return DB::table('crm_lead_assignees')
            ->join('crm_leads', 'crm_leads.id', '=', 'crm_lead_assignees.lead_id')
            ->leftJoin('users', 'users.id', '=', 'crm_lead_assignees.user_id')
            ->whereNull('crm_leads.deleted_at')
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('crm_leads.branch_id', (int) $id);
                }
            })
            ->tap(fn ($query) => $this->applyDateRange($query, $request, 'crm_leads.created_at'))
            ->selectRaw("
                crm_lead_assignees.user_id,
                users.name as user_name,
                COUNT(DISTINCT crm_leads.id) as assigned_total,
                SUM(CASE WHEN crm_leads.status IN ('terjadwal','dalam_pengerjaan','pending_pengerjaan') THEN 1 ELSE 0 END) as scheduled_total,
                SUM(CASE WHEN crm_leads.sale_order_id IS NOT NULL THEN 1 ELSE 0 END) as sale_total,
                SUM(CASE WHEN crm_leads.status = 'selesai' THEN 1 ELSE 0 END) as completed_total
            ")
            ->groupBy('crm_lead_assignees.user_id', 'users.name')
            ->orderByDesc('assigned_total')
            ->get()
            ->map(function ($row) {
                return [
                    'user_id' => $row->user_id,
                    'user_name' => $row->user_name,
                    'assigned_total' => (int) $row->assigned_total,
                    'scheduled_total' => (int) $row->scheduled_total,
                    'sale_total' => (int) $row->sale_total,
                    'completed_total' => (int) $row->completed_total,
                ];
            });
    }

    protected function stockReadiness(Request $request): Collection
    {
        return DB::table('crm_lead_products')
            ->join('crm_leads', 'crm_leads.id', '=', 'crm_lead_products.lead_id')
            ->whereNull('crm_leads.deleted_at')
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('crm_leads.branch_id', (int) $id);
                }
            })
            ->tap(fn ($query) => $this->applyDateRange($query, $request, 'crm_leads.created_at'))
            ->selectRaw("
                COALESCE(crm_lead_products.stock_status, 'tidak_terdata') as stock_status,
                COUNT(*) as total_items,
                COUNT(DISTINCT crm_leads.id) as lead_count
            ")
            ->groupBy('stock_status')
            ->orderByDesc('total_items')
            ->get()
            ->map(function ($row) {
                return [
                    'stock_status' => $row->stock_status,
                    'label' => match ($row->stock_status) {
                        'tersedia' => 'Ready',
                        'perlu_order' => 'Perlu indent',
                        'tidak_tersedia' => 'Tidak tersedia',
                        default => 'Belum terdata',
                    },
                    'total_items' => (int) $row->total_items,
                    'lead_count' => (int) $row->lead_count,
                ];
            });
    }

    protected function serviceOrderStatus(Request $request): Collection
    {
        return DB::table('crm_service_orders')
            ->whereNull('deleted_at')
            ->when(!BranchContext::isAll(), function ($query) {
                $id = BranchContext::id();
                if ($id !== null) {
                    $query->where('branch_id', (int) $id);
                }
            })
            ->tap(fn ($query) => $this->applyDateRange($query, $request, 'scheduled_at'))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
            ]);
    }

    protected function topCampaigns(Request $request): Collection
    {
        return DB::table('crm_cta_clicks')
            ->tap(fn ($query) => $this->applyDateRange($query, $request))
            ->selectRaw("
                COALESCE(NULLIF(utm_campaign,''), '(none)') as campaign_label,
                COUNT(*) as total,
                SUM(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as converted
            ")
            ->groupBy('campaign_label')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'campaign_label' => $row->campaign_label,
                'total' => (int) $row->total,
                'converted' => (int) $row->converted,
            ]);
    }

    protected function topRefCodes(Request $request): Collection
    {
        return DB::table('crm_cta_clicks')
            ->whereNotNull('ref_code')
            ->tap(fn ($query) => $this->applyDateRange($query, $request))
            ->selectRaw('ref_code, COUNT(*) as total, SUM(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as converted')
            ->groupBy('ref_code')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'ref_code' => $row->ref_code,
                'total' => (int) $row->total,
                'converted' => (int) $row->converted,
            ]);
    }

    protected function fieldAudit(): array
    {
        return [
            'legacy_fields' => [
                'vehicle_make',
                'vehicle_model',
                'vehicle_year',
                'glass_type',
                'glass_types',
                'estimated_price',
                'realized_price',
                'stock_status',
                'conversation_with',
                'sales_chat_owner',
            ],
            'active_primary_fields' => [
                'branch_id',
                'service_type',
                'install_location_type',
                'install_location',
                'map_link',
                'source',
                'utm_source',
                'utm_campaign',
                'ref_code',
                'status',
                'scheduled_at',
                'assigned_user_id',
                'sales_owner_user_ids',
                'cta_click_id -> crm_cta_clicks.lead_id',
                'crm_lead_products.product_id',
                'crm_lead_products.quantity',
                'crm_lead_products.unit_price',
                'crm_lead_products.stock_status',
                'sale_order_id',
            ],
            'notes' => [
                'Kolom legacy masih dipertahankan untuk kompatibilitas data lama dan API lama.',
                'Laporan tim sebaiknya fokus ke crm_lead_products, CTA conversion, branch target, PK, dan Sale Order.',
                'estimated_price masih terisi sebagai ringkasan turunan dari total item lead, bukan input manual utama.',
            ],
        ];
    }

    public function summary(Request $request)
    {
        abort_if(Gate::denies('show_crm_reports'), 403);

        return response()->json($this->summaryPayload($request));
    }

    public function advanced(Request $request)
    {
        abort_if(Gate::denies('show_crm_reports'), 403);

        $summary = $this->summaryPayload($request);

        return response()->json([
            'summary' => $summary,
            'funnel' => [
                ['label' => 'CTA Tracked', 'total' => (int) ($summary['cta_tracking_total'] ?? 0)],
                ['label' => 'Lead Created', 'total' => (int) ($summary['total'] ?? 0)],
                ['label' => 'Perintah Kerja', 'total' => (int) ($summary['service_orders_total'] ?? 0)],
                ['label' => 'Sale Order', 'total' => (int) ($summary['sale_orders_total'] ?? 0)],
                ['label' => 'Lead Selesai', 'total' => (int) ($summary['conversions'] ?? 0)],
            ],
            'source_performance' => $this->sourcePerformance($request),
            'branch_performance' => $this->branchPerformance($request),
            'assignee_performance' => $this->assigneePerformance($request),
            'stock_readiness' => $this->stockReadiness($request),
            'service_order_status' => $this->serviceOrderStatus($request),
            'campaign_performance' => $this->topCampaigns($request),
            'ref_code_performance' => $this->topRefCodes($request),
            'field_audit' => $this->fieldAudit(),
        ]);
    }

    public function bySource(Request $request)
    {
        abort_if(Gate::denies('show_crm_reports'), 403);

        $q = Lead::query()->whereNull('deleted_at');
        $this->scopeByBranch($q);
        $this->applyDateRange($q, $request);

        $rows = $q->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function byBranch(Request $request)
    {
        abort_if(Gate::denies('show_crm_reports'), 403);

        if (!BranchContext::canViewAll()) {
            return response()->json(['data' => []]);
        }

        $q = Lead::query()->whereNull('deleted_at');
        $this->applyDateRange($q, $request);

        $rows = $q->select('branch_id', DB::raw('COUNT(*) as total'))
            ->groupBy('branch_id')
            ->orderByDesc('total')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function byAssignee(Request $request)
    {
        abort_if(Gate::denies('show_crm_reports'), 403);

        $q = DB::table('crm_lead_assignees')
            ->join('crm_leads', 'crm_leads.id', '=', 'crm_lead_assignees.lead_id')
            ->whereNull('crm_leads.deleted_at');

        if (!BranchContext::isAll()) {
            $id = BranchContext::id();
            if ($id !== null) {
                $q->where('crm_leads.branch_id', (int) $id);
            }
        }

        $this->applyDateRange($q, $request, 'crm_leads.created_at');

        $rows = $q->select('crm_lead_assignees.user_id as assigned_user_id', DB::raw('COUNT(DISTINCT crm_leads.id) as total'))
            ->groupBy('crm_lead_assignees.user_id')
            ->orderByDesc('total')
            ->get();

        $unassigned = Lead::query()->whereNull('deleted_at')->whereDoesntHave('assignees');
        $this->scopeByBranch($unassigned);
        $this->applyDateRange($unassigned, $request);
        $unassignedTotal = $unassigned->count();

        if ($unassignedTotal > 0) {
            $rows->push((object) ['assigned_user_id' => null, 'total' => $unassignedTotal]);
        }

        return response()->json(['data' => $rows]);
    }

    public function technicianPerformance(Request $request)
    {
        abort_if(Gate::denies('show_crm_reports'), 403);

        $q = DB::table('crm_service_order_technicians')
            ->join('crm_service_orders', 'crm_service_orders.id', '=', 'crm_service_order_technicians.service_order_id')
            ->join('users', 'users.id', '=', 'crm_service_order_technicians.user_id')
            ->whereNull('crm_service_orders.deleted_at');

        if (!BranchContext::isAll()) {
            $id = BranchContext::id();
            if ($id !== null) {
                $q->where('crm_service_order_technicians.branch_id', (int) $id);
            }
        }

        $this->applyDateRange($q, $request, 'crm_service_orders.scheduled_at');

        $rows = $q->select(
                'crm_service_order_technicians.user_id',
                'users.name as technician_name',
                DB::raw('COUNT(DISTINCT crm_service_order_technicians.service_order_id) as assigned_orders'),
                DB::raw("SUM(CASE WHEN crm_service_order_technicians.status = 'completed' THEN 1 ELSE 0 END) as completed_orders"),
                DB::raw('AVG(CASE WHEN crm_service_order_technicians.started_at IS NOT NULL AND crm_service_order_technicians.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, crm_service_order_technicians.started_at, crm_service_order_technicians.completed_at) ELSE NULL END) as avg_handling_minutes'),
                DB::raw('MIN(crm_service_order_technicians.started_at) as first_started_at'),
                DB::raw('MAX(crm_service_order_technicians.completed_at) as last_completed_at')
            )
            ->groupBy('crm_service_order_technicians.user_id', 'users.name')
            ->orderByDesc('completed_orders')
            ->get()
            ->map(function ($row) {
                $row->assigned_orders = (int) $row->assigned_orders;
                $row->completed_orders = (int) $row->completed_orders;
                $row->avg_handling_minutes = $row->avg_handling_minutes !== null
                    ? round((float) $row->avg_handling_minutes, 2)
                    : null;
                return $row;
            });

        return response()->json(['data' => $rows]);
    }

    public function ctaTracking(Request $request)
    {
        abort_if(Gate::denies('show_crm_reports'), 403);

        $base = CtaClick::query();
        $this->applyDateRange($base, $request);

        $summary = [
            'total' => (clone $base)->count(),
            'converted' => (clone $base)->whereNotNull('lead_id')->count(),
            'not_converted' => (clone $base)->whereNull('lead_id')->count(),
        ];
        $summary['conversion_rate'] = $summary['total'] > 0
            ? round(($summary['converted'] / $summary['total']) * 100, 2)
            : 0;

        $bySource = (clone $base)
            ->selectRaw("{$this->sourceExpression()} as source_label, COUNT(*) as total, SUM(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as converted")
            ->groupBy('source_label')
            ->orderByDesc('total')
            ->get();

        $byCampaign = (clone $base)
            ->selectRaw("COALESCE(NULLIF(utm_campaign,''), '(none)') as utm_campaign, COUNT(*) as total, SUM(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as converted")
            ->groupBy('utm_campaign')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $byRefCode = (clone $base)
            ->whereNotNull('ref_code')
            ->selectRaw('ref_code, COUNT(*) as total, SUM(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as converted')
            ->groupBy('ref_code')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return response()->json([
            'summary' => $summary,
            'by_source' => $bySource,
            'by_campaign' => $byCampaign,
            'by_ref_code' => $byRefCode,
        ]);
    }
}

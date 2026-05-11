<?php

namespace Modules\Crm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ScheduleCapacityController extends Controller
{
    private const DAILY_CAPACITY = 10;

    /**
     * GET /api/crm/schedule/capacity?date=YYYY-MM-DD
     * Read-only. Returns per-branch job counts for the given date.
     */
    public function index(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        // Validate + normalise date — fallback to today if malformed
        $date = (string) $request->query('date', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->toDateString();
        }

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $branches = $user->allAvailableBranches();
        $branchIds = [];
        foreach ($branches as $b) {
            $branchIds[] = (int) $b->id;
        }

        if (empty($branchIds)) {
            return response()->json(['date' => $date, 'branches' => []]);
        }

        // Fetch all relevant rows for this date, group counts in PHP
        $rows = DB::table('crm_service_orders')
            ->whereNull('deleted_at')
            ->whereIn('branch_id', $branchIds)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['scheduled', 'in_progress', 'completed'])
            ->select('branch_id', 'status')
            ->get();

        // Build count map indexed by branch_id (integer key)
        $countMap = [];
        foreach ($rows as $row) {
            $bid = (int) $row->branch_id;
            if (!isset($countMap[$bid])) {
                $countMap[$bid] = ['scheduled' => 0, 'in_progress' => 0, 'completed' => 0];
            }
            $s = (string) $row->status;
            if (isset($countMap[$bid][$s])) {
                $countMap[$bid][$s]++;
            }
        }

        $capacity   = self::DAILY_CAPACITY;
        $branchList = [];

        foreach ($branches as $branch) {
            $bid        = (int) $branch->id;
            $c          = isset($countMap[$bid]) ? $countMap[$bid] : ['scheduled' => 0, 'in_progress' => 0, 'completed' => 0];
            $scheduled  = (int) $c['scheduled'];
            $inProgress = (int) $c['in_progress'];
            $completed  = (int) $c['completed'];
            $active     = $scheduled + $inProgress;
            $total      = $active + $completed;
            $available  = max($capacity - $active, 0);

            if ($active >= $capacity) {
                $status = 'full';
            } elseif ($active >= (int) ceil($capacity * 0.7)) {
                $status = 'busy';
            } elseif ($active === 0) {
                $status = 'empty';
            } else {
                $status = 'available';
            }

            $branchList[] = [
                'branch_id'         => $bid,
                'branch_name'       => (string) $branch->name,
                'scheduled_count'   => $scheduled,
                'in_progress_count' => $inProgress,
                'completed_count'   => $completed,
                'total_active'      => $active,
                'total_today'       => $total,
                'capacity'          => $capacity,
                'available_slots'   => $available,
                'status'            => $status,
            ];
        }

        return response()->json([
            'date'     => $date,
            'branches' => $branchList,
        ]);
    }

    /**
     * GET /api/crm/schedule/jobs?date=YYYY-MM-DD
     *
     * Returns the full list of service orders for the given date, grouped by branch.
     * Includes customer, vehicle, and product info. Read-only.
     */
    public function jobs(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $date = (string) $request->query('date', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->toDateString();
        }

        /** @var \App\Models\User $user */
        $user     = Auth::user();
        $branches = $user->allAvailableBranches();
        $branchIds = [];
        $branchMap = [];
        foreach ($branches as $b) {
            $bid = (int) $b->id;
            $branchIds[] = $bid;
            $branchMap[$bid] = (string) $b->name;
        }

        if (empty($branchIds)) {
            return response()->json(['date' => $date, 'branches' => []]);
        }

        // Fetch service orders joined with lead info for the date
        $rows = DB::table('crm_service_orders as so')
            ->leftJoin('crm_leads as l', 'l.id', '=', 'so.lead_id')
            ->whereNull('so.deleted_at')
            ->whereIn('so.branch_id', $branchIds)
            ->whereDate('so.scheduled_at', $date)
            ->whereIn('so.status', ['scheduled', 'in_progress', 'completed'])
            ->orderBy('so.scheduled_at')
            ->select(
                'so.id',
                'so.lead_id',
                'so.spk_number',
                'so.scheduled_at',
                'so.status',
                'so.branch_id',
                'l.install_location_type',
                'so.address_snapshot',
                'so.admin_note',
                'l.contact_name',
                'l.contact_whatsapp',
                'l.vehicle_make',
                'l.vehicle_model',
                'l.vehicle_year',
                'l.vehicle_plate',
                'l.glass_type'
            )
            ->get();

        // Get first product per lead
        $leadIds = [];
        foreach ($rows as $row) {
            if ($row->lead_id) {
                $leadIds[] = (string) $row->lead_id;
            }
        }
        $leadIds = array_unique($leadIds);

        $productMap = [];
        if (!empty($leadIds)) {
            $products = DB::table('crm_lead_products')
                ->whereIn('lead_id', $leadIds)
                ->orderBy('id')
                ->select('lead_id', 'product_code', 'product_name')
                ->get();
            foreach ($products as $p) {
                $lid = (string) $p->lead_id;
                if (!isset($productMap[$lid])) {
                    $productMap[$lid] = [
                        'product_code' => $p->product_code,
                        'product_name' => $p->product_name,
                    ];
                }
            }
        }

        // Initialise all branches (including ones with no jobs)
        $grouped = [];
        foreach ($branchIds as $bid) {
            $grouped[$bid] = [
                'branch_id'   => $bid,
                'branch_name' => $branchMap[$bid] ?? "Branch #{$bid}",
                'jobs'        => [],
            ];
        }

        foreach ($rows as $row) {
            $bid = (int) $row->branch_id;
            if (!isset($grouped[$bid])) {
                continue;
            }

            $lid     = $row->lead_id ? (string) $row->lead_id : null;
            $product = ($lid && isset($productMap[$lid])) ? $productMap[$lid] : ['product_code' => null, 'product_name' => null];

            $vehicleParts = array_filter([$row->vehicle_make ?? '', $row->vehicle_model ?? '']);
            $vehicle      = implode(' ', $vehicleParts) ?: null;

            $grouped[$bid]['jobs'][] = [
                'id'                    => $row->id,
                'spk_number'            => $row->spk_number,
                'scheduled_at'          => $row->scheduled_at,
                'status'                => $row->status,
                'install_location_type' => $row->install_location_type,
                'address'               => $row->address_snapshot,
                'admin_note'            => $row->admin_note,
                'customer_name'         => $row->contact_name,
                'customer_whatsapp'     => $row->contact_whatsapp,
                'vehicle'               => $vehicle,
                'vehicle_year'          => $row->vehicle_year,
                'vehicle_plate'         => $row->vehicle_plate,
                'glass_type'            => $row->glass_type,
                'product_code'          => $product['product_code'],
                'product_name'          => $product['product_name'],
            ];
        }

        return response()->json([
            'date'     => $date,
            'branches' => array_values($grouped),
        ]);
    }
}

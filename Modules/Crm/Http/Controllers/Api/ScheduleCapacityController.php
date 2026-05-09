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
}

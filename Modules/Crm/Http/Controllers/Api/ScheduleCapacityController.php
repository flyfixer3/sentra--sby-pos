<?php

namespace Modules\Crm\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ScheduleCapacityController extends Controller
{
    /**
     * Default max jobs per branch per day.
     * Can be made configurable per branch later.
     */
    private const DAILY_CAPACITY = 10;

    /**
     * GET /api/crm/schedule/capacity?date=YYYY-MM-DD
     *
     * Returns scheduled job counts for each accessible branch on the given date.
     * Read-only — never mutates any data.
     */
    public function index(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        // Parse and validate the requested date (defaults to today)
        $rawDate = $request->query('date', now()->toDateString());
        try {
            $date = Carbon::createFromFormat('Y-m-d', $rawDate)->startOfDay();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Format tanggal tidak valid. Gunakan YYYY-MM-DD.'], 422);
        }

        // Resolve branches the current user can access
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $branches = $user->allAvailableBranches();
        $branchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($branchIds)) {
            return response()->json(['date' => $date->toDateString(), 'branches' => []]);
        }

        // Count active service orders per branch for the requested date
        $counts = DB::table('crm_service_orders')
            ->whereNull('deleted_at')
            ->whereIn('branch_id', $branchIds)
            ->whereDate('scheduled_date', $date->toDateString())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->select(
                'branch_id',
                DB::raw("SUM(CASE WHEN status = 'scheduled'   THEN 1 ELSE 0 END) as scheduled_count"),
                DB::raw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count"),
                DB::raw('COUNT(*) as total_active'),
            )
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $capacity = self::DAILY_CAPACITY;

        $branchData = $branches->map(function ($branch) use ($counts, $capacity) {
            $bid        = (int) $branch->id;
            $row        = $counts->get($bid);
            $scheduled  = $row ? (int) $row->scheduled_count  : 0;
            $inProgress = $row ? (int) $row->in_progress_count : 0;
            $total      = $row ? (int) $row->total_active      : 0;
            $available  = max($capacity - $total, 0);

            $status = match (true) {
                $total >= $capacity                        => 'full',
                $total >= (int) ceil($capacity * 0.7)     => 'busy',
                $total === 0                              => 'empty',
                default                                   => 'available',
            };

            return [
                'branch_id'          => $bid,
                'branch_name'        => $branch->name,
                'scheduled_count'    => $scheduled,
                'in_progress_count'  => $inProgress,
                'total_active'       => $total,
                'capacity'           => $capacity,
                'available_slots'    => $available,
                'status'             => $status,
            ];
        });

        return response()->json([
            'date'     => $date->toDateString(),
            'branches' => $branchData->values(),
        ]);
    }
}

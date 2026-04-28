<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Entities\ServiceOrder;
use Modules\Crm\Entities\ServiceOrderTechnician;

class TechniciansController extends Controller
{
    protected function requireBranch(): int
    {
        $id = BranchContext::id();
        if ($id === null) {
            return abort(422, "Please select a specific branch first (not 'All Branch').");
        }
        return (int) $id;
    }

    protected function actingTechnicianRow(ServiceOrder $serviceOrder, Request $request): ?ServiceOrderTechnician
    {
        $query = ServiceOrderTechnician::where('service_order_id', $serviceOrder->id);

        if (Gate::allows('assign_crm_service_orders')) {
            $requestedUserId = $request->input('technician_user_id');
            if ($requestedUserId) {
                return (clone $query)->where('user_id', (int) $requestedUserId)->first();
            }

            return (clone $query)
                ->where('status', '!=', 'completed')
                ->orderByRaw("CASE WHEN user_id = ? THEN 0 ELSE 1 END", [auth()->id()])
                ->orderBy('id')
                ->first();
        }

        return $query->where('user_id', auth()->id())->first();
    }

    public function assign(Request $request, int $id)
    {
        abort_if(Gate::denies('assign_crm_service_orders'), 403);
        $branchId = $this->requireBranch();
        $so = ServiceOrder::findOrFail($id);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
            'roles' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($data, $branchId, $so) {
            foreach ($data['user_ids'] as $uid) {
                ServiceOrderTechnician::updateOrCreate(
                    [
                        'service_order_id' => (int) $so->id,
                        'user_id' => (int) $uid,
                    ],
                    [
                        'branch_id' => $branchId,
                        'role' => $data['roles'][(string) $uid] ?? $data['roles'][(int) $uid] ?? null,
                        'status' => 'assigned',
                        'assigned_by' => auth()->id(),
                        'assigned_at' => now(),
                    ]
                );
            }
        });

        return response()->json(['message' => 'Technicians assigned']);
    }

    public function accept(Request $request, int $id)
    {
        // Optional in Phase-1; keep as no-op
        return response()->json(['message' => 'Accept not implemented in Phase-1 minimal scope'], 501);
    }

    public function start(Request $request, int $id)
    {
        $this->requireBranch();
        $so = ServiceOrder::findOrFail($id);

        $row = $this->actingTechnicianRow($so, $request);
        if (!$row) abort(422, 'You are not assigned to this service order.');

        if ($row->status === 'completed') {
            abort(422, 'Your task for this service order is already completed.');
        }
        if ($row->status === 'started') {
            // idempotent
        } elseif ($row->status === 'assigned') {
            $row->update([
                'status' => 'started',
                'started_at' => now(),
            ]);
        }

        if (!$so->started_at) {
            $so->update([
                'started_at' => now(),
                'status' => 'in_progress',
            ]);

            if ($so->lead_id) {
                $so->lead()->update(['status' => 'dalam_pengerjaan']);
            }
        }

        return response()->json(['message' => 'Job started']);
    }

    public function complete(Request $request, int $id)
    {
        $this->requireBranch();
        $data = $request->validate([
            'technician_note' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $so = ServiceOrder::withCount([
                'photos as before_photos_count' => function($q){ $q->where('phase','before'); },
                'photos as after_photos_count' => function($q){ $q->where('phase','after'); },
            ])
            ->findOrFail($id);

        // Business rule: Cannot complete job without before photo
        if ((int) $so->before_photos_count <= 0) {
            abort(422, 'Cannot complete job without at least one before photo.');
        }
        if ((int) $so->after_photos_count <= 0) {
            abort(422, 'Cannot complete job without at least one after photo.');
        }

        $row = $this->actingTechnicianRow($so, $request);
        if (!$row || !in_array($row->status, ['assigned','started'], true)) abort(422, 'You must be assigned and have started before completing.');

        $row->update([
            'status' => 'completed',
            'completed_at' => now(),
            'note' => $data['note'] ?? $data['technician_note'] ?? $row->note,
        ]);

        // If all assigned technicians completed, mark SO as completed
        $remaining = ServiceOrderTechnician::where('service_order_id', $so->id)
            ->where('status', '!=', 'completed')
            ->count();
        if ($remaining === 0) {
            $so->update([
                'status' => 'completed',
                'completed_at' => now(),
                'technician_note' => $data['technician_note'] ?? $data['note'] ?? $so->technician_note,
            ]);

            if ($so->lead_id) {
                $so->lead()->update(['status' => 'selesai']);
            }
        }

        return response()->json(['message' => 'Job completed', 'service_order' => $so->fresh(['technicians.user','photos.media','warranty','lead'])]);
    }

    public function available(Request $request)
    {
        $roleNames = ['Technician', 'Teknisi', 'Technician Leader'];

        // Leaders can request all branches at once (for the assign-technician screen)
        $wantsAll = $request->boolean('all_branches') && Gate::allows('assign_crm_service_orders');

        if ($wantsAll) {
            // Each user's primary branch = the branch_user row with the lowest branch_id.
            // Using a subquery avoids duplicate rows for users who belong to multiple branches.
            $primaryBranchSub = \DB::table('branch_user')
                ->select('user_id', \DB::raw('MIN(branch_id) as branch_id'))
                ->groupBy('user_id');

            $rows = \DB::table('users')
                ->join('model_has_roles', fn($j) => $j->on('model_has_roles.model_id', '=', 'users.id'))
                ->join('roles', fn($j) => $j->on('roles.id', '=', 'model_has_roles.role_id'))
                ->joinSub($primaryBranchSub, 'pb', fn($j) => $j->on('pb.user_id', '=', 'users.id'))
                ->join('branches', fn($j) => $j->on('branches.id', '=', 'pb.branch_id'))
                ->select('users.id', 'users.name', 'users.email', 'pb.branch_id', 'branches.name as branch_name')
                ->whereIn('roles.name', $roleNames)
                ->groupBy('users.id', 'users.name', 'users.email', 'pb.branch_id', 'branches.name')
                ->orderBy('users.name')
                ->get();
        } else {
            $branchId = $this->requireBranch();

            $rows = \DB::table('users')
                ->join('model_has_roles', fn($j) => $j->on('model_has_roles.model_id', '=', 'users.id'))
                ->join('roles', fn($j) => $j->on('roles.id', '=', 'model_has_roles.role_id'))
                ->join('branch_user', fn($j) => $j->on('branch_user.user_id', '=', 'users.id'))
                ->join('branches', fn($j) => $j->on('branches.id', '=', 'branch_user.branch_id'))
                ->select('users.id', 'users.name', 'users.email', 'branch_user.branch_id', 'branches.name as branch_name')
                ->whereIn('roles.name', $roleNames)
                ->where('branch_user.branch_id', $branchId)
                ->groupBy('users.id', 'users.name', 'users.email', 'branch_user.branch_id', 'branches.name')
                ->orderBy('users.name')
                ->get();
        }

        return response()->json(['data' => $rows]);
    }
}












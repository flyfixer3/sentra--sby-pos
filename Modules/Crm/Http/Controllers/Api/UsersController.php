<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Entities\CrmUserAccessOverride;

class UsersController extends Controller
{
    /**
     * List ALL system users with their CRM access status.
     * Used by the "CRM User Access" management page.
     */
    public function index()
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        // Fetch all active users with roles and branches in one query
        $users = User::query()
            ->without('media')
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhereNull('is_active');
            })
            ->with([
                'roles:id,name',
                'branches:id,name',
            ])
            ->orderBy('name')
            ->get();

        // Load overrides in one query (not N+1)
        $overrides = CrmUserAccessOverride::pluck('blocked', 'user_id');

        $data = $users->map(function (User $user) use ($overrides) {
            $blocked = $overrides->get($user->id, false);
            $hasAccessViaRole = $user->can('access_crm');
            $crmAccess = $hasAccessViaRole && !$blocked;

            return [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'is_active'    => (bool) ($user->is_active ?? true),
                'roles'        => $user->roles->pluck('name')->values(),
                'branches'     => $user->branches->map(fn($b) => ['id' => $b->id, 'name' => $b->name])->values(),
                'crm_access'   => $crmAccess,
                'crm_blocked'  => $blocked,
                'access_via_role' => $hasAccessViaRole,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Toggle CRM access for a specific user.
     * action=disable → blocks user even if their role has access_crm
     * action=enable  → removes the block
     */
    public function toggleAccess(Request $request, int $id)
    {
        abort_if(Gate::denies('manage_crm_permissions'), 403);

        $data = $request->validate([
            'action' => ['required', 'in:enable,disable'],
        ]);

        $user = User::findOrFail($id);
        $blocked = $data['action'] === 'disable';

        CrmUserAccessOverride::updateOrCreate(
            ['user_id' => $user->id],
            ['blocked' => $blocked, 'updated_by' => auth()->id()]
        );

        // Clear user cache so next request reflects the change
        Cache::forget('api:crm:users:crm-access');

        return response()->json([
            'message'     => $blocked ? 'CRM access disabled.' : 'CRM access enabled.',
            'crm_blocked' => $blocked,
            'crm_access'  => !$blocked && $user->can('access_crm'),
        ]);
    }
}

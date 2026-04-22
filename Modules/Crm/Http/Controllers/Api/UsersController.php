<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class UsersController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $users = Cache::remember('api:crm:users:crm-access', now()->addMinutes(5), function () {
            return User::query()
                ->without('media')
                ->where(function ($query) {
                    $query->where('users.is_active', 1)
                        ->orWhereNull('users.is_active');
                })
                ->with(['roles.permissions:id,name', 'branches:id,name'])
                ->orderBy('users.name')
                ->get()
                ->filter(function (User $user) {
                    return $user->can('access_crm');
                })
                ->values()
                ->map(function (User $user) {
                    $branches = $user->branches->map(function ($branch) {
                        return [
                            'id' => (int) $branch->id,
                            'name' => (string) $branch->name,
                        ];
                    })->values();

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'branch_id' => (int) ($branches->first()['id'] ?? 0),
                        'branches' => $branches,
                        'roles' => $user->roles->pluck('name')->values(),
                    ];
                });
        });

        return response()->json(['data' => $users]);
    }
}

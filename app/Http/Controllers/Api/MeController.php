<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MeController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        $profile = Cache::remember("api:me:profile:{$user->id}", now()->addMinutes(5), function () use ($user) {
            $roles = $user->getRoleNames()->values();
            $roleNames = $roles->map(fn ($role) => strtolower((string) $role))->all();

            return [
                'roles' => $roles,
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'branches' => $user->allAvailableBranches()->map(fn ($b) => [
                    'id' => (int) $b->id,
                    'name' => (string) $b->name,
                ])->values(),
                'can_view_all_branches' => in_array('owner', $roleNames, true)
                    || in_array('super admin', $roleNames, true)
                    || in_array('administrator', $roleNames, true),
            ];
        });

        $active = session('active_branch');
        if (!$active && $profile['branches']->count() > 0) {
            $active = (int) $profile['branches']->first()['id'];
        }

        return response()->json([
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'roles' => $profile['roles'],
            'permissions' => $profile['permissions'],
            'branches' => $profile['branches'],
            'active_branch' => $active,
            'can_view_all_branches' => $profile['can_view_all_branches'],
        ]);
    }

    public function branches(Request $request)
    {
        $user = $request->user();
        $branches = Cache::remember("api:me:branches:{$user->id}", now()->addMinutes(5), function () use ($user) {
            return $user->allAvailableBranches()->map(fn ($b) => [
                'id' => (int) $b->id,
                'name' => (string) $b->name,
            ])->values();
        });

        return response()->json(['data' => $branches]);
    }
}

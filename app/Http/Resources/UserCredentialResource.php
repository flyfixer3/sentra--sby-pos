<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserCredentialResource extends JsonResource
{
    public function toArray($request): array
    {
        // Underlying model instance (App\Models\User enhanced with 'token')
        $user = $this->resource;

        // Roles / permissions (Spatie)
        $roles = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->values()->toArray()
            : [];

        $permissions = method_exists($user, 'getAllPermissions')
            ? $user->getAllPermissions()->pluck('name')->values()->toArray()
            : [];

        // Branches available to the user
        $branches = method_exists($user, 'allAvailableBranches')
            ? $user->allAvailableBranches()
                ->map(fn ($b) => [ 'id' => (int) $b->id, 'name' => (string) $b->name ])
                ->values()->toArray()
            : [];

        // Active branch: prefer existing session, otherwise first allowed
        $activeBranch = session('active_branch');
        if (!$activeBranch && !empty($branches)) {
            $activeBranch = (int) ($branches[0]['id'] ?? null);
        }

        return [
            'token' => $user->token ?? null,
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            // Back-compat fields used by frontend mappers
            'role' => $roles[0] ?? null,
            'branch_id' => $activeBranch,
            'branchId' => $activeBranch,
            'branches' => $branches,
        ];
    }
}

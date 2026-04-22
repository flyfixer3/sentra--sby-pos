<?php

namespace Modules\Crm\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsController extends Controller
{
    private function authorizeManagement(): void
    {
        $user = auth()->user();

        abort_if(
            !$user || !$user->can('manage_crm_permissions'),
            403
        );
    }

    /**
     * @return array<int, string>
     */
    private function crmPermissionNames(): array
    {
        return [
            'access_crm',
            'manage_crm_permissions',
            'show_crm_reports',
            'create_crm_leads',
            'show_crm_leads',
            'edit_crm_leads',
            'delete_crm_leads',
            'comment_crm_leads',
            'convert_crm_leads',
            'create_crm_service_orders',
            'show_crm_service_orders',
            'edit_crm_service_orders',
            'delete_crm_service_orders',
            'assign_crm_service_orders',
            'upload_crm_photos',
            'delete_crm_photos',
            'show_crm_warranties',
            'upsert_crm_warranties',
            'view_all_branches',
        ];
    }

    public function index()
    {
        $this->authorizeManagement();

        $permissionNames = $this->crmPermissionNames();

        $permissions = Permission::query()
            ->whereIn('name', $permissionNames)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Permission $permission) {
                return [
                    'id' => (int) $permission->id,
                    'name' => (string) $permission->name,
                ];
            })
            ->values();

        $roles = Role::query()
            ->with(['permissions:id,name'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Role $role) use ($permissionNames) {
                return [
                    'id' => (int) $role->id,
                    'name' => (string) $role->name,
                    'crm_permissions' => $role->permissions
                        ->pluck('name')
                        ->filter(fn ($name) => in_array((string) $name, $permissionNames, true))
                        ->values(),
                ];
            })
            ->values();

        return response()->json([
            'permissions' => $permissions,
            'roles' => $roles,
        ]);
    }

    public function updateRole(Request $request, int $roleId)
    {
        $this->authorizeManagement();

        $permissionNames = $this->crmPermissionNames();

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', $permissionNames)],
        ]);

        /** @var Role $role */
        $role = Role::query()->findOrFail($roleId);

        $selected = collect($data['permissions'] ?? [])
            ->map(fn ($name) => (string) $name)
            ->filter(fn ($name) => in_array($name, $permissionNames, true))
            ->unique()
            ->values();

        $currentCrmPermissions = $role->permissions()
            ->whereIn('name', $permissionNames)
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->values();

        $toRevoke = $currentCrmPermissions->diff($selected)->values();
        $toGrant = $selected->diff($currentCrmPermissions)->values();

        if ($toRevoke->isNotEmpty()) {
            $role->revokePermissionTo($toRevoke->all());
        }

        if ($toGrant->isNotEmpty()) {
            $role->givePermissionTo($toGrant->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->load('permissions:id,name');

        return response()->json([
            'message' => 'CRM permissions updated.',
            'role' => [
                'id' => (int) $role->id,
                'name' => (string) $role->name,
                'crm_permissions' => $role->permissions
                    ->pluck('name')
                    ->filter(fn ($name) => in_array((string) $name, $permissionNames, true))
                    ->values(),
            ],
        ]);
    }
}

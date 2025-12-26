<?php

namespace Modules\Branch\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Model;

class BranchPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();
        $permissions = [
            'access_branches',
            'create_branch',
            'edit_branches',
            'delete_branches',
            'switch_branch',
            'view_all_branches',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo([
            'access_branches',
            'create_branch',
            'edit_branches',
            'delete_branches',
            'switch_branch',
        ]);

        if (Permission::where('name', 'access_user_management')->exists()) {
            $adminRole->revokePermissionTo('access_user_management');
        }

        $ownerRole = Role::firstOrCreate(['name' => 'Owner']);
        $marketingRole = Role::firstOrCreate(['name' => 'Marketing']);

        $ownerRole->givePermissionTo([
            'switch_branch',
            'view_all_branches',
        ]);

        $marketingRole->givePermissionTo([
            'switch_branch',
            'view_all_branches',
        ]);
    }
}

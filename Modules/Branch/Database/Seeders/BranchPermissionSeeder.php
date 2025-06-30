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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        
        $role = Role::where([
            'name' => 'Admin'
        ])->first();

        $role->givePermissionTo($permissions);
        $role->revokePermissionTo('access_user_management');
    }
}

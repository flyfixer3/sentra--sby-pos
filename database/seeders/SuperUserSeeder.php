<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::updateOrCreate(
            ['email' => 'stefanusalvinwijaya@gmail.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make(12345678),
                'is_active' => 1,
            ]
        );

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);

        if (!$user->hasRole('Super Admin')) {
            $user->assignRole($superAdmin);
        }
    }
}

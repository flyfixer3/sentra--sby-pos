<?php

namespace Modules\Crm\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Modules\Branch\Entities\Branch;
use App\Models\User;

class TechnicianDemoSeeder extends Seeder
{
    public function run(): void
    {
        $techRole = Role::firstOrCreate(['name' => 'Technician', 'guard_name' => 'web']);
        $branches = Branch::query()->select('id','name')->orderBy('id')->get();
        if ($branches->isEmpty()) return;

        foreach ($branches as $branch) {
            for ($i=1; $i<=4; $i++) {
                $email = sprintf('tech%02d.branch%d@sentra.local', $i, (int)$branch->id);
                $name  = sprintf('Teknisi %02d %s', $i, (string)$branch->name);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('12345678'),
                        'username' => $email,
                        'phone' => '08123'.str_pad((string)($branch->id*100+$i), 6, '0', STR_PAD_LEFT),
                        'is_active' => 1,
                    ]
                );

                if (!$user->hasRole('Technician')) { $user->assignRole($techRole); }

                DB::table('branch_user')->updateOrInsert(
                    ['branch_id' => (int)$branch->id, 'user_id' => (int)$user->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}
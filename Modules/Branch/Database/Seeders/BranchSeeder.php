<?php

namespace Modules\Branch\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Branch\Entities\Branch;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        Branch::create([
            'name' => 'Cabang Bekasi',
            'address' => 'Jl. Raya Bekasi',
            'phone' => '0812-0000-0001',
        ]);

        Branch::create([
            'name' => 'Cabang Surabaya',
            'address' => 'Jl. Mayjend Sungkono',
            'phone' => '0821-0000-0002',
        ]);

        // $this->call("OthersTableSeeder");
    }
}

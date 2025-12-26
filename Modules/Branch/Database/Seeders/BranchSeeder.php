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

        $branches = [
            [
                'name' => 'Cabang Bekasi',
                'address' => 'Jl. Raya Bekasi',
                'phone' => '0812-0000-0001',
            ],
            [
                'name' => 'Cabang Surabaya',
                'address' => 'Jl. Mayjend Sungkono',
                'phone' => '0821-0000-0002',
            ],
            [
                'name' => 'Cabang Tangerang',
                'address' => 'Jl. Raya Tangerang',
                'phone' => '0813-0000-0003',
            ],
        ];

        foreach ($branches as $data) {
            Branch::updateOrCreate(
                ['name' => $data['name']],
                [
                    'address' => $data['address'],
                    'phone' => $data['phone'],
                ]
            );
        }
    }
}

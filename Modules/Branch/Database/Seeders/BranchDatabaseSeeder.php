<?php

namespace Modules\Branch\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;


class BranchDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();
        // $this->call("OthersTableSeeder");
        $this->call(BranchSeeder::class);
        $this->call(BranchPermissionSeeder::class);
    }
}

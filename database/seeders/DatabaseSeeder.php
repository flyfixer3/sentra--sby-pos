<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Branch\Database\Seeders\BranchDatabaseSeeder;
use Modules\Currency\Database\Seeders\CurrencyDatabaseSeeder;
use Modules\Setting\Database\Seeders\SettingDatabaseSeeder;
use Modules\User\Database\Seeders\PermissionsTableSeeder;
use Modules\Product\Database\Seeders\AccessoryDatabaseSeeder;
use Modules\Product\Database\Seeders\CategoryDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(PermissionsTableSeeder::class);
        $this->call(BranchDatabaseSeeder::class);
        $this->call(SuperUserSeeder::class);
        $this->call(AccessoryDatabaseSeeder::class);
        $this->call(CategoryDatabaseSeeder::class);
        $this->call(CurrencyDatabaseSeeder::class);
        $this->call(SettingDatabaseSeeder::class);
    }
}

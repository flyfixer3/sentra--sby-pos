<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Product\Entities\Accessory;

class AccessoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Accessory::create([
            'accessory_code' => '-',
            'accessory_name' => '-'
        ]);
        Accessory::create([
            'accessory_code' => 'X/MB/RS',
            'accessory_name' => 'X/MB/RS'
        ]);
        
        

        // $this->call("OthersTableSeeder");
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `customers` MODIFY `customer_email` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `customers` MODIFY `customer_phone` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `customers` MODIFY `city` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `customers` MODIFY `country` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `customers` MODIFY `address` TEXT NULL');

        DB::statement('ALTER TABLE `suppliers` MODIFY `supplier_email` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `suppliers` MODIFY `supplier_phone` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `suppliers` MODIFY `city` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `suppliers` MODIFY `country` VARCHAR(255) NULL');
        DB::statement('ALTER TABLE `suppliers` MODIFY `address` TEXT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE `customers` SET `customer_email` = COALESCE(`customer_email`, '')");
        DB::statement("UPDATE `customers` SET `customer_phone` = COALESCE(`customer_phone`, '')");
        DB::statement("UPDATE `customers` SET `city` = COALESCE(`city`, '')");
        DB::statement("UPDATE `customers` SET `country` = COALESCE(`country`, '')");
        DB::statement("UPDATE `customers` SET `address` = COALESCE(`address`, '')");

        DB::statement("UPDATE `suppliers` SET `supplier_email` = COALESCE(`supplier_email`, '')");
        DB::statement("UPDATE `suppliers` SET `supplier_phone` = COALESCE(`supplier_phone`, '')");
        DB::statement("UPDATE `suppliers` SET `city` = COALESCE(`city`, '')");
        DB::statement("UPDATE `suppliers` SET `country` = COALESCE(`country`, '')");
        DB::statement("UPDATE `suppliers` SET `address` = COALESCE(`address`, '')");

        DB::statement('ALTER TABLE `customers` MODIFY `customer_email` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `customers` MODIFY `customer_phone` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `customers` MODIFY `city` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `customers` MODIFY `country` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `customers` MODIFY `address` TEXT NOT NULL');

        DB::statement('ALTER TABLE `suppliers` MODIFY `supplier_email` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `suppliers` MODIFY `supplier_phone` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `suppliers` MODIFY `city` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `suppliers` MODIFY `country` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `suppliers` MODIFY `address` TEXT NOT NULL');
    }
};

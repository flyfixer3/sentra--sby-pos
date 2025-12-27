<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_defect_items', function (Blueprint $table) {
            $table->id();

            // lokasi & produk
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');

            // referensi asal (transfer, purchase, adjustment, dll)
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();

            // jumlah defect (TETAP termasuk stok)
            $table->integer('quantity');

            // info defect
            $table->string('defect_type');
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();

            // audit
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // index untuk performa
            $table->index(['branch_id', 'warehouse_id']);
            $table->index('product_id');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_defect_items');
    }
};

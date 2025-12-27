<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_damaged_items', function (Blueprint $table) {
            $table->id();

            // lokasi & produk
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');

            // referensi asal (transfer, purchase, dll)
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();

            // jumlah rusak
            $table->integer('quantity');

            // keterangan rusak
            $table->text('reason')->nullable();
            $table->string('photo_path')->nullable();

            // relasi mutation (WAJIB ADA)
            $table->unsignedBigInteger('mutation_in_id');
            $table->unsignedBigInteger('mutation_out_id');

            // audit
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            // index performa
            $table->index(['branch_id', 'warehouse_id']);
            $table->index('product_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index('mutation_in_id');
            $table->index('mutation_out_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_damaged_items');
    }
};

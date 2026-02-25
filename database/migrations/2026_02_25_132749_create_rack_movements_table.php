<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');

            // Source
            $table->unsignedBigInteger('from_warehouse_id');
            $table->unsignedBigInteger('from_rack_id');

            // Destination
            $table->unsignedBigInteger('to_warehouse_id');
            $table->unsignedBigInteger('to_rack_id');

            $table->string('reference')->unique();
            $table->date('date');
            $table->string('note')->nullable();

            // Untuk saat ini langsung completed (karena internal move = langsung eksekusi)
            $table->string('status')->default('completed');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'date']);
            $table->index(['from_warehouse_id', 'from_rack_id']);
            $table->index(['to_warehouse_id', 'to_rack_id']);

            // Foreign keys
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('from_rack_id')->references('id')->on('racks')->cascadeOnDelete();
            $table->foreign('to_rack_id')->references('id')->on('racks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_movements');
    }
};
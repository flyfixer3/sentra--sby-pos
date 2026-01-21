<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_deliveries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id'); // pengirim (branch aktif)
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('customer_id');

            $table->string('reference')->unique();
            $table->date('date');

            $table->unsignedBigInteger('warehouse_id'); // gudang keluar

            $table->string('status')->default('pending'); // pending|confirmed|cancelled
            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            // indexes
            $table->index(['branch_id', 'status']);
            $table->index(['customer_id']);
            $table->index(['quotation_id']);
            $table->index(['warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_deliveries');
    }
};

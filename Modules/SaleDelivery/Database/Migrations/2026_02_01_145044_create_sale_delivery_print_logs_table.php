<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_delivery_print_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_delivery_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('printed_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['sale_delivery_id']);

            // optional tapi bagus (kalau tabel users pakai id bigIncrements)
            $table->foreign('sale_delivery_id')
                ->references('id')->on('sale_deliveries')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_delivery_print_logs');
    }
};

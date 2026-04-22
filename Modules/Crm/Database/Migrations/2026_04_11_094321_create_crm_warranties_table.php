<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_warranties', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('service_order_id');

            $table->string('warranty_number', 50)->unique();
            $table->unsignedSmallInteger('coverage_months');
            $table->date('start_at');
            $table->date('end_at');

            $table->json('conditions')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique('service_order_id');
            $table->index('branch_id');
            $table->index('start_at');
            $table->index('end_at');

            // FKs
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('service_order_id')->references('id')->on('crm_service_orders')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_warranties');
    }
};
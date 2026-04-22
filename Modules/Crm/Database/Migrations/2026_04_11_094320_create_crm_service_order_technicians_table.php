<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_service_order_technicians', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('service_order_id');
            $table->unsignedBigInteger('user_id');

            $table->string('role', 32)->nullable();
            $table->string('status', 32)->default('assigned');

            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['service_order_id', 'user_id']);
            $table->index('branch_id');
            $table->index('user_id');
            $table->index('status');
            $table->index(['branch_id', 'status']);

            // FKs
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('service_order_id')->references('id')->on('crm_service_orders')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_service_order_technicians');
    }
};
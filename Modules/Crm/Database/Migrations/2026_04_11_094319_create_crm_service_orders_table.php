<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_service_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('customer_id');

            $table->string('spk_number', 50)->unique();
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('address_snapshot');
            $table->string('map_link_snapshot')->nullable();

            $table->string('status', 32)->default('scheduled');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->text('admin_note')->nullable();
            $table->text('technician_note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('branch_id');
            $table->index('customer_id');
            $table->index('lead_id');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index(['branch_id', 'status']);

            // FKs
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('lead_id')->references('id')->on('crm_leads')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_service_orders');
    }
};
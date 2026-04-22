<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_service_order_photos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('service_order_id');
            $table->unsignedBigInteger('media_id');

            $table->string('phase', 16); // before|after|other
            $table->string('caption')->nullable();

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('uploaded_at');

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique('media_id');
            $table->index('service_order_id');
            $table->index('branch_id');
            $table->index('phase');
            $table->index('uploaded_by');

            // FKs
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('service_order_id')->references('id')->on('crm_service_orders')->cascadeOnDelete();
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_service_order_photos');
    }
};
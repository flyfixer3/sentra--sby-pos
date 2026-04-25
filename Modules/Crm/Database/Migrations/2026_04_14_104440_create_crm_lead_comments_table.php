<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_lead_comments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('user_id');

            $table->text('content');
            $table->json('mentions')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('lead_id');
            $table->index('branch_id');
            $table->index('user_id');

            // Foreign keys (align with CRM style)
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('lead_id')->references('id')->on('crm_leads')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_comments');
    }
};
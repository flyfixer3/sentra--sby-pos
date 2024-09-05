<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounting_posting_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('accounting_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_subaccount_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_posting_snapshots');
    }
};

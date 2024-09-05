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
        Schema::create('accounting_transaction_details', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('accounting_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_subaccount_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->enum('type', ['credit', 'debit']);

            $table->unique(['accounting_transaction_id', 'accounting_subaccount_id', 'type'], 'unique_transaction_detail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_transaction_details');
    }
};

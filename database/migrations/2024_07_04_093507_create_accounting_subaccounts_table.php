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
        Schema::create('accounting_subaccounts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('accounting_account_id')->constrained()->cascadeOnUpdate();
            $table->string('subaccount_number');
            $table->string('subaccount_name');
            $table->string('description');
            $table->integer('total_debit')->default(0);
            $table->integer('total_credit')->default(0);

            $table->unique(['accounting_account_id', 'subaccount_number'], 'unique_subaccount_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subaccounts');
    }
};

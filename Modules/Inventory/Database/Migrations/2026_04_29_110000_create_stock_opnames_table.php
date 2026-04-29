<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockOpnamesTable extends Migration
{
    public function up()
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('reference')->unique();
            $table->date('opname_date');
            $table->string('title');
            $table->string('status', 20)->default('draft');
            $table->text('note')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('adjustment_id')->nullable()->constrained('adjustments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_opnames');
    }
}

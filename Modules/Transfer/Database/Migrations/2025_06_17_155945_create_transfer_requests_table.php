<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransferRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->date('date');
            
            // Gudang asal pengirim
            $table->unsignedBigInteger('from_warehouse_id');
            
            // Cabang tujuan WAJIB diketahui saat pembuatan transfer
            $table->unsignedBigInteger('to_branch_id');

            // Gudang tujuan hanya diketahui saat konfirmasi oleh penerima
            $table->unsignedBigInteger('to_warehouse_id')->nullable();

            $table->text('note')->nullable();
            
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');

            // Cabang pengirim
            $table->unsignedBigInteger('branch_id');

            // Pembuat transfer
            $table->unsignedBigInteger('created_by');
            
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_requests');
    }
}

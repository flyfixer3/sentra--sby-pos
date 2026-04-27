<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerVehiclesTable extends Migration
{
    public function up()
    {
        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('vehicle_name')->nullable();
            $table->string('car_plate');
            $table->string('chassis_number')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_vehicles');
    }
}

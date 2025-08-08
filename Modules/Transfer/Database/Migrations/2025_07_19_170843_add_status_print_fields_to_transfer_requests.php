<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatusPrintFieldsToTransferRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            // Tambahkan kolom status jika belum ada
            if (!Schema::hasColumn('transfer_requests', 'status')) {
                $table->enum('status', ['pending', 'shipped', 'confirmed', 'completed'])
                    ->default('pending')->after('note');
            }

            // Tambahkan printed_at jika belum ada
            if (!Schema::hasColumn('transfer_requests', 'printed_at')) {
                $table->timestamp('printed_at')->nullable()->after('status');
            }

            // Tambahkan printed_by jika belum ada
            if (!Schema::hasColumn('transfer_requests', 'printed_by')) {
                $table->unsignedBigInteger('printed_by')->nullable()->after('printed_at');
                $table->foreign('printed_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('', function (Blueprint $table) {

        });
    }
}

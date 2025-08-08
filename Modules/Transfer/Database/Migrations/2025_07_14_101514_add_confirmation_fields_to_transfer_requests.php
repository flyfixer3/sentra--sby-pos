<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddConfirmationFieldsToTransferRequests extends Migration
{
    public function up()
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->string('delivery_proof_path')->nullable()->after('note');
            $table->unsignedBigInteger('confirmed_by')->nullable()->after('delivery_proof_path');
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['delivery_proof_path', 'confirmed_by', 'confirmed_at']);
        });
    }
}

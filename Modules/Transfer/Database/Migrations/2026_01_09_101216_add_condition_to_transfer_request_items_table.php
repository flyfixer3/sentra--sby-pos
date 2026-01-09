<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transfer_request_items', function (Blueprint $table) {
            $table->string('condition', 20)->default('good')->after('product_id');
            // good | defect | damaged
        });
    }

    public function down(): void
    {
        Schema::table('transfer_request_items', function (Blueprint $table) {
            $table->dropColumn('condition');
        });
    }
};

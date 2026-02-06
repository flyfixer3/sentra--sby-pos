<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('transfer_request_items', 'from_rack_id')) {
                $table->unsignedBigInteger('from_rack_id')->nullable()->after('product_id');
                $table->index(['from_rack_id']);
            }

            if (!Schema::hasColumn('transfer_request_items', 'to_rack_id')) {
                $table->unsignedBigInteger('to_rack_id')->nullable()->after('condition');
                $table->index(['to_rack_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('transfer_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('transfer_request_items', 'from_rack_id')) {
                $table->dropIndex(['from_rack_id']);
                $table->dropColumn('from_rack_id');
            }

            if (Schema::hasColumn('transfer_request_items', 'to_rack_id')) {
                $table->dropIndex(['to_rack_id']);
                $table->dropColumn('to_rack_id');
            }
        });
    }
};

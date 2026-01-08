<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_damaged_items', function (Blueprint $table) {
            // type untuk bedain missing vs damaged
            $table->string('damage_type', 20)->default('damaged')->after('quantity');

            // sesuai requirement kamu
            $table->string('cause', 20)->nullable()->after('photo_path'); // transfer|employee|supplier|unknown
            $table->unsignedBigInteger('responsible_user_id')->nullable()->after('cause');
            $table->string('resolution_status', 20)->default('pending')->after('responsible_user_id'); // pending|resolved|compensated|waived
            $table->text('resolution_note')->nullable()->after('resolution_status');

            $table->index(['damage_type']);
            $table->index(['resolution_status']);
            $table->index(['cause']);
            $table->index(['responsible_user_id']);
        });

        // FK (optional, tapi bagus)
        Schema::table('product_damaged_items', function (Blueprint $table) {
            $table->foreign('responsible_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        // Existing rows dianggap damaged
        DB::table('product_damaged_items')
            ->whereNull('damage_type')
            ->update(['damage_type' => 'damaged']);
    }

    public function down(): void
    {
        Schema::table('product_damaged_items', function (Blueprint $table) {
            $table->dropForeign(['responsible_user_id']);
            $table->dropIndex(['damage_type']);
            $table->dropIndex(['resolution_status']);
            $table->dropIndex(['cause']);
            $table->dropIndex(['responsible_user_id']);

            $table->dropColumn([
                'damage_type',
                'cause',
                'responsible_user_id',
                'resolution_status',
                'resolution_note',
            ]);
        });
    }
};

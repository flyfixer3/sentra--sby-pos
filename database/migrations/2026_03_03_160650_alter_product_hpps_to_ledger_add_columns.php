<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_hpps', function (Blueprint $table) {
            // kapan snapshot ini "berlaku"
            if (!Schema::hasColumn('product_hpps', 'effective_at')) {
                $table->dateTime('effective_at')->nullable()->after('product_id');
            }

            // sumber perubahan (optional tapi berguna banget untuk audit)
            if (!Schema::hasColumn('product_hpps', 'source_type')) {
                $table->string('source_type', 100)->nullable()->after('effective_at');
            }
            if (!Schema::hasColumn('product_hpps', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            // metadata perhitungan (optional tapi bagus untuk trace)
            if (!Schema::hasColumn('product_hpps', 'incoming_qty')) {
                $table->integer('incoming_qty')->default(0)->after('last_purchase_cost');
            }
            if (!Schema::hasColumn('product_hpps', 'incoming_unit_cost')) {
                $table->decimal('incoming_unit_cost', 18, 2)->default(0)->after('incoming_qty');
            }
            if (!Schema::hasColumn('product_hpps', 'old_qty')) {
                $table->integer('old_qty')->default(0)->after('incoming_unit_cost');
            }
            if (!Schema::hasColumn('product_hpps', 'old_avg_cost')) {
                $table->decimal('old_avg_cost', 18, 2)->default(0)->after('old_qty');
            }
            if (!Schema::hasColumn('product_hpps', 'new_avg_cost')) {
                $table->decimal('new_avg_cost', 18, 2)->default(0)->after('old_avg_cost');
            }

            // index untuk query "latest as-of"
            $table->index(['branch_id', 'product_id', 'effective_at', 'id'], 'idx_hpp_ledger_lookup');
            $table->index(['source_type', 'source_id'], 'idx_hpp_ledger_source');
        });

        /**
         * Kalau sebelumnya ada UNIQUE (branch_id, product_id),
         * itu HARUS dihapus supaya bisa multiple rows.
         *
         * Nama index unique tiap project bisa beda, jadi:
         * - cek di phpMyAdmin/SHOW INDEX
         * - drop sesuai nama yang ada
         *
         * Contoh (kalau namanya product_hpps_branch_id_product_id_unique):
         * Schema::table('product_hpps', function (Blueprint $table) {
         *     $table->dropUnique('product_hpps_branch_id_product_id_unique');
         * });
         */
    }

    public function down(): void
    {
        Schema::table('product_hpps', function (Blueprint $table) {
            // jangan drop index custom kalau kamu gak yakin - tapi ini contoh lengkap:
            $table->dropIndex('idx_hpp_ledger_lookup');
            $table->dropIndex('idx_hpp_ledger_source');

            $table->dropColumn([
                'effective_at',
                'source_type',
                'source_id',
                'incoming_qty',
                'incoming_unit_cost',
                'old_qty',
                'old_avg_cost',
                'new_avg_cost',
            ]);
        });
    }
};
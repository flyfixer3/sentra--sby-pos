<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        $tables = ['sales', 'products','purchases'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            });
        }
    }

    public function down()
    {
        $tables = ['sales', 'products','purchases'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign([$table . '_created_by_foreign']);
                $table->dropForeign([$table . '_updated_by_foreign']);
                $table->dropColumn(['created_by', 'updated_by']);
            });
        }
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adjusted_products', function (Blueprint $table) {
            if (!Schema::hasColumn('adjusted_products', 'note')) {
                $table->string('note', 255)->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('adjusted_products', function (Blueprint $table) {
            if (Schema::hasColumn('adjusted_products', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};

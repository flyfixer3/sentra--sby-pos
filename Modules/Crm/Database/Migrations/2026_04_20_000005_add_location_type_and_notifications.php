<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_leads', 'install_location_type')) {
                $table->string('install_location_type', 50)->default('workshop')->after('stock_status');
                $table->index('install_location_type');
            }
        });

        if (!Schema::hasTable('crm_notifications')) {
            Schema::create('crm_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('lead_id')->nullable()->index();
                $table->string('type', 50)->index();
                $table->string('title');
                $table->text('message')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notifications');

        Schema::table('crm_leads', function (Blueprint $table) {
            if (Schema::hasColumn('crm_leads', 'install_location_type')) {
                $table->dropIndex(['install_location_type']);
                $table->dropColumn('install_location_type');
            }
        });
    }
};

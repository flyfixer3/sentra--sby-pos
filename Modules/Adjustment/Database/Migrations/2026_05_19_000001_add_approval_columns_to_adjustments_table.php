<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddApprovalColumnsToAdjustmentsTable extends Migration
{
    public function up()
    {
        Schema::table('adjustments', function (Blueprint $table) {
            if (!Schema::hasColumn('adjustments', 'status')) {
                $table->string('status', 30)->default('approved')->after('warehouse_id')->index();
            }
            if (!Schema::hasColumn('adjustments', 'request_type')) {
                $table->string('request_type', 80)->nullable()->after('status')->index();
            }
            if (!Schema::hasColumn('adjustments', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable()->after('created_by')->index();
            }
            if (!Schema::hasColumn('adjustments', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (!Schema::hasColumn('adjustments', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('submitted_at')->index();
            }
            if (!Schema::hasColumn('adjustments', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('adjustments', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at')->index();
            }
            if (!Schema::hasColumn('adjustments', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (!Schema::hasColumn('adjustments', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('rejected_at');
            }
            if (!Schema::hasColumn('adjustments', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_note');
            }
            if (!Schema::hasColumn('adjustments', 'executed_by')) {
                $table->unsignedBigInteger('executed_by')->nullable()->after('rejection_reason')->index();
            }
            if (!Schema::hasColumn('adjustments', 'executed_at')) {
                $table->timestamp('executed_at')->nullable()->after('executed_by');
            }
            if (!Schema::hasColumn('adjustments', 'payload')) {
                $table->json('payload')->nullable()->after('executed_at');
            }
        });

        DB::table('adjustments')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'approved']);

        DB::table('adjustments')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('adjustments')
                    ->where('id', $row->id)
                    ->update([
                        'status' => $row->status ?: 'approved',
                        'submitted_by' => $row->submitted_by ?: ($row->created_by ?? null),
                        'submitted_at' => $row->submitted_at ?: ($row->created_at ?? null),
                        'executed_by' => $row->executed_by ?: ($row->created_by ?? null),
                        'executed_at' => $row->executed_at ?: ($row->created_at ?? null),
                        'approved_by' => $row->approved_by ?: ($row->created_by ?? null),
                        'approved_at' => $row->approved_at ?: ($row->created_at ?? null),
                    ]);
            }
        });
    }

    public function down()
    {
        Schema::table('adjustments', function (Blueprint $table) {
            $columns = [
                'payload',
                'executed_at',
                'executed_by',
                'rejection_reason',
                'approval_note',
                'rejected_at',
                'rejected_by',
                'approved_at',
                'approved_by',
                'submitted_at',
                'submitted_by',
                'request_type',
                'status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('adjustments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('module');
            $table->string('event');
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('accounting_subaccount_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('entity_id')->references('id')->on('entities')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('accounting_subaccount_id')->references('id')->on('accounting_subaccounts')->cascadeOnDelete();
            $table->unique(['entity_id', 'branch_id', 'module', 'event'], 'accounting_mapping_scope_unique');
        });

        $defaults = [
            ['module' => 'sale', 'event' => 'receivable', 'label' => 'Sale Receivable', 'description' => 'Debit account used when creating a sale invoice receivable.', 'subaccount_number' => '1-10100'],
            ['module' => 'sale', 'event' => 'revenue', 'label' => 'Sale Revenue', 'description' => 'Credit revenue account used when creating a sale invoice.', 'subaccount_number' => '4-40000'],
            ['module' => 'sale', 'event' => 'cogs', 'label' => 'Sale COGS', 'description' => 'Debit cost of goods sold account used when stock cost exists.', 'subaccount_number' => '5-50000'],
            ['module' => 'sale', 'event' => 'inventory', 'label' => 'Sale Inventory', 'description' => 'Credit inventory account reduced by sale cost.', 'subaccount_number' => '1-10200'],
            ['module' => 'sale_payment', 'event' => 'receivable', 'label' => 'Sale Payment Receivable', 'description' => 'Receivable account relieved when customer payment is posted.', 'subaccount_number' => '1-10100'],
            ['module' => 'purchase_payment', 'event' => 'inventory', 'label' => 'Purchase Payment Inventory', 'description' => 'Inventory account used when purchase payment is posted.', 'subaccount_number' => '1-10200'],
        ];

        foreach ($defaults as $mapping) {
            $subaccountId = DB::table('accounting_subaccounts')
                ->where('subaccount_number', $mapping['subaccount_number'])
                ->value('id');

            if ($subaccountId === null) {
                continue;
            }

            DB::table('accounting_account_mappings')->updateOrInsert(
                [
                    'entity_id' => null,
                    'branch_id' => null,
                    'module' => $mapping['module'],
                    'event' => $mapping['event'],
                ],
                [
                    'label' => $mapping['label'],
                    'description' => $mapping['description'],
                    'accounting_subaccount_id' => $subaccountId,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_account_mappings');
    }
};

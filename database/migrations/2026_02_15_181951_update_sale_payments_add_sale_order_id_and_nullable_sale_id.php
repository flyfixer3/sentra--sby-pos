<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Tambah kolom sale_order_id (aman pakai schema builder)
        Schema::table('sale_payments', function ($table) {
            if (!Schema::hasColumn('sale_payments', 'sale_order_id')) {
                $table->unsignedBigInteger('sale_order_id')->nullable()->after('sale_id');
            }
        });

        // 2) Drop FK sale_id kalau ada (pakai SQL, tidak tergantung nama constraint)
        //    Ini penting karena kita mau ubah kolom sale_id jadi NULLABLE.
        $this->dropForeignKeyIfExists('sale_payments', 'sale_id');

        // 3) Ubah sale_id jadi NULLABLE pakai SQL biasa (tanpa doctrine/dbal)
        //    MySQL: MODIFY COLUMN
        DB::statement("ALTER TABLE `sale_payments` MODIFY `sale_id` BIGINT(20) UNSIGNED NULL");

        // 4) Add FK baru untuk sale_id (nullable) -> ON DELETE SET NULL
        //    Kita tentuin nama constraint biar konsisten.
        $this->addForeignKeyIfNotExists(
            'sale_payments',
            'sale_payments_sale_id_fk',
            'sale_id',
            'sales',
            'id',
            'SET NULL'
        );

        // 5) Add FK untuk sale_order_id -> ON DELETE SET NULL
        $this->addForeignKeyIfNotExists(
            'sale_payments',
            'sale_payments_sale_order_id_fk',
            'sale_order_id',
            'sale_orders',
            'id',
            'SET NULL'
        );

        // 6) Index sale_order_id (kalau belum ada)
        $this->addIndexIfNotExists('sale_payments', 'sale_payments_sale_order_id_index', ['sale_order_id']);
    }

    public function down(): void
    {
        // Drop FK dulu
        $this->dropForeignKeyByNameIfExists('sale_payments', 'sale_payments_sale_order_id_fk');
        $this->dropForeignKeyByNameIfExists('sale_payments', 'sale_payments_sale_id_fk');

        // Drop index
        $this->dropIndexByNameIfExists('sale_payments', 'sale_payments_sale_order_id_index');

        // Drop column sale_order_id
        if (Schema::hasColumn('sale_payments', 'sale_order_id')) {
            Schema::table('sale_payments', function ($table) {
                $table->dropColumn('sale_order_id');
            });
        }

        // Ubah sale_id kembali NOT NULL
        DB::statement("ALTER TABLE `sale_payments` MODIFY `sale_id` BIGINT(20) UNSIGNED NOT NULL");

        // Balikin FK lama (biasanya CASCADE atau RESTRICT)
        // Aku set CASCADE ON DELETE supaya mirip pola umum sales payment.
        $this->addForeignKeyIfNotExists(
            'sale_payments',
            'sale_payments_sale_id_fk',
            'sale_id',
            'sales',
            'id',
            'CASCADE'
        );
    }

    /**
     * Drop foreign key on a column if it exists (by scanning information_schema).
     */
    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $dbName = DB::getDatabaseName();

        $rows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$dbName, $table, $column]);

        foreach ($rows as $r) {
            $constraint = $r->CONSTRAINT_NAME;
            // drop fk
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }

    /**
     * Add foreign key with explicit name if not exists.
     */
    private function addForeignKeyIfNotExists(
        string $table,
        string $fkName,
        string $column,
        string $refTable,
        string $refColumn,
        string $onDelete
    ): void {
        $dbName = DB::getDatabaseName();

        $exists = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$dbName, $table, $fkName]);

        if ((int) ($exists->c ?? 0) === 0) {
            DB::statement("
                ALTER TABLE `{$table}`
                ADD CONSTRAINT `{$fkName}`
                FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}`(`{$refColumn}`)
                ON DELETE {$onDelete}
            ");
        }
    }

    /**
     * Add index with explicit name if not exists.
     */
    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        $dbName = DB::getDatabaseName();

        $exists = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ", [$dbName, $table, $indexName]);

        if ((int) ($exists->c ?? 0) === 0) {
            $cols = implode('`,`', $columns);
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$cols}`)");
        }
    }

    private function dropForeignKeyByNameIfExists(string $table, string $fkName): void
    {
        $dbName = DB::getDatabaseName();

        $exists = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$dbName, $table, $fkName]);

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
        }
    }

    private function dropIndexByNameIfExists(string $table, string $indexName): void
    {
        $dbName = DB::getDatabaseName();

        $exists = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ", [$dbName, $table, $indexName]);

        if ((int) ($exists->c ?? 0) > 0) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }
};

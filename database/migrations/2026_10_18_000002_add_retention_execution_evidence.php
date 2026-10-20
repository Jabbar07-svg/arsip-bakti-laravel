<?php

use App\Support\LocalDatabaseMigrationGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = LocalDatabaseMigrationGuard::assertApplicationDatabase();
        if (! Schema::hasTable('disposition_requests') || ! Schema::hasTable('jadwal_retensi')) {
            throw new RuntimeException('Tabel legacy disposition_requests dan jadwal_retensi harus tersedia sebelum migrasi retensi.');
        }

        Schema::table('disposition_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('disposition_requests', 'executed_by')) {
                $table->unsignedInteger('executed_by')->nullable();
            }
            if (! Schema::hasColumn('disposition_requests', 'execution_date')) {
                $table->date('execution_date')->nullable();
            }
            if (! Schema::hasColumn('disposition_requests', 'destination')) {
                $table->string('destination', 255)->nullable();
            }
            if (! Schema::hasColumn('disposition_requests', 'handover_recipient')) {
                $table->string('handover_recipient', 255)->nullable();
            }
            if (! Schema::hasColumn('disposition_requests', 'evidence_path')) {
                $table->string('evidence_path', 500)->nullable();
            }
        });

        $hasExecutorForeignKey = $connection->table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->where('TABLE_NAME', 'disposition_requests')
            ->where('COLUMN_NAME', 'executed_by')
            ->where('REFERENCED_TABLE_NAME', 'users')
            ->exists();
        if (! $hasExecutorForeignKey) {
            Schema::table('disposition_requests', function (Blueprint $table): void {
                $table->foreign('executed_by', 'fk_disposition_executor')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        foreach (['disposition_requests' => 'action', 'jadwal_retensi' => 'final_action'] as $table => $column) {
            $columnType = DB::selectOne(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            )?->COLUMN_TYPE;
            if (is_string($columnType) && str_starts_with($columnType, 'enum(') && ! str_contains($columnType, 'Pindahkan Arsip Inaktif')) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` ENUM('Musnahkan','Pindahkan Arsip Inaktif','Permanen','Serah ke ANRI') NOT NULL");
            }
        }
    }

    public function down(): void
    {
        LocalDatabaseMigrationGuard::assertApplicationDatabase();
    }
};

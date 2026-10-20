<?php

use App\Support\LocalDatabaseMigrationGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        LocalDatabaseMigrationGuard::assertApplicationDatabase();

        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama', 150);
            $table->string('profile_image')->nullable();
            $table->string('username', 80)->unique('uq_users_username');
            $table->string('password');
            $table->enum('role', [
                'Super Admin',
                'Petugas Gudang',
                'Admin Unit/Petugas Arsip',
                'Verifikator',
                'Pimpinan/Approver',
                'Customer',
                'Pengguna Umum',
            ])->default('Customer');
            $table->string('division', 150)->nullable();
            $table->enum('status', ['Aktif', 'Nonaktif'])->default('Aktif');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        LocalDatabaseMigrationGuard::assertApplicationDatabase();

        // Preserve user data during rollback.
    }
};

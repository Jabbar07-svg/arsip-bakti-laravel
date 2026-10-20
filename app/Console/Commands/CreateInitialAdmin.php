<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LocalDatabaseMigrationGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CreateInitialAdmin extends Command
{
    protected $signature = 'app:create-initial-admin';

    protected $description = 'Create the first Admin account in the local application database';

    public function handle(): int
    {
        $connection = LocalDatabaseMigrationGuard::assertApplicationDatabase();

        $selectedDatabase = $connection->selectOne('SELECT DATABASE() AS database_name');
        if (strtolower((string) ($selectedDatabase->database_name ?? '')) !== 'arsip_bakti_local') {
            throw new RuntimeException('Admin bootstrap is restricted to the arsip_bakti_local database.');
        }

        $lockName = 'arsip_bakti_local_initial_admin';
        $lock = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            $this->components->error('Another initial Admin setup is running; no account was changed.');

            return self::FAILURE;
        }

        try {
            if (User::query()->exists()) {
                $this->components->error('The users table is not empty; initial Admin setup is disabled.');

                return self::FAILURE;
            }

            $name = trim((string) $this->ask('Nama Admin'));
            $username = trim((string) $this->ask('Username'));
            $password = (string) $this->secret('Password (minimum 12 karakter)');
            $passwordConfirmation = (string) $this->secret('Ulangi password');

            if (
                $name === ''
                || mb_strlen($name) > 150
                || ! preg_match('/\A[a-zA-Z0-9_-]{1,80}\z/', $username)
                || mb_strlen($password) < 12
                || ! hash_equals($password, $passwordConfirmation)
            ) {
                $this->components->error('Input tidak valid. Akun tidak dibuat.');

                return self::FAILURE;
            }

            DB::transaction(function () use ($name, $username, $password): void {
                if (User::query()->exists()) {
                    throw new RuntimeException('The users table is no longer empty; no account was created.');
                }

                if (User::query()->where('username', $username)->exists()) {
                    throw new RuntimeException('Username is already in use; no account was created.');
                }

                User::query()->create([
                    'nama' => $name,
                    'username' => $username,
                    'password' => Hash::make($password),
                    'role' => 'Admin',
                    'division' => null,
                    'status' => 'Aktif',
                ]);
            });

            $this->components->info('Admin awal berhasil dibuat. Password hanya disimpan sebagai hash.');

            return self::SUCCESS;
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }
}

<?php

namespace App\Providers;

use App\Support\LocalDatabaseMigrationGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $destructiveCommands = [
            'migrate:fresh',
            'migrate:refresh',
            'migrate:reset',
            'db:wipe',
        ];
        $migrationCommands = [
            'migrate',
            'migrate:install',
            'migrate:rollback',
        ];
        $arguments = $_SERVER['argv'] ?? [];

        foreach ($arguments as $argument) {
            if (in_array($argument, $destructiveCommands, true)) {
                throw new \RuntimeException('Destructive database reset commands are disabled for this application.');
            }

            if (in_array($argument, $migrationCommands, true)) {
                LocalDatabaseMigrationGuard::assertApplicationDatabase();

                return;
            }
        }
    }
}

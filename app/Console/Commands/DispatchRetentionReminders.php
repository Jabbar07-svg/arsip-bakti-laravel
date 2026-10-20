<?php

namespace App\Console\Commands;

use App\Support\RetentionReminderDispatcher;
use Illuminate\Console\Command;

class DispatchRetentionReminders extends Command
{
    protected $signature = 'retention:dispatch-reminders';

    protected $description = 'Create due retention reminders and notify the active Admin';

    public function handle(RetentionReminderDispatcher $dispatcher): int
    {
        $created = $dispatcher->dispatch();
        $this->info("Pengingat retensi diproses. Pengingat baru: {$created}.");

        return self::SUCCESS;
    }
}

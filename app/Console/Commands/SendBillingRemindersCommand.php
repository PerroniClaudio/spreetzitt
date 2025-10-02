<?php

namespace App\Console\Commands;

use App\Jobs\SendBillingReminders;
use Illuminate\Console\Command;

class SendBillingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send billing reminders to superadmins with billing counters';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Inviando reminder di fatturazione...');

        dispatch(new SendBillingReminders);

        $this->info('Job SendBillingReminders è stato accodato con successo!');
    }
}

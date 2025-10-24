<?php

namespace App\Console\Commands;

use App\Jobs\MigrateUserCompanyToPivot;
use Illuminate\Console\Command;

class MigrateUserCompanyToPivotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:user-company-pivot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra i company_id degli utenti nella tabella pivot company_user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        dispatch(new MigrateUserCompanyToPivot);
        $this->info('Job di migrazione lanciato!');
    }
}

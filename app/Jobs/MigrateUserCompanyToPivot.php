<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MigrateUserCompanyToPivot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Prendi tutti gli utenti che hanno un company_id valorizzato
        $users = User::whereNotNull('company_id')->get();
        foreach ($users as $user) {
            // Inserisci nella tabella pivot se non esiste già
            DB::table('company_user')->updateOrInsert([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
            ]);
        }
    }
}

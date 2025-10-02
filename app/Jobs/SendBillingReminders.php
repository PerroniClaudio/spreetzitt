<?php

namespace App\Jobs;

use App\Mail\BillingReminderMail;
use App\Models\TicketStage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendBillingReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Recupera tutti gli utenti superadmin
        $superadmins = User::where('is_superadmin', 1)->get();

        if ($superadmins->isEmpty()) {
            return; // Non ci sono superadmin, esce
        }

        // Recupera i contatori di fatturazione usando la stessa logica di adminGroupsBillingCounters
        $counters = $this->getBillingCounters();

        // Invia la mail a tutti i superadmin
        foreach ($superadmins as $superadmin) {
            $mail = new BillingReminderMail($counters);
            
            Mail::to($superadmin->email)->send($mail);
        }
    }

    /**
     * Recupera i contatori di fatturazione per i superadmin
     */
    private function getBillingCounters(): array
    {
        $closedStageId = TicketStage::where('system_key', 'closed')->value('id');

        // Singola query ottimizzata per tutti i contatori
        $result = DB::selectOne('
            SELECT 
                COUNT(CASE WHEN is_billable IS NULL THEN 1 END) as billable_missing,
                COUNT(CASE WHEN is_billing_validated = 0 THEN 1 END) as billing_validation_missing,
                COUNT(CASE WHEN is_billable = 1 AND is_billing_validated = 1 AND is_billed = 0 THEN 1 END) as billed_missing,
                COUNT(CASE WHEN is_billed = 1 AND bill_identification IS NULL THEN 1 END) as billed_bill_identification_missing,
                COUNT(CASE WHEN is_billed = 1 AND bill_date IS NULL THEN 1 END) as billed_bill_date_missing,
                COUNT(CASE WHEN is_billable IS NULL AND stage_id != ? THEN 1 END) as open_billable_missing,
                COUNT(CASE WHEN is_billing_validated = 0 AND stage_id != ? THEN 1 END) as open_billing_validation_missing,
                COUNT(CASE WHEN is_billable = 1 AND is_billing_validated = 1 AND is_billed = 0 AND stage_id != ? THEN 1 END) as open_billed_missing,
                COUNT(CASE WHEN is_billed = 1 AND bill_identification IS NULL AND stage_id != ? THEN 1 END) as open_billed_bill_identification_missing,
                COUNT(CASE WHEN is_billed = 1 AND bill_date IS NULL AND stage_id != ? THEN 1 END) as open_billed_bill_date_missing
            FROM tickets
        ', [$closedStageId, $closedStageId, $closedStageId, $closedStageId, $closedStageId]);

        return [
            'billable_missing' => $result->billable_missing,
            'billing_validation_missing' => $result->billing_validation_missing,
            'billed_missing' => $result->billed_missing,
            'billed_bill_identification_missing' => $result->billed_bill_identification_missing,
            'billed_bill_date_missing' => $result->billed_bill_date_missing,
            'open_billable_missing' => $result->open_billable_missing,
            'open_billing_validation_missing' => $result->open_billing_validation_missing,
            'open_billed_missing' => $result->open_billed_missing,
            'open_billed_bill_identification_missing' => $result->open_billed_bill_identification_missing,
            'open_billed_bill_date_missing' => $result->open_billed_bill_date_missing,
        ];
    }
}

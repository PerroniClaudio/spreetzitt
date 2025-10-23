<?php

namespace App\Jobs;

use App\Mail\HourlyCostExpirationNotification;
use App\Models\Company;
use App\Models\TicketType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckHourlyCostExpiration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

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
        try {
            // Usa solo le date (senza orario) per confrontare con il campo date del DB
            $twoWeeksFromNow = Carbon::now()->addWeeks(2)->format('Y-m-d');
            $oneMonthFromNow = Carbon::now()->addMonth()->format('Y-m-d');
            $mailToAddress = config('mail.to_address');

            Log::channel('stderr')->info('CheckHourlyCostExpiration job started', [
                'two_weeks_from_now' => $twoWeeksFromNow,
                'one_month_from_now' => $oneMonthFromNow,
                'mail_to_address' => $mailToAddress ? 'configurato' : 'non configurato'
            ]);

            if (!$mailToAddress) {
                Log::channel('stderr')->warning('MAIL_TO_ADDRESS non configurato, impossibile inviare notifiche di scadenza costi orari');
                return;
            }

            // Trova tutte le aziende che hanno tipi di ticket con costi in scadenza nelle prossime 2 settimane
            // (incluse quelle già scadute)
            $companiesWithExpiring = Company::whereHas('ticketTypes', function ($query) use ($twoWeeksFromNow) {
                $query->where('hourly_cost_expires_at', '<=', $twoWeeksFromNow)
                      ->where('is_deleted', false)
                      ->whereNotNull('hourly_cost_expires_at');
            })->get();

            Log::channel('stderr')->info('Companies with expiring hourly costs found', [
                'count' => $companiesWithExpiring->count(),
                'company_ids' => $companiesWithExpiring->pluck('id')->toArray()
            ]);

            foreach ($companiesWithExpiring as $company) {
                // Per ogni azienda, raccogli tutti i tipi di ticket che scadono nel prossimo mese
                // (incluse quelle già scadute)
                $expiringTicketTypes = $company->ticketTypes()
                    ->where('hourly_cost_expires_at', '<=', $oneMonthFromNow)
                    ->where('is_deleted', false)
                    ->whereNotNull('hourly_cost_expires_at')
                    ->with('category')
                    ->orderBy('hourly_cost_expires_at', 'asc')
                    ->get();

                // Conta i tipi di ticket con data di scadenza null per questa azienda
                // Tipi di ticket senza data di scadenza con prezzo = 0
                $nullExpirationZeroPrice = $company->ticketTypes()
                    ->whereNull('hourly_cost_expires_at')
                    ->where('is_deleted', false)
                    ->where(function($query) {
                        $query->whereNull('hourly_cost')->orWhere('hourly_cost', 0);
                    })
                    ->count();

                // Tipi di ticket senza data di scadenza con prezzo > 0
                $nullExpirationWithPrice = $company->ticketTypes()
                    ->whereNull('hourly_cost_expires_at')
                    ->where('is_deleted', false)
                    ->whereNotNull('hourly_cost')
                    ->where('hourly_cost', '>', 0)
                    ->count();

                Log::channel('stderr')->info('Expiring ticket types for company', [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'expiring_count' => $expiringTicketTypes->count(),
                    'ticket_type_ids' => $expiringTicketTypes->pluck('id')->toArray(),
                    'null_expiration_zero_price' => $nullExpirationZeroPrice,
                    'null_expiration_with_price' => $nullExpirationWithPrice
                ]);

                if ($expiringTicketTypes->isNotEmpty()) {
                    // Invia email per questa azienda
                    Mail::to($mailToAddress)->send(
                        new HourlyCostExpirationNotification(
                            $company, 
                            $expiringTicketTypes, 
                            $nullExpirationZeroPrice, 
                            $nullExpirationWithPrice
                        )
                    );

                    Log::channel('stderr')->info("Inviata notifica scadenza costi orari per azienda: {$company->name} ({$expiringTicketTypes->count()} tipi di ticket)");
                }
            }

            Log::channel('stderr')->info('CheckHourlyCostExpiration job completed successfully');

        } catch (\Exception $e) {
            Log::channel('stderr')->error('CheckHourlyCostExpiration job failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw per far fallire il job
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('stderr')->error('CheckHourlyCostExpiration job failed permanently', [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Mail\ReportUpdatedNotification;
use App\Models\TicketReportPdfExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPdfReportUpdatedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected $report;

    /**
     * Create a new job instance.
     */
    public function __construct(TicketReportPdfExport $report)
    {
        $this->report = $report;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Se l'azienda non esiste, logga e termina
            $company = $this->report->company;
            if (! $company) {
                throw new \Exception('Company not found for ticket report: ' . $this->report->id);
                return;
            }

            // Carica le relazioni necessarie
            $this->report->load(['company']);

            // Ottieni tutti i company admin dell'azienda
            $companyAdmins = $company->users()->where('is_company_admin', true)->get();
            
            if ($companyAdmins->isEmpty()) {
                throw new \Exception('No company admins found for ticket report: ' . $this->report->id);
                return;
            }

            $recipientEmails = $companyAdmins->pluck('email')->toArray();
            $reportId = $this->report->id;
            $emailSent = false;
            $listener = null;

            // Listener per confermare l'invio della mail specifica usando l'header X-Ticket-Report-ID
            $listener = function ($event) use (&$emailSent, $reportId) {
                // Verifica che sia la nostra mail specifica usando l'header custom
                $headers = $event->message->getHeaders();
                if ($headers->has('X-Ticket-Report-ID')) {
                    $reportIdHeader = $headers->get('X-Ticket-Report-ID')->getBody();
                    if ($reportIdHeader == $reportId) {
                        $emailSent = true;
                    }
                }
            };

            \Illuminate\Support\Facades\Event::listen(
                \Illuminate\Mail\Events\MessageSent::class,
                $listener
            );

            try {
                // Invia l'email a tutti i company admin (sincrono - quando ritorna l'evento MessageSent è già stato emesso)
                Mail::to($recipientEmails)->send(new ReportUpdatedNotification($this->report));
            } finally {
                // Rimuovi il listener per evitare memory leak
                \Illuminate\Support\Facades\Event::forget(\Illuminate\Mail\Events\MessageSent::class);
            }

            // Verifica se la mail è stata effettivamente inviata
            if ($emailSent) {
                Log::info('Ticket report email sent successfully', [
                    'report_id' => $reportId,
                    'recipients' => $recipientEmails,
                    'company' => $this->report->company->business_name ?? 'N/A',
                ]);

                // Aggiorna lo status e la data dell'ultimo invio
                // Possibili valori: null, 'pending', 'sent', 'failed', 'resend_requested'
                $this->report->email_status = 'sent';
                $this->report->last_email_sent_at = now();
                $this->report->save();
            } else {
                // email_status si aggiorna a failed solo se falliscono anche i retry, quindi nella funzione failed.
                Log::warning('Ticket report email was not sent', [
                    'report_id' => $reportId,
                    'recipients' => $recipientEmails,
                ]);

                throw new \Exception('Email sending failed: no confirmation received');
            }
        } catch (\Exception $e) {
            Log::error('Failed to send ticket report email', [
                'report_id' => $this->report->id,
                'recipients' => $recipientEmails ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Imposta lo status a failed
        // Possibili valori: null, 'pending', 'sent', 'failed', 'resend_requested'
        $this->report->email_status = 'failed';
        $this->report->save();

        Log::error('Ticket report email job failed after all retries', [
            'report_id' => $this->report->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

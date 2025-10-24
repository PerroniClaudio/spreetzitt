<?php

namespace App\Jobs;

use App\Mail\CloseTicketEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCloseTicketEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticket;

    protected $message;

    protected $brand_url;

    protected $sendToDataOwner;

    /**
     * Create a new job instance.
     */
    public function __construct($ticket, $message, $brand_url, $sendToDataOwner = false)
    {
        $this->ticket = $ticket;
        $this->message = $message;
        $this->brand_url = $brand_url;
        $this->sendToDataOwner = $sendToDataOwner;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $referer = $this->ticket->referer;
        $refererIT = $this->ticket->refererIt;

        // L'invio al data_owner è in un evento uguale a questo ma dispacciato a parte, con sendToDataOwner = true
        if ($this->sendToDataOwner == true) {
            $userLink = env('FRONTEND_URL').'/support/user/data-owner/ticket/'.$this->ticket->id;

            // Inviare la mail di chiusura al data-owner (ultimo argomento true)
            if (isset($this->ticket->company->data_owner_email) && $this->ticket->company->data_owner_email != null && filter_var($this->ticket->company->data_owner_email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($this->ticket->company->data_owner_email)->send(new CloseTicketEmail($this->ticket, $this->message, $userLink, $this->brand_url, true));
            }

        } else {
            $userLink = env('FRONTEND_URL').'/support/user/ticket/'.$this->ticket->id;

            // Inviare la mail di chiusura all'utente che l'ha aperto, se non è admin
            if (! $this->ticket->user['is_admin'] && $this->ticket->user->email) {
                if (filter_var($this->ticket->user->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($this->ticket->user->email)->send(new CloseTicketEmail($this->ticket, $this->message, $userLink, $this->brand_url));
                }
            }

            // Inviare la mail di chiusura al {{ strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'referente IT')) }}
            if ($refererIT && $refererIT->email) {
                if (filter_var($refererIT->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($refererIT->email)->send(new CloseTicketEmail($this->ticket, $this->message, $userLink, $this->brand_url));
                }
            }

            // Inviare la mail di chiusura al referente in sede, se è diverso dal {{ strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'referente IT')) }}
            if ($referer && ($refererIT ? $refererIT->id !== $referer->id : true) && $referer->email) {
                if (filter_var($referer->email, FILTER_VALIDATE_EMAIL)) {
                    Mail::to($referer->email)->send(new CloseTicketEmail($this->ticket, $this->message, $userLink, $this->brand_url));
                }
            }
        }

    }
}

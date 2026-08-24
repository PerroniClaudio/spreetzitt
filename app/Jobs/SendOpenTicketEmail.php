<?php

namespace App\Jobs;

use App\Jobs\Concerns\SendsUniqueEmails;
use App\Mail\OpenTicketEmail;
use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOpenTicketEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SendsUniqueEmails, SerializesModels;

    protected $ticket;

    protected $brand_url;

    /**
     * Create a new job instance.
     */
    public function __construct($ticket, $brand_url)
    {
        $this->ticket = $ticket;
        $this->brand_url = $brand_url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $ticketUser = $this->ticket->user;
        $company = $this->ticket->company;
        $ticketType = $this->ticket->ticketType;
        $category = $ticketType->category;
        $adminLink = env('FRONTEND_URL').'/support/admin/ticket/'.$this->ticket->id;
        $userlink = env('FRONTEND_URL').'/support/user/ticket/'.$this->ticket->id;
        $group = Group::where('id', $this->ticket->group_id)->first();
        $groupEmail = $group ? $group->email : null;

        $supportMail = env('MAIL_TO_ADDRESS');
        $sentEmails = [];
        // Inviarla anche a tutti i membri del gruppo?
        // In ogni caso invia la mail al supporto ed al gruppo di appartenenza.
        $this->sendEmailOnce($sentEmails, $supportMail, function (string $email) use ($company, $ticketType, $category, $adminLink): void {
            Mail::to($email)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $adminLink, $this->brand_url, 'admin'));
        });
        $this->sendEmailOnce($sentEmails, $groupEmail, function (string $email) use ($company, $ticketType, $category, $adminLink): void {
            Mail::to($email)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $adminLink, $this->brand_url, 'admin'));
        });
        // Altrimenti si potrebbe inviare una mail al supporto per avvisare che il gruppo non ha un indirizzo email associato. Utile solo per i gruppi preesistenti.

        // Se l'utente che ha creato il ticket non è admin invia la mail anche a lui (se la sua è valida).
        if (! $ticketUser['is_admin']) {
            $this->sendEmailOnce($sentEmails, $ticketUser['email'], function (string $email) use ($company, $ticketType, $category, $userlink): void {
                Mail::to($email)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $userlink, $this->brand_url, 'user'));
            });
        }

        $referer = $this->ticket->referer;
        $refererIT = $this->ticket->refererIt;

        // Se l'utente interessato (referer) è impostato ed è diverso dall'utente che ha aperto il ticket, gli invia la mail (se la sua è valida).
        if ($referer && $referer->id !== $ticketUser->id && $referer->email) {
            $this->sendEmailOnce($sentEmails, $referer->email, function (string $email) use ($company, $ticketType, $category, $userlink): void {
                Mail::to($email)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $userlink, $this->brand_url, 'referer'));
            });
        }

        // Se il {{ strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'referente IT')) }} è impostato ed è diverso dall'utente e dall'utente interessato (referer), gli invia la mail (se la sua è valida).
        if ($refererIT && ($referer ? $refererIT->id !== $referer->id : true) && $refererIT->id !== $ticketUser->id && $refererIT->email) {
            $this->sendEmailOnce($sentEmails, $refererIT->email, function (string $email) use ($company, $ticketType, $category, $userlink): void {
                Mail::to($email)->send(new OpenTicketEmail($this->ticket, $company, $ticketType, $category, $userlink, $this->brand_url, 'referer_it'));
            });
        }

    }
}

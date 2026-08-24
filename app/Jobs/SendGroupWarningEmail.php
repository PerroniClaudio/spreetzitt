<?php

namespace App\Jobs;

use App\Jobs\Concerns\SendsUniqueEmails;
use App\Mail\GroupWarningEmail;
use App\Mail\UpdateEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendGroupWarningEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SendsUniqueEmails, SerializesModels;

    protected $type;

    protected $ticket;

    protected $group;

    protected $update;

    protected $isAutomatic;

    /**
     * Create a new job instance.
     */
    // i predefiniti null li ho messi per poter riutilizzare la funzione in casi senza ticket o update
    public function __construct($type, $group, $ticket = null, $update = null, $isAutomatic = false)
    {
        $this->type = $type;
        $this->group = $group;
        $this->ticket = $ticket ?? null;
        $this->update = $update ?? null;
        $this->isAutomatic = $isAutomatic;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $ticket = $this->update->ticket;
        $user = $this->update->user;
        $company = $ticket->company;
        $ticketType = $ticket->ticketType;
        $category = $ticketType->category;
        $link = env('FRONTEND_URL').'/support/admin/ticket/'.$ticket->id;
        $mail = env('MAIL_TO_ADDRESS');
        $handler = $ticket->handler;
        $sentEmails = [];
        // Inviarla anche a tutti i membri del gruppo?
        $this->sendEmailOnce($sentEmails, $mail, function (string $email) use ($ticket, $company, $ticketType, $category, $link, $user): void {
            Mail::to($email)->send(new UpdateEmail($ticket, $company, $ticketType, $category, $link, $this->update, $user, $this->isAutomatic));
        });

        $link = env('FRONTEND_URL').'/support/admin/'.($this->ticket ? 'ticket/'.$this->ticket->id : '');
        if ($this->group->email) {
            $this->sendEmailOnce($sentEmails, $this->group->email, function (string $email) use ($link): void {
                Mail::to($email)->send(new GroupWarningEmail($this->type, $link, $this->ticket, $this->update));
            });
        } else {
            $groupUsers = $this->group->users;

            foreach ($groupUsers as $user) {
                $this->sendEmailOnce($sentEmails, $user->email, function (string $email) use ($link): void {
                    Mail::to($email)->send(new GroupWarningEmail($this->type, $link, $this->ticket, $this->update));
                });
            }
        }

    }
}

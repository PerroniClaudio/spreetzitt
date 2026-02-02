<?php

namespace App\Jobs;

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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        // Inviarla anche a tutti i membri del gruppo?
        Mail::to($mail)->send(new UpdateEmail($ticket, $company, $ticketType, $category, $link, $this->update, $user, $this->isAutomatic));


        $link = env('FRONTEND_URL').'/support/admin/'.($this->ticket ? 'ticket/'.$this->ticket->id : '');
        if ($this->group->email) {
            Mail::to($this->group->email)->send(new GroupWarningEmail($this->type, $link, $this->ticket, $this->update));
        } else {
            $groupUsers = $this->group->users;

            foreach ($groupUsers as $user) {
                if ($user->email) {
                    Mail::to($user->email)->send(new GroupWarningEmail($this->type, $link, $this->ticket, $this->update));
                }
            }
        }

    }
}

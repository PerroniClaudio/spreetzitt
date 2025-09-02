<?php

namespace App\Jobs;

use App\Mail\UpdateEmail;
use App\Mail\AssignToUserEmail;
use App\Mail\GroupWarningEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendGroupWarningEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $type;
    protected $ticket;
    protected $group;
    protected $update;

    /**
     * Create a new job instance.
     */
    // i predefiniti null li ho messi per poter riutilizzare la funzione in casi senza ticket o update
    public function __construct($type, $group, $ticket = null, $update = null) { 
      $this->type = $type;
      $this->group = $group;
      $this->ticket = $ticket ?? null;
      $this->update = $update ?? null;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
      $link = env('FRONTEND_URL') . "/support/admin/" . ($this->ticket ? 'ticket/' . $this->ticket->id : '');
      if($this->group->email){
        Mail::to($this->group->email)->send(new GroupWarningEmail($this->type, $link, $this->ticket, $this->update));
      } else {
        $groupUsers = $this->group->users;
  
        foreach ($groupUsers as $user) {
          if($user->email){
            Mail::to($user->email)->send(new GroupWarningEmail($this->type, $link, $this->ticket, $this->update));
          }
        }
      }

    }
}
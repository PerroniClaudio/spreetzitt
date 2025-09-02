<?php

namespace App\Jobs;

use App\Mail\UpdateEmail;
use App\Mail\AssignToUserEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendUpdateEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $update;
    protected $isAutomatic;


    /**
     * Create a new job instance.
     */
    public function __construct($update, $isAutomatic = false) {
        $this->update = $update;
        $this->isAutomatic = $isAutomatic;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {

      $ticket = $this->update->ticket;

      // Se l'utente che ha creato il ticket non è admin invia la mail al supporto. Si è deciso di farlo in ogni caso.
      // if(!$ticket->user['is_admin']){

        // Si inviano tutti gli update al supporto e solo quelli selezionati al gestore
        // if($this->update["type"] == "note"){
          $user = $this->update->user;
          $company = $ticket->company;
          $ticketType =  $ticket->ticketType;
          $category = $ticketType->category;
          $link = env('FRONTEND_URL') . '/support/admin/ticket/' . $ticket->id;
          $mail = env('MAIL_TO_ADDRESS');
          $handler = $ticket->handler;
          // Inviarla anche a tutti i membri del gruppo?
          Mail::to($mail)->send(new UpdateEmail($ticket, $company, $ticketType, $category, $link, $this->update, $user, $this->isAutomatic));
          
          // Per il gestore
          if($handler) {
            // Filtro tipi di update per i quali inviare una mail al gestore. (assign, status, sla, closing, note, blame, group_assign)
            if(in_array($this->update->type, ['assign', 'sla'])) {
              // Inviare mail di assegnazione ticket, altrimenti mail di update
              if($this->update->type == 'assign'){
                Mail::to($handler->email)->send(new AssignToUserEmail($ticket, $company, $ticketType, $category, $link, $this->update, $user));
              } else {
                Mail::to($handler->email)->send(new UpdateEmail($ticket, $company, $ticketType, $category, $link, $this->update, $user));
              }
            }
          }
        // }

      // }

    }
}
<?php

namespace App\Jobs;

use App\Mail\NewMessageEmail;
use App\Mail\WelcomeEmail;
use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewMessageEmail implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ticket;
    protected $user;
    protected $message;
    protected $brand_url;


    /**
     * Create a new job instance.
     */
    public function __construct($ticket, $user, $message, $brand_url) {
        $this->ticket = $ticket;
        $this->user = $user;
        $this->message = $message;
        $this->brand_url = $brand_url;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
      $ticketUser = $this->ticket->user;
      $handler = $this->ticket->handler;
      $referer = $this->ticket->referer;
      $refererIT = $this->ticket->refererIt;
      $link_user = env('FRONTEND_URL') . '/support/user/ticket/' . $this->ticket->id;
      $link_admin = env('FRONTEND_URL') . '/support/admin/ticket/' . $this->ticket->id;
      $userLogoRedirectUrl = config('app.frontend_url');
      $adminLogoRedirectUrl = config('app.frontend_url') . '/support/admin';
      $supportMail = env('MAIL_TO_ADDRESS');
      $group = Group::where('id', $this->ticket->group_id)->first();
      $groupEmail = $group ? $group->email : null;
      
      // Inviarlo all'utente che ha creato il ticket se non è admin e se non ha inviato lui il messaggio
      if (!$ticketUser->is_admin && $ticketUser->id !== $this->user->id && $ticketUser->email) {
        Mail::to($ticketUser->email)->send(new NewMessageEmail('user', $this->ticket, $this->message, $link_user, $this->brand_url, $userLogoRedirectUrl, $this->user));
      }

      // Se chi ha creato il ticket è admin, se non l'ha inviato lui, solo se il ticket non ha il gestore, gli si invia la mail.
      if ($ticketUser->is_admin && $ticketUser->id !== $this->user->id && !$handler && $ticketUser->email) {
        Mail::to($ticketUser->email)->send(new NewMessageEmail('admin', $this->ticket, $this->message, $link_admin, $this->brand_url, $adminLogoRedirectUrl, $this->user));
      }

      // Inviarlo al gestore se c'è e non l'ha inviato lui
      if ($handler && $handler->id !== $this->user->id && $handler->email) {
        Mail::to($handler->email)->send(new NewMessageEmail('admin', $this->ticket, $this->message, $link_admin, $this->brand_url, $adminLogoRedirectUrl, $this->user));
      }

      // Inviarlo all'utente interessato (referer) se non l'ha inviato lui e se è diverso dal {{ strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'referente IT')) }}
      if($referer && $referer->id !== $this->user->id && ($refererIT ? $refererIT->id !== $referer->id : true) && $referer->email){
        Mail::to($referer->email)->send(new NewMessageEmail("referer", $this->ticket, $this->message, $link_user, $this->brand_url, $userLogoRedirectUrl, $this->user));
      }

      // Inviarlo al {{ strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'referente IT')) }} se non l'ha inviato lui
      if($refererIT && $refererIT->id !== $this->user->id && $refererIT->email){
        Mail::to($refererIT->email)->send(new NewMessageEmail("referer_it", $this->ticket, $this->message, $link_user, $this->brand_url, $userLogoRedirectUrl, $this->user));
      }

      // Inviarlo al gruppo se non l'ha inviato un admin
      if($groupEmail && !$this->user->is_admin){
        Mail::to($groupEmail)->send(new NewMessageEmail("admin", $this->ticket, $this->message, $link_admin, $this->brand_url, $adminLogoRedirectUrl, $this->user));
      }

      // Inviarlo al supporto in ogni caso
      Mail::to($supportMail)->send(new NewMessageEmail('support', $this->ticket, $this->message, $link_admin, $this->brand_url, $adminLogoRedirectUrl, $this->user));

    } 
}



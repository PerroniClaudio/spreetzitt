<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\TicketStage;
use App\Models\TicketStatusUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutoAssignTicket implements ShouldQueue
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
        //
        // $tickets = Ticket::where("status", "!=", 5)->with("company", "ticketType")->orderBy("created_at", "desc")->get();
        // $supportMail = env('MAIL_TO_ADDRESS');
        // Mail::to($supportMail)->send(new \App\Mail\PlatformActivityMail($tickets));

        // // Hardcodate
        // Mail::to("p.massafra@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));
        // Mail::to("a.fumagalli@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));
        // Mail::to("e.salsano@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));
        // Mail::to("c.perroni@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));

        Log::info('AutoAssignTicket job started');
        $newTicketStageId = TicketStage::where('system_key', 'new')->value('id');
        // $unassignedTickets = Ticket::where('status', 0)->get();
        $unassignedTickets = Ticket::where('stage_id', $newTicketStageId)->orWhereNull('admin_user_id')->get();

        foreach ($unassignedTickets as $ticket) {

            Log::info('Detected unassigned ticket: '.$ticket->id);

            $groups = $ticket->ticketType->groups()->get();

            $selectedGroup = $ticket->group ?? null;
            $adminUser = $ticket->handler ?? null;

            // Se non ha il gestore lo assegna automaticamente, altrimenti lascia quello che c'è.
            if(!$adminUser){
                if ($groups->count() == 0) {
                    // Invia mail di avviso che un ticket non ha un gruppo associato
                    continue;
                }
                
                if($ticket->group && $ticket->group->users->count() > 0){
                    // Se il ticket ha già un gruppo associato e quel gruppo ha utenti, usa quello
                    $selectedGroup = $ticket->group;
                    $adminUser = $selectedGroup->users->first();
                }else{
                    // Altrimenti cerca il primo gruppo con utenti
                    foreach ($groups as $group) {
                        if ($group->users->count() > 0) {
                            $adminUser = $group->users->first();
                            $selectedGroup = $group;
                            break;
                        }
                    }
                }
                if (! $adminUser) {
                    // Invia mail di avviso che un ticket non ha un utente associato
                    continue;
                }
    
                // Assegna il ticket all'utente
                $ticket->update([
                    'admin_user_id' => $adminUser->id,
                ]);
    
                $assignUpdate = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $adminUser->id, // Anche se non l'ha richiesto lui, ma è obbligatorio questo campo e deve essere un utente esistente
                    'content' => "Modifica automatica: Ticket assegnato all'utente ".$adminUser->name.' '.($adminUser->surname ?? ''),
                    'type' => 'assign',
                ]);

                dispatch(new SendUpdateEmail($assignUpdate, true));
                // Invia mail di avviso a tutto il gruppo che un ticket è stato assegnato automaticamente e non è detto che venga gestito da quell'utente.
                dispatch(new SendGroupWarningEmail('auto-assign', $selectedGroup, $ticket, $assignUpdate));
            }

            // Si controlla se lo stato è "Nuovo" e in tal caso lo si porta a "Assegnato"
            if ($ticket->stage_id == $newTicketStageId) {
                $assignedStageId = TicketStage::where('system_key', 'assigned')->value('id');
                $ticket->update(['stage_id' => $assignedStageId]);
                $newStageText = TicketStage::find($assignedStageId)->name;
    
                $statusUpdate = TicketStatusUpdate::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $adminUser->id,
                    'old_stage_id' => $newTicketStageId,
                    'new_stage_id' => $assignedStageId,
                    'content' => 'Modifica automatica: Stato del ticket modificato in "'.$newStageText.'"',
                    'type' => 'status',
                ]);
            }

            // Invalida la cache per chi ha creato il ticket e per i referenti.
            $ticket->invalidateCache();

        }
    }
}

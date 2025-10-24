<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TicketType;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class MigrateMasterToSchedulingCommand extends Command
{
    protected $signature = 'tickets:migrate-master-to-scheduling';
    protected $description = 'Migra i TicketType master in scheduling e aggiorna i ticket associati';

    public function handle(): int
    {
        DB::transaction(function () {
            $masterTypes = TicketType::where('is_master', true)->get();
            $this->info('Trovati ' . $masterTypes->count() . ' TicketType master.');
            foreach ($masterTypes as $type) {
                $type->is_master = false;
                $type->is_scheduling = true;
                $type->save();
                $this->info("Aggiornato TicketType #{$type->id}");
            }

            $slaveTickets = Ticket::whereNotNull('master_id')->get();
            $this->info('Trovati ' . $slaveTickets->count() . ' ticket slave da aggiornare.');
            foreach ($slaveTickets as $ticket) {
                // Verifica se il tipo master esiste ancora
                $masterTypeExists = TicketType::find($ticket->type_id) !== null;
                if (! $masterTypeExists) {
                    $this->warn("Ticket #{$ticket->id} ha master_id ma il tipo non esiste più (type_id: {$ticket->type_id})");
                }
                $ticket->scheduling_id = $ticket->master_id;
                $ticket->master_id = null;
                // Per la migrazione si è deciso di inserire il tempo di gestione effettivo come durata pianificata. poi se servono aggiustamenti si fanno dopo.
                $ticket->scheduled_duration = $ticket->actual_processing_time;
                $ticket->save();
                $this->info("Aggiornato Ticket #{$ticket->id}");
            }
        });
        $this->info('Migrazione completata.');
        return 0;
    }
}

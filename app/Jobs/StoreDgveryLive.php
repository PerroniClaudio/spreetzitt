<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketStage;
use App\Models\TicketType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreDgveryLive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $payload;

    /**
     * Create a new job instance.
     */
    public function __construct($payload)
    {
        //

        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        $payload = $this->payload;

        $ticketType = TicketType::find(652);
        $group = $ticketType->groups->first();
        $groupId = $group->id;
        $newStageId = TicketStage::where('system_key', 'new')->first()->id ?? null;

        $ticket = Ticket::create([
            'description' => $payload['description'],
            'type_id' => 652,
            'group_id' => $groupId,
            'user_id' => 12,
            'status' => '0', // solo finchè non si migra definitivamente al nuovo sistema con stage
            'stage_id' => $newStageId,
            'company_id' => $payload['company_id'], // 13 academ, 21 labor
            'file' => null,
            'duration' => 0,
            'sla_take' => $ticketType['default_sla_take'],
            'sla_solve' => $ticketType['default_sla_solve'],
            'priority' => $ticketType['default_priority'],
            'unread_mess_for_adm' => 1,
            'unread_mess_for_usr' => 0,
            'source' => 'automatic',
            'is_user_error' => 1, // is_user_error viene usato per la responsabilità del dato e di default è assegnata al cliente.
            'is_billable' => $ticketType['expected_is_billable'],
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => 12,
            'message' => $payload['webform'],
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => 12,
            'message' => $payload['description'],
        ]);
    }
}

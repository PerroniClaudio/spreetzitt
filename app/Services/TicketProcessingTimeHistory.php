<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketStatusUpdate;

class TicketProcessingTimeHistory
{
    public function forTicket(Ticket $ticket): array
    {
        $entries = $ticket->statusUpdates()
            ->where('type', 'time')
            ->select(['id', 'ticket_id', 'user_id', 'content', 'type', 'created_at'])
            ->with('user:id,name,surname')
            ->orderBy('created_at')->orderBy('id')->get()
            ->map(function (TicketStatusUpdate $update) {
                $minutes = null;
                // Time updates store the new value in their generated message.
                if (preg_match('/^Tempo di lavorazione effettivo modificato a\s+(\d+):([0-5]\d)\s*$/u', trim($update->content ?? ''), $matches)) {
                    $minutes = (int) $matches[1] * 60 + (int) $matches[2];
                }

                return [
                    'id' => $update->id,
                    'type' => $update->type,
                    'actual_processing_time' => $minutes,
                    'content' => $update->content,
                    'created_at' => $update->created_at?->toISOString(),
                    'user' => $update->user,
                ];
            });
        $current = $ticket->actual_processing_time === null ? null : (int) $ticket->actual_processing_time;
        $last = $entries->last();

        return [
            'ticket_id' => $ticket->id,
            'entries' => $entries,
            'current' => [
                'actual_processing_time' => $current,
                // This is an observation timestamp, not the date of a possible direct DB edit.
                'observed_at' => now()->toISOString(),
                'differs_from_last_recorded' => $last !== null && $last['actual_processing_time'] !== null
                    ? $current !== $last['actual_processing_time']
                    : null,
            ],
        ];
    }
}

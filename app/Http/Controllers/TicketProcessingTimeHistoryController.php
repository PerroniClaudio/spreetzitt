<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketProcessingTimeHistory;
use Illuminate\Http\Request;

class TicketProcessingTimeHistoryController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket, TicketProcessingTimeHistory $history)
    {
        abort_unless($request->user()->is_admin, 403);

        return response()->json($history->forTicket($ticket));
    }
}

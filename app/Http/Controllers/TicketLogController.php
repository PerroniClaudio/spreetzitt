<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketLog;
use Illuminate\Http\Request;

class TicketLogController extends Controller
{
    /**
     * Recupera tutti i TicketLog di un determinato ticket
     */
    public function index(Ticket $ticket, Request $request)
    {
        $user = $request->user();
        $selectedCompanyId = $this->getSelectedCompanyId($user);

        // Controllo autorizzazioni
        if ($user['is_admin'] != 1 && 
            (! $selectedCompanyId || $selectedCompanyId != $ticket->company_id)) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Recupera tutti i TicketLog collegati a questo ticket
        $ticketLogs = $ticket->ticketLogs()
            ->with(['user:id,name,surname,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Filtra i log se l'utente non è admin
        if ($user['is_admin'] != 1) {
            $ticketLogs = $ticketLogs->where('show_to_user', true);
        }

        return response([
            'ticket_logs' => $ticketLogs,
        ], 200);
    }

    /**
     * Recupera un TicketLog specifico
     */
    public function show(TicketLog $ticketLog, Request $request)
    {
        $user = $request->user();
        $selectedCompanyId = $this->getSelectedCompanyId($user);

        // Carica le relazioni
        $ticketLog->load(['user:id,name,surname,email', 'tickets:id,description,company_id']);

        // Verifica che l'utente possa accedere a questo log
        $ticketCompanyIds = $ticketLog->tickets->pluck('company_id')->unique();
        
        if ($user['is_admin'] != 1) {
            // L'utente deve appartenere a una delle aziende dei ticket collegati
            if (! $selectedCompanyId || ! $ticketCompanyIds->contains($selectedCompanyId)) {
                return response([
                    'message' => 'Unauthorized',
                ], 401);
            }

            // L'utente non-admin può vedere solo i log con show_to_user = true
            if (! $ticketLog->show_to_user) {
                return response([
                    'message' => 'Log not visible to users',
                ], 403);
            }
        }

        return response([
            'ticket_log' => $ticketLog,
        ], 200);
    }

    /**
     * Funzione helper per ottenere l'ID dell'azienda selezionata
     */
    private function getSelectedCompanyId($user)
    {
        return $user->selectedCompany() ? $user->selectedCompany()->id : null;
    }
}

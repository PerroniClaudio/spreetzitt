<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketStats;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TicketStatsController extends Controller
{
    public function latestStats(Request $request)
    {
        $user = $request->user();
        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $cacheKey = 'tickets_stats';
        $stats = Cache::remember($cacheKey, now()->addMinutes(60), function () {
            return TicketStats::latest()->first();
        });

        return response([
            'stats' => $stats,
        ], 200);
    }

    public function statsForCompany(Request $request)
    {
        $user = $request->user();
        if ($user['is_company_admin'] != 1) {
            return response([
                'message' => 'Unauthorized',
            ], 401);
        }

        $company = $user->selectedCompany();

        $cacheKey = 'tickets_stats_'.$company->id.'_'.$request->start_date.'_'.$request->end_date;

        //? Quanti utenti sono stati creati in un determinato periodo

        $usersCount = User::whereHas('companies', function ($query) use ($company) {
            $query->where('companies.id', $company->id);
        })
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->count();

        //? Quanti ticket aperti per utente nel periodo definito

        $tickets = Ticket::where('company_id', $company->id)
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->with('user')
            ->with('ticketType')
            ->get();

        $ticketPerUser = [];

        foreach ($tickets as $ticket) {

            if (! isset($ticketPerUser[$ticket->user->id])) {

                if ($ticket->user->is_admin == 1) {
                    $ticketPerUser[$ticket->user->id] = [
                        'user' => 'Supporto iFortech',
                        'tickets' => 0,
                    ];
                } else {

                    $ticketPerUser[$ticket->user->id] = [
                        'user' => $ticket->user->name.' '.$ticket->user->surname,
                        'tickets' => 0,
                    ];
                }
            }

            $ticketPerUser[$ticket->user->id]['tickets']++;
        }

        usort($ticketPerUser, function ($a, $b) {
            return $b['tickets'] - $a['tickets'];
        });

        //? Quanti ticket aperti per tipologia nel periodo definito

        $ticketsPerType = [];

        foreach ($tickets as $ticket) {

            if (! isset($ticketsPerType[$ticket->ticketType->id])) {
                $ticketsPerType[$ticket->ticketType->id] = [
                    'type' => $ticket->ticketType->name,
                    'tickets' => 0,
                ];
            }

            $ticketsPerType[$ticket->ticketType->id]['tickets']++;
        }

        usort($ticketsPerType, function ($a, $b) {
            return $b['tickets'] - $a['tickets'];
        });

        return response(
            [
                'stats' => [
                    'users_count' => $usersCount,
                    'ticket_per_user' => $ticketPerUser,
                    'ticket_per_type' => $ticketsPerType,
                ],
            ],
            200
        );
    }
}

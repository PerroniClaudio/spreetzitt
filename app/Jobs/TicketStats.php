<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketStats as Stats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class TicketStats implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct() {
        //
    }

    private function getNightHours($start, $end) {

        $nightHours = 0;

        if ($start->isSameDay($end)) {

            if ($start->isBefore($start->copy()->startOfDay()->addHours(18)) && $end->isAfter($start->copy()->startOfDay()->addHours(8))) {
                $nightHours = 10;
            } else if ($start->isBefore($start->copy()->startOfDay()->addHours(18)) && $end->isBefore($start->copy()->startOfDay()->addHours(8))) {
                $nightHours = $start->diffInHours($start->copy()->startOfDay()->addHours(8));
            } else if ($start->isAfter($start->copy()->startOfDay()->addHours(18)) && $end->isAfter($start->copy()->startOfDay()->addHours(8))) {
                $nightHours = $end->diffInHours($start->copy()->startOfDay()->addHours(18));
            }
        } else {
            $nightHours = $start->diffInDays($end) * 13;
        }


        return $nightHours;
    }

    // // Funzione da testare con dove viene utilizzata prima di pubblicarla
    // private function getNightHours($start, $end) {

    //     if ($start->diffInDays($end) != 0) {
    //         $fullDaysHours = $start->diffInDays($end) > 1 ? (($start->diffInDays($end) - 1) * ($endHour + (24-$startHour))) : 0;

    //         // Calcola ore primo giorno fino alla mezzanotte (finisce in un altro giorno)
    //         $orePrimoGiorno = 0;
    //         $orePrimoGiorno += $start->hour < $endHour ? ($endHour - $start->hour) : 0;
    //         $orePrimoGiorno += $start->hour <= $startHour ? (24 - $startHour) : ($startHour - $start->hour);
            
    //         // Calcola ore ultimo giorno fino alla scadenza (inizia in un altro giorno)
    //         $oreUltimoGiorno = 0;
    //         $oreUltimoGiorno += $end->hour < $endHour ? $end->hour : ($endHour);
    //         $oreUltimoGiorno += $end->hour <= $startHour ? 0 : ($end->hour - $startHour);

    //         return $fullDaysHours + $orePrimoGiorno + $oreUltimoGiorno;
    //     } else {
    //         // Calcolo ore nel giorno stesso
    //         $sameDayHours = 0;
    //         // inizia prima delle 8
    //         if ($start->hour < $endHour) {
    //             // finisce prima delle 8
    //             if ($end->hour < $endHour) {
    //                 $sameDayHours += ($end->hour - $start->hour);
    //             } else {
    //                 $sameDayHours += ($endHour - $start->hour);
    //                 if($end->hour > $startHour) {
    //                     $sameDayHours += ($end->hour - $startHour);
    //                 }
    //             }
    //         } else if ($end->hour > $startHour) { //altrimenti non serve calcolare
    //             if($start->hour > $startHour) {
    //                 $sameDayHours += ($end->hour - $start->hour);
    //             } else {
    //                 $sameDayHours += ($end->hour - $startHour);
    //             }
    //         }

    //         return $sameDayHours;
    //     }
    // }

    
    /**
     * Execute the job.
     */
    public function handle(): void {
        $openTicekts = Ticket::where('status', '!=', '5')->with('ticketType.category')->get();

        $results = [
            'incident_open' => 0,
            'incident_in_progress' => 0,
            'incident_waiting' => 0,
            'incident_out_of_sla' => 0,
            'request_open' => 0,
            'request_in_progress' => 0,
            'request_waiting' => 0,
            'request_out_of_sla' => 0
        ];

        foreach ($openTicekts as $ticket) {

            // Aggiunti anche i risolti tra quelli in attesa, perchè non sono chiusi e potrebbero tornare in lavorazione se la soluzione non viene accettata dall'utente.
            switch ($ticket->ticketType->category->is_problem) {
                case 1:
                    switch ($ticket->status) {
                        case 0:
                            $results['incident_open']++;
                            break;
                        case 1:
                        case 2:
                            $results['incident_in_progress']++;
                            break;
                        case 3:
                        case 4:
                            $results['incident_waiting']++;
                            break;
                    }
                    break;
                case 0:
                    switch ($ticket->status) {
                        case 0:
                            $results['request_open']++;
                            break;
                        case 1:
                        case 2:
                            $results['request_in_progress']++;
                            break;
                        case 3:
                        case 4:
                            $results['request_waiting']++;
                            break;
                    }
                    break;
            }

            /*
                Per verificare se il ticket in sla bisogna utilizzare il campo sla_solve del ticketType. 

                Bisogna verificare che la differenza tra la data attuale e la data di creazione del ticket sia minore della data di sla_solve.
                Calcolando questa differenza bisogna tenere conto del fatto che le ore tra mezzanotte e le 8 del mattino non vanno calcolate.
                Calcolando questa differenza bisogna tenere conto del fatto che le ore tra le 18 e mezzanotte non vanno calcolate.
                Calcolando questa differenza bisogna tenere conto che il sabato, la domenica ed i giorni festivi non vanno calcolati.

            */

            $sla = round($ticket->sla_solve / 60, 1);
            $ticketCreationDate = $ticket->created_at;
            $now = now();

            $diffInHours = $ticketCreationDate->diffInHours($now);

            $diffInHours -= $this->getNightHours($ticketCreationDate, $now);

            // ? Rimuovere sabati e domeniche 

            $weekendDays = 0;

            for ($i = 0; $i < $ticketCreationDate->diffInDays($now); $i++) {
                $day = $ticketCreationDate->copy()->addDays($i);
                if ($day->isSaturday() || $day->isSunday()) {
                    $weekendDays++;
                }
            }

            $diffInHours -= $weekendDays * 24;

            // ? Se il ticket è rimasto in attesa è necessario rimuovere le ore in cui è rimasto in attesa.

            $waitingHours = $ticket->waitingHours();
            $diffInHours -= $waitingHours;


            if ($diffInHours > $sla) {
                switch ($ticket->ticketType->category->is_problem) {
                    case 1:
                        $results['incident_out_of_sla']++;
                        break;
                    case 0:
                        $results['request_out_of_sla']++;
                        break;
                }
            }
        }

        // Creare la lista di compagnie con ticket aperti
        $companiesOpenTickets = [];
        // Seve use(&$companiesOpenTickets) per passare la variabile per riferimento e non per valore
        Company::all()->each(function ($company) use (&$companiesOpenTickets) {
            $companyTickets = $company->tickets->where('status', '!=', '5')->count();
            $companiesOpenTickets[] = [
                "name" => $company->name,
                "tickets" => $companyTickets
            ];
        });

        Stats::create([
            'incident_open' => $results['incident_open'],
            'incident_in_progress' => $results['incident_in_progress'],
            'incident_waiting' => $results['incident_waiting'],
            'incident_out_of_sla' => $results['incident_out_of_sla'],
            'request_open' => $results['request_open'],
            'request_in_progress' => $results['request_in_progress'],
            'request_waiting' => $results['request_waiting'],
            'request_out_of_sla' => $results['request_out_of_sla'],
            'compnanies_opened_tickets' => json_encode($companiesOpenTickets)
        ]);

        // Invalida la cache coi dati precedenti
        Cache::forget('tickets_stats');
    }
}

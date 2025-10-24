<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketStage;
use App\Models\TicketStats as Stats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class TicketStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    private function getNightHours($start, $end)
    {

        $nightHours = 0;

        if ($start->isSameDay($end)) {
            // Bisogna allineare i dati tra questa funzione e quella che calcola le ore di attesa, perchè questa ha 18 e l'altra 20. Poi considerare anche se devono essere settate in base alle impostazioni nell'azienda cliente o no.
            if ($start->isBefore($start->copy()->startOfDay()->addHours(18)) && $end->isAfter($start->copy()->startOfDay()->addHours(8))) {
                $nightHours = 10;
            } elseif ($start->isBefore($start->copy()->startOfDay()->addHours(18)) && $end->isBefore($start->copy()->startOfDay()->addHours(8))) {
                $nightHours = $start->diffInHours($start->copy()->startOfDay()->addHours(8));
            } elseif ($start->isAfter($start->copy()->startOfDay()->addHours(18)) && $end->isAfter($start->copy()->startOfDay()->addHours(8))) {
                $nightHours = $end->diffInHours($start->copy()->startOfDay()->addHours(18));
            }
        } else {

            // Calcolo ore notturne primo giorno (dalle 18:00 fino a mezzanotte)
            $firstDayNightHours = 0;
            if ($start->hour < 18) {
                // Inizia prima delle 18, conta dalle 18 a mezzanotte
                $firstDayNightHours = 6; // 18:00 - 24:00 = 6 ore
            } elseif ($start->hour >= 18) {
                // Inizia dopo le 18, conta dall'ora di inizio a mezzanotte
                $firstDayNightHours = 24 - $start->hour;
            }
            // Calcolo ore notturne ultimo giorno (da mezzanotte alle 8:00)
            $lastDayNightHours = 0;
            if ($end->hour > 8) {
                // Finisce dopo le 8, conta da mezzanotte alle 8
                $lastDayNightHours = 8;
            } elseif ($end->hour <= 8) {
                // Finisce prima delle 8, conta da mezzanotte all'ora di fine
                $lastDayNightHours = $end->hour;
            }
            // Calcolo giorni intermedi completi (se esistono)
            $fullDaysBetween = max(0, $start->diffInDays($end) - 1);
            $fullDaysNightHours = $fullDaysBetween * 14; // 6 + 8 = 14 ore per giorno completo

            $nightHours = $firstDayNightHours + $lastDayNightHours + $fullDaysNightHours;
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
    public function handle(): void
    {
        $newTicketStageId = TicketStage::where('system_key', 'new')->first()?->id;
        $closedTicketStageId = TicketStage::where('system_key', 'closed')->first()?->id;
        $waitingTicketStagesIds = TicketStage::where('is_sla_pause', true)->pluck('id')->toArray();

        $openTicekts = Ticket::where('stage_id', '!=', $closedTicketStageId)->with('ticketType.category')->get();

        $results = [
            'incident_open' => 0,
            'incident_in_progress' => 0,
            'incident_waiting' => 0,
            'incident_out_of_sla' => 0,
            'request_open' => 0,
            'request_in_progress' => 0,
            'request_waiting' => 0,
            'request_out_of_sla' => 0,
        ];

        foreach ($openTicekts as $ticket) {

            // Aggiunti anche i risolti tra quelli in attesa, perchè non sono chiusi e potrebbero tornare in lavorazione se la soluzione non viene accettata dall'utente.
            switch ($ticket->ticketType->category->is_problem) {
                case 1:
                    if ($ticket->stage_id == $newTicketStageId) {
                        $results['incident_open']++;
                    } elseif (in_array($ticket->stage_id, $waitingTicketStagesIds)) {
                        $results['incident_waiting']++;
                    } else {
                        $results['incident_in_progress']++;
                    }
                    break;
                case 0:
                    if ($ticket->stage_id == $newTicketStageId) {
                        $results['request_open']++;
                    } elseif (in_array($ticket->stage_id, $waitingTicketStagesIds)) {
                        $results['request_waiting']++;
                    } else {
                        $results['request_in_progress']++;
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

            // Quando si rifarà la funzione considerare che se vengono già tolte le ore notturne non si devono togliere 24 ore, ma meno.
            // In più si dovrebbe controllare se il giorno in questione è quello iniziale o finale e calcolare con più precisione in quel caso.
            // Inoltre la funzione successiva non considera sabati e domeniche se non si passano parametri diversi, quindi considerare anche quello (che siano allineate. se non le considera allora va bene così, altrimenti non vanno eliminati nemmeno qui).
            // $diffInHours -= $weekendDays * 24;
            $diffInHours -= $weekendDays * 10; // nightHours conta dalle 18 alle 8, quindi 14 ore. Ne rimangono 10.

            // ? Se il ticket è rimasto in attesa è necessario rimuovere le ore in cui è rimasto in attesa.

            // Quando si rifarà la funzione considerare che $ticket->waitingHours() considera o meno le ore notturne in base al parametro che gli si passa e che le conteggerebbe dalle 20 alle 8.
            // Di base non le considera. Inoltre anche sabati e domeniche vanno in base ai parametri passati e di base non li considera.
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
        // Serve use(&$companiesOpenTickets, $closedTicketStageId) per passare la variabile per riferimento e non per valore
        Company::all()->each(function ($company) use (&$companiesOpenTickets, $closedTicketStageId) {
            $companyTickets = $company->tickets->where('stage_id', '!=', $closedTicketStageId)->count();
            $companiesOpenTickets[] = [
                'name' => $company->name,
                'tickets' => $companyTickets,
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
            'compnanies_opened_tickets' => json_encode($companiesOpenTickets),
        ]);

        // Invalida la cache coi dati precedenti
        Cache::forget('tickets_stats');
    }
}

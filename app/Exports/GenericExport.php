<?php

namespace App\Exports;

use App\Models\Ticket;
use App\Models\TicketReportExport;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;

class GenericExport implements FromArray
{
    private $report;

    private $hours_for_scadenza = 2;

    public function __construct(TicketReportExport $ticketReportExport)
    {
        $this->report = $ticketReportExport;
    }

    private function getNightHours($start, $end)
    {

        $nightHours = 0;

        if ($start->isSameDay($end)) {

            if ($start->isBefore($start->copy()->startOfDay()->addHours(18)) && $end->isAfter($start->copy()->startOfDay()->addHours(8))) {
                $nightHours = 10;
            } elseif ($start->isBefore($start->copy()->startOfDay()->addHours(18)) && $end->isBefore($start->copy()->startOfDay()->addHours(8))) {
                $nightHours = $start->diffInHours($start->copy()->startOfDay()->addHours(8));
            } elseif ($start->isAfter($start->copy()->startOfDay()->addHours(18)) && $end->isAfter($start->copy()->startOfDay()->addHours(8))) {
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
     * @return \Illuminate\Support\Collection
     */
    public function array(): array
    {

        $ticket_data = [];
        $headers = [
            'ID',
            'Autore',
            'Utente interessato', // referer
            'Data',
            'Tipologia',
            'Webform',
            'Chiusura',
            'Tempo in attesa (ore)',
            'Numero di volte in attesa',
        ];

        if ($this->report->company_id != 1) {
            $tickets = Ticket::where('company_id', $this->report->company_id)->whereBetween('created_at', [
                $this->report->start_date,
                $this->report->end_date,
            ]);
        } else {
            $tickets = Ticket::whereBetween('created_at', [
                $this->report->start_date,
                $this->report->end_date,
            ]);
        }

        $optional_parameters = json_decode($this->report->optional_parameters, true);

        // Se è impostato $optional_paramters->specific_types allora filtro i ticket per i tipi indicati

        if (isset($optional_parameters['specific_types']) && count($optional_parameters['specific_types']) > 0) {
            $tickets = $tickets->whereIn('type_id', $optional_parameters['specific_types'])->get();
        } elseif (isset($optional_parameters['type'])) {

            //Se è incident prendi solo le categorie che hanno is_incident = 1, se è request prendi solo le categorie che hanno is_incident = 0

            $tickets = $tickets->whereHas('ticketType.category', function ($query) use ($optional_parameters) {
                $query->where('is_problem', $optional_parameters['type'] == 'incident' ? 1 : 0);
            })->get();

        } else {
            $tickets = $tickets->get();
        }

        foreach ($tickets as $ticket) {

            $messages = $ticket->messages;
            $webform = json_decode($messages->first()->message, true);
            $webform_text = '';
            $has_referer = false;
            $referer_name = '';

            if (isset($webform)) {
                foreach ($webform as $key => $value) {
                    $webform_text .= $key.': '.(is_array($value) ? implode(', ', $value) : $value)."\n";

                    if ($key == 'referer') {
                        $has_referer = true;
                    }
                }
            }

            if ($has_referer) {
                if (isset($webform['referer'])) {
                    $referer = User::find($webform['referer']);
                    $referer_name = $referer ? $referer->name.' '.$referer->surname : null;
                }
            }

            $waiting_times = $ticket->waitingTimes();
            $waiting_hours = $ticket->waitingHours();

            if ($optional_parameters['sla'] != 'nessuna') {

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

                $diffInHours -= $waiting_hours;

                //? Calcolo sla

                if ($optional_parameters['sla'] == 'scadenza') {

                    // Il ticket è da considerarsi "In scadenza" se la $diffinhours è comunque minore del tempo di sla ma la differenza tra $sla e $diffinhours è minore del tempo di scadenza

                    if (($diffInHours < $sla) && ($sla - $diffInHours < $this->hours_for_scadenza)) {

                        $this_ticket = [
                            $ticket->id,
                            $ticket->user->name.' '.$ticket->user->surname,
                            $referer_name,
                            $ticket->created_at,
                            $ticket->ticketType->name,
                            $webform_text,
                            $ticket->created_at,
                            $waiting_hours,
                            $waiting_times,
                        ];

                        foreach ($ticket->messages as $message) {

                            if ($message == $ticket->messages->first()) {
                                continue;
                            }

                            $this_ticket[] = $message->created_at;
                            $this_ticket[] = $message->message;
                        }

                        $ticket_data[] = $this_ticket;

                    }

                }

                if ($optional_parameters['sla'] == 'scaduti') {
                    // Ticket da considerarsi scaduto

                    if ($diffInHours > $sla) {

                        $this_ticket = [
                            $ticket->id,
                            $ticket->user->name.' '.$ticket->user->surname,
                            $referer_name,
                            $ticket->created_at,
                            $ticket->ticketType->name,
                            $webform_text,
                            $ticket->created_at,
                            $waiting_hours,
                            $waiting_times,
                        ];

                        foreach ($ticket->messages as $message) {

                            if ($message == $ticket->messages->first()) {
                                continue;
                            }

                            $this_ticket[] = $message->created_at;
                            $this_ticket[] = $message->message;
                        }

                        $ticket_data[] = $this_ticket;

                    }
                }

            } else {

                $this_ticket = [
                    $ticket->id,
                    $ticket->user->name.' '.$ticket->user->surname,
                    $referer_name,
                    $ticket->created_at,
                    $ticket->ticketType->name,
                    $webform_text,
                    $ticket->created_at,
                    $waiting_hours,
                    $waiting_times,
                ];

                foreach ($ticket->messages as $message) {

                    if ($message == $ticket->messages->first()) {
                        continue;
                    }

                    $this_ticket[] = $message->created_at;
                    $this_ticket[] = $message->message;
                }

                $ticket_data[] = $this_ticket;

            }

            /*

            if($optional_parameters['sla'] !=  "nessuna") {

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

                $diffInHours -= $waiting_hours;

                if($optional_parameters['sla'] == "scadenza"){

                    // Il ticket è da considerarsi "In scadenza" se la $diffinhours è comunque minore del tempo di sla ma la differenza tra $sla e $diffinhours è minore del tempo di scadenza

                    if (($diffInHours < $sla) && ($sla - $diffInHours < $this->hours_for_scadenza)){

                        $this_ticket = [
                            $ticket->id,
                            $ticket->user->name . " " . $ticket->user->surname,
                            $referer_name,
                            $ticket->created_at,
                            $ticket->ticketType->name,
                            $webform_text,
                            $ticket->created_at,
                            $waiting_hours,
                            $waiting_times
                        ];

                        foreach ($ticket->messages as $message) {

                            if ($message == $ticket->messages->first()) {
                                continue;
                            }

                            $this_ticket[] = $message->created_at;
                            $this_ticket[] = $message->message;
                        }

                        $ticket_data[] = $this_ticket;

                    }

                }

                if($optional_parameters['sla'] == "scaduti"){

                    // Ticket da considerarsi scaduto

                    if ($diffInHours > $sla) {

                        $this_ticket = [
                            $ticket->id,
                            $ticket->user->name . " " . $ticket->user->surname,
                            $referer_name,
                            $ticket->created_at,
                            $ticket->ticketType->name,
                            $webform_text,
                            $ticket->created_at,
                            $waiting_hours,
                            $waiting_times
                        ];

                        foreach ($ticket->messages as $message) {

                            if ($message == $ticket->messages->first()) {
                                continue;
                            }

                            $this_ticket[] = $message->created_at;
                            $this_ticket[] = $message->message;
                        }

                        $ticket_data[] = $this_ticket;

                    }

                }

            } else {
                $this_ticket = [
                    $ticket->id,
                    $ticket->user->name . " " . $ticket->user->surname,
                    $referer_name,
                    $ticket->created_at,
                    $ticket->ticketType->name,
                    $webform_text,
                    $ticket->created_at,
                    $waiting_hours,
                    $waiting_times
                ];

                foreach ($ticket->messages as $message) {

                    if ($message == $ticket->messages->first()) {
                        continue;
                    }

                    $this_ticket[] = $message->created_at;
                    $this_ticket[] = $message->message;
                }

                $ticket_data[] = $this_ticket;
            }

            */

        }

        return [
            $headers,
            $ticket_data,
        ];
    }
}

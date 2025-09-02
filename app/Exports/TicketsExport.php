<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\Office;
use App\Models\Ticket;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TicketsExport implements FromArray, WithColumnFormatting, WithEvents {
// class TicketsExport implements FromArray, WithColumnFormatting {

// 
    private $company_id;
    private $start_date;
    private $end_date;

    public function __construct($company_id, $start_date, $end_date) {
        $this->company_id = $company_id;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function array(): array {

        // $tickets = Ticket::where('company_id', $this->company->id)->whereBetween('created_at', [now()->subDays(30)->startOfMonth(), now()->subDays(30)->endOfMonth()])->get();
        $tickets = Ticket::where('company_id', $this->company_id)->whereBetween('created_at', [
            $this->start_date,
            $this->end_date
        ])->get();

        $ticket_data = [];
        $headers = [
            "ID",
            "Autore",
            "Utente interessato", // referer
            "Data",
            "Tipologia",
            "Webform",
            "Chiusura",
            "Fatturabile",
            "Tempo previsto di esecuzione",
            "Tempo di esecuzione",
            "Tempo in attesa",
            "Numero di volte in attesa",
            "Modalità di lavoro",
            "Form corretto",
            "Cliente autonomo",
            "Responsabilità del dato", // nel db per ora è is_user_error perchè veniva usato in un altro modo
            "Responsabilità del problema"
        ];

        foreach ($tickets as $ticket) {

            $messages = $ticket->messages;
            $webform = json_decode($messages->first()->message, true);
            $webform_text = "";
            $has_referer = false;
            $referer_name = "";

            // Recupera i campi hardware per poterli intercettare e riempire coi dati dell'hardware selezionato.
            // Se viene modificato il form (es. un campo hardware viene eliminato) non si può più intercettare il tipo di campo dal nome 
            // e risulteranno solo gli id al posto degli altri dati dell'hardware selezionato.
            $hardwareFields = $ticket->ticketType->typeFormField()->where('field_type', 'hardware')->pluck('field_label')->map(function($field) {
                return strtolower($field);
            });

            
            if(isset($webform)){
                foreach ($webform as $key => $value) {
                    if ($key == "description"){
                        continue;
                    }
                    if ($key == "referer") {
                        $has_referer = true;
                        continue;
                    }
                    if ($key == "referer_it"){
                        continue;
                    }
                    if ($key == "office"){
                        $office = Office::find($value);
                        if($office){
                            $webform_text .= "Sede: " . $office->name . "\n ";
                        }
                        continue;
                    }
                    if (in_array(strtolower($key), $hardwareFields->toArray())) {
                        foreach ($value as $index => $hardware_id) {
                            // Non è detto che l'hardware esista ancora. Se esiste si aggiungono gli altri valori
                            $hardware = $ticket->hardware()->where('hardware_id', $hardware_id)->first();
                            if ($hardware) {
                                $webform[$key][$index] = $hardware->id . " (" . $hardware->make . " " 
                                    . $hardware->model . " " . $hardware->serial_number 
                                    . ($hardware->company_asset_number ? " " . $hardware->company_asset_number : "")
                                    . ($hardware->support_label ? " " . $hardware->support_label : "")
                                    . ")";
                            } else {
                                $webform[$key][$index] = $webform[$key][$index] . " (assente)";
                            }
                        }
                        $webform_text .= $key . ": " . (is_array($webform[$key]) ? implode(', ', $webform[$key]) : $webform[$key]) . "\n ";
                        continue;
                    }
                    
                    // Negli altri casi si aggiunge e basta
                    $webform_text .= $key . ": " . (is_array($value) ? implode(', ', $value) : $value) . "\n ";
                }
                // Poi si aggiunge la descrizione. (si può spostare anche all'inizio volendo)
                if(isset($webform['description'])) {
                    $webform_text .= "Descrizione: " . $webform['description'] . "\n ";
                }
            }

            if ($has_referer) {
                if (isset($webform['referer'])) {
                    $referer = User::find($webform['referer']);
                    $referer_name = $referer ? $referer->name . " " . $referer->surname : null;
                }
            }

            $closingUpdate = $ticket->statusUpdates()->where('type', 'closing')->orderBy('created_at', 'desc')->first();
            $closingDate = $closingUpdate ? $closingUpdate->created_at : null;

            $expectedProcessingTime = $ticket->expected_processing_time 
                ? floor($ticket->expected_processing_time / 60) . ":" . str_pad(floor($ticket->expected_processing_time % 60), 2, '0', STR_PAD_LEFT) . ":00"
                : "Non definito";

            // $processingTimeHours= $ticket->actual_processing_time ? floor($ticket->actual_processing_time / 60) : 0;
            // $processingTimeMinutes = $ticket->actual_processing_time ? $ticket->actual_processing_time % 60 : 0;
            // $processingTime = (!!$processingTimeHours ? ($processingTimeHours . ($processingTimeHours > 1 ? " ore " : " ora ")) : "") 
            //     . ((!$processingTimeHours || !$processingTimeMinutes) ? "" : "e ")
            //     . (!!$processingTimeMinutes ? ($processingTimeMinutes . ($processingTimeMinutes > 1 ? " minuti" : " minuto")) : "");
            
            // $processingTime = $ticket->actual_processing_time 
            //     ? floor($ticket->actual_processing_time / 60) . ":" . str_pad(floor($ticket->actual_processing_time % 60), 2, '0', STR_PAD_LEFT) . ":00"
            //     : "Non definito";
            $processingTime = $ticket->actual_processing_time 
                ? $ticket->actual_processing_time / 1440 // Converti minuti in giorni (1 giorno = 1440 minuti)
                : "Non definito";

            $waiting_times = $ticket->waitingTimes();
            $waiting_hours = $ticket->waitingHours();

            // $waitingTimeHours = $waiting_hours ? floor($waiting_hours) : 0;
            // $waitingTimeMinutes = $waiting_hours ? ($waiting_hours - floor($waiting_hours)) * 60 : 0;
            // $waitingTime = (!!$waitingTimeHours ? ($waitingTimeHours . ($waitingTimeHours > 1 ? " ore " : " ora ")) : "") 
            //     . ((!$waitingTimeHours || !$waitingTimeMinutes) ? "" : "e ")
            //     . (!!$waitingTimeMinutes ? ($waitingTimeMinutes . ($waitingTimeMinutes > 1 ? " minuti" : " minuto")) : "");
            
            // $waitingTime = floor($waiting_hours) . ":" . str_pad((floor($waiting_hours - floor($waiting_hours))) * 60, 2, '0', STR_PAD_LEFT) . ":00";
            $waitingTime = $waiting_hours / 24; // Converti ore in giorni (1 giorno = 24 ore)

            
            $workModes = config('app.work_modes');
            $this_ticket = [
                $ticket->id,
                $ticket->user->name . " " . $ticket->user->surname,
                $referer_name,
                $ticket->created_at,
                $ticket->ticketType->name,
                $webform_text,
                $closingDate,
                isset($ticket->is_billable) ? ($ticket->is_billable ? "Si" : "No") : "Non definito",
                $expectedProcessingTime,
                $processingTime,
                $waitingTime,
                $waiting_times,
                $workModes && $ticket->work_mode ? $workModes[$ticket->work_mode] : $ticket->work_mode,
                $ticket->is_form_correct ? "Si" : "No",
                $ticket->was_user_self_sufficient ? "Si" : "No",
                $ticket->is_user_error ? "Cliente" : "Supporto", // nel db per ora è is_user_error perchè veniva usato in un altro modo
                $ticket->ticketType->is_problem ? ($ticket->is_user_error_problem ? "Cliente" : "Supporto") : "-"
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

        return [
            $headers,
            $ticket_data
        ];
    }

    public function columnFormats(): array
    {
        return [
            // 'J' => NumberFormat::FORMAT_DATE_TIME4, // Colonna "Tempo di esecuzione" (colonna J)
            // 'J' => NumberFormat::FORMAT_DATE_TIME6, // Colonna "Tempo di esecuzione" (colonna J)
            'J' => NumberFormat::FORMAT_GENERAL, // Imposta un formato generico per evitare conflitti
            'K' => NumberFormat::FORMAT_GENERAL, // Colonna "Tempo in attesa" (colonna K)
        ];
    }

    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();
        $sheet->getStyle('J')->getNumberFormat()->setFormatCode('[h]:mm:ss'); // Formato personalizzato
        $sheet->getStyle('K')->getNumberFormat()->setFormatCode('[h]:mm:ss'); // Formato personalizzato
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [$this, 'afterSheet'],
        ];
    }
}

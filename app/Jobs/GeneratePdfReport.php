<?php

namespace App\Jobs;

use App\Mail\AvailableReportNotification;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\Ticket;
use App\Models\TicketReportPdfExport;
use App\Models\TicketStage;
use App\Models\TicketStatusUpdate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use \Exception as Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GeneratePdfReport implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 420; // Timeout in seconds
    public $tries = 2; // Number of attempts

    public $report;

    /**
     * Create a new job instance.
     */
    public function __construct(TicketReportPdfExport $report) {
        //
        $this->report = $report;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        try {
            $report = $this->report;
            $user = User::find($report->user_id);
            $company = Company::find($report->company_id);
            // Poi qui deve generare il pdf, salvarlo e restituirlo (sostituendo ticketController->batchReport e TicketReportExportController->exportBatch)

            // Parte presa da batchReport, cioè i dati che si visualizzavano nella preview web

            // Ticket che non sono ancora stati chiusi nel periodo selezionato

            // ignora i ticket creati dopo $request->end_date, escludi quelli con created_at dopo il to ,e quelli chiusi prima di $request->start_date

            $queryTo = \Carbon\Carbon::parse($report->end_date)->endOfDay()->toDateTimeString();

            $tickets = Ticket::where('company_id', $report->company_id)
                ->where('created_at', '<=', $queryTo)
                ->where('description', 'NOT LIKE', 'Ticket importato%')
                ->whereDoesntHave('statusUpdates', function ($query) use ($report) {
                    if (!empty($report->start_date)) {
                        $query->where('type', 'closing')
                            ->where('created_at', '<=', $report->start_date);
                    }
                })
                ->get();
            
            if (!$tickets->isEmpty()) {
                $tickets->load('ticketType');
            }

            $closedStageId = TicketStage::where('system_key', 'closed')->first()?->id;            

            // Questa parte va provata, perchè nella request dovrebbe esserci l'indicazione, ma verrà inserita negli optional parameters.
            // $filter = $report->type_filter;
            $optional_parameters = json_decode($report->optional_parameters);
            $filter = $optional_parameters->type ?? 'all';

            // Aperti nel periodo selezionato
            $opened_tickets_count = 0;

            // ticket ancora aperti a fine periodo selezionato
            $still_open_tickets_count = 0;

            // Conteggio ticket non fatturabili
            $unbillable_remote_tickets_count = 0;
            $unbillable_on_site_tickets_count = 0;
            // Tempo di lavoro per gestire i ticket non fatturabili (in minuti)
            $unbillable_remote_work_time = 0;
            $unbillable_on_site_work_time = 0;

            // Conteggio ticket fatturabili
            // $billable_tickets_count = 0;
            $remote_billable_tickets_count = 0;
            $on_site_billable_tickets_count = 0;
            // Tempo di lavoro per gestire i ticket fatturabili (in minuti)
            $remote_billable_work_time = 0;
            $on_site_billable_work_time = 0;

            $tickets_data = [];

            $loadErrorsOnly = false;
            $errorsString = "";

            foreach ($tickets as $ticket) {
                $ticket['category'] = $ticket->ticketType->category()->first();

                if (
                    $filter == 'all' ||
                    ($filter == 'request' && ($ticket['category']['is_request'] == 1)) ||
                    ($filter == 'incident' && ($ticket['category']['is_problem'] == 1))
                ) {

                    if (\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket->created_at)->gte(\Carbon\Carbon::createFromFormat('Y-m-d', $report->start_date))) {
                        $opened_tickets_count++;
                    }

                    // Se il ticket è ancora aperto bisogna scartarlo e mantenere solo il conteggio. (la query controlla se c'è una chiusura prima della fine del periodo selezionato. se non esiste salta un ciclo)
                    if (!TicketStatusUpdate::where('ticket_id', $ticket->id)
                        ->where('type', 'closing')
                        ->where('created_at', '<=', $queryTo)
                        ->exists()) {
                        $still_open_tickets_count++;
                        continue;
                    }

                    if(!$ticket->actual_processing_time) {
                        $loadErrorsOnly = true;
                        $errorsString .= "- #" . $ticket->id . " non ha il tempo di lavoro.";
                        continue;
                    }
                    if($ticket->is_billable === null){
                        $loadErrorsOnly = true;
                        $errorsString .= "- #" . $ticket->id . " non ha il flag di fatturabilità.";
                        continue;
                    }
                    if($ticket->work_mode === null){
                        $loadErrorsOnly = true;
                        $errorsString .= "- #" . $ticket->id . " non ha la modalità di lavoro.";
                        continue;
                    }
                    if($ticket->work_mode == "on_site" && ($ticket->admin_user_id == null)) {
                        $loadErrorsOnly = true;
                        $errorsString .= "- #" . $ticket->id . " ticket on_site senza gestore.";
                        continue;
                    }

                    // Qui aggiungere la funzione che salta il ciclo se si devono solo caricare gli errori.
                    if($loadErrorsOnly == true) {
                        continue;
                    }

                    // Dei ticket da includere bisogna contare separatamente quanti sono quelli fatturabili e quelli no, oltre ai tempi di gestione.
                    if($ticket->is_billable == 0) {
                        // Anche qui vogliamo escludere gli slave? per ora non faccio niente, poi si vedrà
                        if($ticket->work_mode == "on_site"){
                            $unbillable_on_site_tickets_count++;
                            $unbillable_on_site_work_time += $ticket->actual_processing_time;
                        } else if($ticket->work_mode == "remote") {
                            $unbillable_remote_tickets_count++;
                            $unbillable_remote_work_time += $ticket->actual_processing_time;
                        }
                    } else if($ticket->is_billable == 1) {
                        // $billable_tickets_count++;
                        // $billable_work_time += $ticket->actual_processing_time;
                        if($ticket->master_id == null) {
                            if($ticket->work_mode == "on_site"){
                                $on_site_billable_tickets_count++;
                                $on_site_billable_work_time += $ticket->actual_processing_time;
                            } else if($ticket->work_mode == "remote") {
                                $remote_billable_tickets_count++;
                                $remote_billable_work_time += $ticket->actual_processing_time;
                            }
                        } 
                        // Il ticket è slave e non va sommato il suo tempo
                    }

                    if (!$ticket->messages()->first()) {
                        continue;
                    }

                    $webform_data = json_decode($ticket->messages()->first()->message);

                    if (!$webform_data) {
                        continue;
                    }

                    if (isset($webform_data->office)) {
                        $office = $ticket->company->offices()->where('id', $webform_data->office)->first();
                        $webform_data->office = $office ? $office->name : null;
                    } else {
                        $webform_data->office = null;
                    }

                    if (isset($webform_data->referer)) {
                        $referer = User::find($webform_data->referer);
                        $webform_data->referer = $referer ? $referer->name . " " . $referer->surname : null;
                    }

                    if (isset($webform_data->referer_it)) {
                        $referer_it = User::find($webform_data->referer_it);
                        $webform_data->referer_it = $referer_it ? $referer_it->name . " " . $referer_it->surname : null;
                    }

                    $hardwareFields = $ticket->ticketType->typeFormField()->where('field_type', 'hardware')->pluck('field_label')->map(function($field) {
                        return strtolower($field);
                    })->toArray();
            
                    if(isset($webform_data)){
                        foreach ($webform_data as $key => $value) {
                            if (in_array(strtolower($key), $hardwareFields)) {
                                // value è un array di id
                                foreach ($value as $index => $hardware_id) {
                                    // Non è detto che l'hardware esista ancora. Se esiste si aggiungono gli altri valori
                                    $hardware = $ticket->hardware()->where('hardware_id', $hardware_id)->first();
                                    if ($hardware) {
                                        $webform_data->$key[$index] = $hardware->id . " (" . $hardware->make . " " 
                                            . $hardware->model . " " . $hardware->serial_number 
                                            . ($hardware->company_asset_number ? " " . $hardware->company_asset_number : "")
                                            . ($hardware->support_label ? " " . $hardware->support_label : "")
                                            . ")";
                                    } else {
                                        $webform_data->$key[$index] = $webform_data->$key[$index] . " (assente)";
                                    }
                                }
                            }
                        }
                    }

                    //? Avanzamento

                    $avanzamento = [
                        "attesa" => 0,
                        "assegnato" => 0,
                        "in_corso" => 0,
                    ];

                    foreach ($ticket->statusUpdates as $update) {
                        if ($update->type == 'status') {
                            if ($update->newStage) {
                                if ($update->newStage->system_key == 'assigned') {
                                    $avanzamento["assegnato"]++;
                                } else if ($update->newStage->is_sla_pause == 1) {
                                    $avanzamento["attesa"]++;
                                } else {
                                    $avanzamento["in_corso"]++;
                                }
                            }
                        }
                    }

                    //? Chiusura

                    $closingMessage = "";

                    $closingUpdates = TicketStatusUpdate::where('ticket_id', $ticket->id)->where('type', 'closing')->get();
                    $closingUpdate = $closingUpdates->last();

                    if ($closingUpdate) {
                        $closingMessage = $closingUpdate->content;
                    }

                    $ticket->ticket_type = $ticket->ticketType ?? null;

                    // Nasconde i dati per gli admin se l'utente non è admin
                    if ($user ? $user["is_admin"] != 1 : $report->is_user_generated == 1) {

                        $ticket->setRelation('status_updates', null);
                        $ticket->makeHidden(["admin_user_id", "group_id", "priority", "is_user_error", "actual_processing_time"]);
                    }

                    $ticket['messages'] = $ticket->messages()->with('user')->get();
                    $author = $ticket->user()->first();
                    if ($author->is_admin == 1) {
                        $ticket['opened_by'] = "Supporto";
                    } else {
                        $ticket['opened_by'] = $author->name . " " . $author->surname;
                    }
                    
                    $tickets_data[] = [
                        'data' => $ticket,
                        'webform_data' => $webform_data,
                        'status_updates' => $avanzamento,
                        'closing_message' => [
                            'message' => $closingMessage,
                            'date' => $closingUpdate ? $closingUpdate->created_at : null
                        ]

                    ];
                }
            }

            // Qui si restituisce la stringa di errore se c'è loadErrorsOnly
            if ($loadErrorsOnly == true) {
                throw new Exception($errorsString);
            }

            // Parte presa da exportBatch, quindi quella che crea il pdf

            $tickets_by_day = [];
            $ticket_graph_data = [];
            $closed_tickets_per_day = [];
            $different_categories_with_count = [];
            $different_type_with_count = [];
            $ticket_by_weekday = [
                "lunedì" => 0,
                "martedì" => 0,
                "mercoledì" => 0,
                "giovedì" => 0,
                "venerdì" => 0,
                "sabato" => 0,
                "domenica" => 0
            ];
            $ticket_by_priority = [
                "critical" => [
                    "incidents" => 0,
                    "requests" => 0,
                ],
                "high" => [
                    "incidents" => 0,
                    "requests" => 0,
                ],
                "medium" => [
                    "incidents" => 0,
                    "requests" => 0,
                ],
                "low" => [
                    "incidents" => 0,
                    "requests" => 0,
                ],
            ];
            $tickets_by_user = [];
            $ticket_by_source = [];
            $reduced_tickets = [];
            $total_incidents = 0;
            $total_requests = 0;


            $sla_data = [
                'less_than_30_minutes' => 0,
                'less_than_1_hour' => 0,
                'less_than_2_hours' => 0,
                'more_than_2_hours' => 0,
            ];

            $dates_are_more_than_one_month_apart = \Carbon\Carbon::createFromFormat('Y-m-d', $report->start_date)->diffInMonths(\Carbon\Carbon::createFromFormat('Y-m-d', $report->end_date)) > 0;
            $tickets_by_month = [];

            $closed_tickets_count = 0;
            $other_tickets_count = 0;

            $wrong_type = [
                "incident" => 0,
                "request" => 0
            ];

            $tickets_by_billable_time = [
                'billable' => [],
                'unbillable' => [],
            ];

            foreach ($tickets_data as $ticket) {
                $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->format('Y-m-d');
                if (!isset($tickets_by_day[$date])) {
                    $tickets_by_day[$date] = [];
                }
                $tickets_by_day[$date][] = $ticket;

                // Categoria e tipo

                if ($ticket['data']['ticketType']['category']['is_problem'] == 1) {

                    // Incident 

                    if (!isset($different_categories_with_count['incident'][$ticket['data']['ticketType']['category']['name']])) {
                        $different_categories_with_count['incident'][$ticket['data']['ticketType']['category']['name']] = 0;
                    }

                    $different_categories_with_count['incident'][$ticket['data']['ticketType']['category']['name']]++;

                    if (!isset($different_type_with_count['incident'][$ticket['data']['ticketType']['name']])) {
                        $different_type_with_count['incident'][$ticket['data']['ticketType']['name']] = 0;
                    }

                    $different_type_with_count['incident'][$ticket['data']['ticketType']['name']]++;

                    $total_incidents++;
                } else {

                    // Request 


                    if (!isset($different_categories_with_count['request'][$ticket['data']['ticketType']['category']['name']])) {
                        $different_categories_with_count['request'][$ticket['data']['ticketType']['category']['name']] = 0;
                    }

                    $different_categories_with_count['request'][$ticket['data']['ticketType']['category']['name']]++;

                    if (!isset($different_type_with_count['request'][$ticket['data']['ticketType']['name']])) {
                        $different_type_with_count['request'][$ticket['data']['ticketType']['name']] = 0;
                    }

                    $different_type_with_count['request'][$ticket['data']['ticketType']['name']]++;
                    $total_requests++;
                }

                // Giorno della settimana

                $weekday = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->locale('it')->isoFormat('dddd');

                if (!isset($ticket_by_weekday[$weekday])) {
                    $ticket_by_weekday[$weekday] = 0;
                }

                $ticket_by_weekday[$weekday]++;

                // Se chiuso o meno (con le modifiche di maggio 2025 qui dovrebbero essere tutti già chiusi, se non cambia niente)

                if ($ticket['data']['stage_id'] == $closedStageId) {
                    $closed_tickets_count++;
                } else {
                    $other_tickets_count++;
                }

                // Per mese

                if ($dates_are_more_than_one_month_apart) {

                    $month = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->format('Y-m');

                    if (!isset($tickets_by_month[$month])) {
                        $tickets_by_month[$month] = [
                            'incident' => 0,
                            'request' => 0
                        ];
                    }

                    if ($ticket['data']['ticketType']['category']['is_problem'] == 1) {
                        $tickets_by_month[$month]['incident']++;
                    } else {
                        $tickets_by_month[$month]['request']++;
                    }
                }

                // Per priorità

                if ($ticket['data']['ticketType']['category']['is_problem'] == 1) {
                    $ticket_by_priority[$ticket['data']['priority']]['incidents']++;
                } else {
                    $ticket_by_priority[$ticket['data']['priority']]['requests']++;
                }


                // Per utente

                if ($ticket['data']['user']['is_admin'] == 1) {

                    if (!isset($tickets_by_user['Support'])) {
                        $tickets_by_user['Support'] = 0;
                    }

                    $tickets_by_user['Support']++;
                } else {

                    if (!isset($tickets_by_user[$ticket['data']['user_id']])) {
                        $tickets_by_user[$ticket['data']['user_id']] = 0;
                    }

                    $tickets_by_user[$ticket['data']['user_id']]++;
                }

                // Per provenienza

                if (!isset($ticket_by_source[$ticket['data']['source']])) {
                    $ticket_by_source[$ticket['data']['source']] = 0;
                }

                $ticket_by_source[$ticket['data']['source']]++;


                // Presa in carica

                if ($ticket['data']['status_updates'] != null) {
                    $elapsed_minutes = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->diffInMinutes(\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['status_updates'][0]['created_at']));
                } else {
                    // $elapsed_minutes = $ticket['data']['sla_take'];
                    $elapsed_minutes = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->diffInMinutes(\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['updated_at']));
                }

                if ($elapsed_minutes < 30) {
                    $sla_data['less_than_30_minutes']++;
                } else if ($elapsed_minutes < 60) {
                    $sla_data['less_than_1_hour']++;
                } else if ($elapsed_minutes < 120) {
                    $sla_data['less_than_2_hours']++;
                } else {
                    $sla_data['more_than_2_hours']++;
                }

                // Stato attuale del ticket 

                $latest_status_update = TicketStatusUpdate::where(
                    'ticket_id',
                    $ticket['data']['id']
                )
                    ->whereIn('type', ['status', 'closing'])
                    ->where('created_at', '<', \Carbon\Carbon::parse($report->end_date)->endOfDay()->toDateTimeString())
                    ->orderBy('created_at', 'DESC')
                    ->first();

                $current_status = $latest_status_update->newStage?->name ?? "Aperto";

                // Form non corretto

                if ($ticket['data']['is_form_correct'] == 0) {
                    if ($ticket['data']['ticketType']['category']['is_problem'] == 1) {
                        $wrong_type['incident']++;
                    } else {
                        $wrong_type['request']++;
                    }
                }

                if ($ticket['closing_message']['date'] != "") {
                    $closed_at = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['closing_message']['date'])->format('d/m/Y H:i');
                } else {
                    $closed_at = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['updated_at'])->format('d/m/Y H:i');
                }

                // Fatturabilità e categoria
                if ($ticket['data']['is_billable'] == 1) {
                    // Se non esiste ancora la categoria, la creo
                    if (!isset($tickets_by_billable_time['billable'][$ticket['data']['ticketType']['category']['name']])) {
                        $tickets_by_billable_time['billable'][$ticket['data']['ticketType']['category']['name']] = 0;
                    }
                    // Incrementa il conteggio per la categoria
                    $tickets_by_billable_time['billable'][$ticket['data']['ticketType']['category']['name']]+= $ticket['data']['actual_processing_time'];
                } else {
                    if(!isset($tickets_by_billable_time['unbillable'][$ticket['data']['ticketType']['category']['name']])) {
                        $tickets_by_billable_time['unbillable'][$ticket['data']['ticketType']['category']['name']] = 0;
                    }
                    $tickets_by_billable_time['unbillable'][$ticket['data']['ticketType']['category']['name']]+= $ticket['data']['actual_processing_time'];
                }

                // Gestore viene inserito nei ticket on-site (sarebbe chi è andato dal cliente)
                $handler = $ticket['data']['admin_user_id'] != null ? User::find($ticket['data']['admin_user_id']) : null;
                $handlerFullName = ""; 
                if($handler){
                    $handlerFullName = $handler->surname ? $handler->surname . ' ' . strtoupper(substr($handler->name, 0, 1)) . '.' : $handler->name;
                }

                // Ticket ridotto

                $reduced_ticket = [
                    "id" => $ticket['data']['id'],
                    "incident_request" => $ticket['data']['ticketType']['category']['is_problem'] == 1 ? "Incident" : "Request",
                    "category" => $ticket['data']['ticketType']['category']['name'],
                    "type" => $ticket['data']['ticketType']['name'],
                    "opened_by_initials" => $ticket['data']['user']['is_admin'] == 1 ? "SUP" : (strtoupper($ticket['data']['user']['name'][0]) . ". " . $ticket['data']['user']['surname'] ? (strtoupper($ticket['data']['user']['surname'][0]) . ".") : ""),
                    "opened_by" => $ticket['data']['user']['is_admin'] == 1 ? "Supporto" : $ticket['data']['user']['name'] . " " . $ticket['data']['user']['surname'],
                    "opened_at" => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->format('d/m/Y H:i'),
                    "webform_data" => $ticket['webform_data'],
                    "status_updates" => $ticket['status_updates'],
                    "description" => $ticket['data']['description'],
                    "closing_message" => $ticket['closing_message'],
                    "closed_at" => $ticket['data']['stage_id'] == $closedStageId ? $closed_at : "",
                    'should_show_more' => false,
                    'ticket_frontend_url' => env('FRONTEND_URL') . '/support/user/ticket/' . $ticket['data']['id'],
                    'current_status' => $current_status,
                    'is_billable' => $ticket['data']['is_billable'],
                    'actual_processing_time' => $ticket['data']['actual_processing_time'],
                    'master_id' => $ticket['data']['master_id'],
                    'is_master' => $ticket['data']['ticketType']['is_master'],
                    'slave_ids' => Ticket::where('master_id', $ticket['data']['id'])->pluck('id')->toArray(),
                    'handler_full_name' => $handlerFullName,
                    'work_mode' => $ticket['data']['work_mode'],
                ];

                if (count($ticket['data']['messages']) > 3) {

                    $ticket['data']['messages']->forget(0);
                    $ticket['data']['messages'] = $ticket['data']['messages']->take(3);

                    foreach ($ticket['data']['messages'] as $key => $message) {
                        $reduced_ticket['messages'][] = [
                            "id" => $message['id'],
                            "user" => $message['user']['is_admin'] == 1 ? "Supporto - Update" : $message['user']['name'] . " " . $message['user']['surname'],
                            "message" => $message['message'],
                            "created_at" => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $message['created_at'])->format('d/m/Y H:i')
                        ];
                    }
                    $reduced_ticket['should_show_more'] = true;
                } else {
                    foreach ($ticket['data']['messages'] as $key => $message) {
                        $reduced_ticket['messages'][] = [
                            "id" => $message['id'],
                            "user" => $message['user']['is_admin'] == 1 ? "Supporto - Update" : $message['user']['name'] . " " . $message['user']['surname'],
                            "message" => $message['message'],
                            "created_at" => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $message['created_at'])->format('d/m/Y H:i')
                        ];
                    }
                }


                $reduced_tickets[] = $reduced_ticket;
            }

            usort($reduced_tickets, function ($a, $b) {
                $categoryComparison = strcmp($a['category'], $b['category']);
                if ($categoryComparison === 0) {
                    return strtotime($a['opened_at']) - strtotime($b['opened_at']);
                }
                return $categoryComparison;
            });



            for ($date = \Carbon\Carbon::createFromFormat('Y-m-d', $report->start_date); $date <= \Carbon\Carbon::createFromFormat('Y-m-d', $report->end_date); $date->addDay()) {
                if (isset($tickets_by_day[$date->format('Y-m-d')])) {

                    $incidents = 0;
                    $requests = 0;

                    foreach ($tickets_by_day[$date->format('Y-m-d')] as $ticket) {

                        if ($ticket['data']['ticketType']['category']['is_problem'] == 1) {
                            $incidents++;
                        } else {
                            $requests++;
                        }
                    }

                    $ticket_graph_data[$date->format('Y-m-d')] = [
                        'incidents' => $incidents,
                        'requests' => $requests
                    ];

                    $closed_tickets_per_day[$date->format('Y-m-d')] = $incidents + $requests;
                }
            }




            /** Grafici */

            $charts_base_url = "https://quickchart.io/chart?c=";
            $base_incident_color = "#ff6f6a";
            $base_request_color = "#9bbed0";

            // 1 - Numero di Ticket Chiusi per Categoria
            $ticket_by_category_data = [
                "type" => "bar",
                "data" => [
                    "labels" => [
                        "Request",
                        "Incident"
                    ],
                    "datasets" => [[
                        "label" => "Numero di Ticket",
                        "data" => [
                            $total_requests,
                            $total_incidents
                        ],
                        "backgroundColor" => [$base_request_color, $base_incident_color],
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Ticket Chiusi per Categoria"],
                    "legend" => ["display" => false],

                ]
            ];

            if (($total_requests < 5) || ($total_incidents < 5)) {
                $ticket_by_category_data['options']['scales']['yAxes'][0]['ticks']['beginAtZero'] = true;
                $ticket_by_category_data['options']['scales']['yAxes'][0]['ticks']['stepSize'] = 1;
                $ticket_by_category_data['options']['scales']['yAxes'][0]['ticks']['max'] = 10;
            } else {
                $ticket_by_category_data['options']["plugins"]["datalabels"] = [
                    "display" => true,
                    "color" => "white",
                    "align" => "center",
                    "anchor" => "center",
                    "font" => [
                        "weight" => "bold"
                    ]
                ];
            }


            $ticket_by_category_url = $charts_base_url . urlencode(json_encode($ticket_by_category_data));



            // 2 - Ticket chiusi nel tempo 

            $ticket_closed_time_data = [
                "type" => "line",
                "data" => [
                    "labels" => array_keys($ticket_graph_data),
                    "datasets" => [
                        [
                            "label" => "Incidents",
                            "data" => array_values(array_column($ticket_graph_data, 'incidents')),
                            "borderColor" => $base_incident_color,
                            "fill" => false
                        ],
                        [
                            "label" => "Requests",
                            "data" => array_values(array_column($ticket_graph_data, 'requests')),
                            "borderColor" => $base_request_color,
                            "fill" => false
                        ]
                    ]
                ],
                "options" => [
                    "title" => [
                        "display" => true,
                        "text" => "Ticket Chiusi nel tempo"
                    ],
                    "legend" => [
                        "display" => true,
                    ],

                ]
            ];

            // $maxValue = max(array_values($ticket_closed_time_data['data']['datasets'][0]['data']));
            // if ($maxValue < 5) {
            //     $ticket_closed_time_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            //     $ticket_closed_time_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
            //     $ticket_closed_time_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
            // }

            $ticket_closed_time_url = $charts_base_url . urlencode(json_encode($ticket_closed_time_data));

            // 3 - Grafico a barre categoria di ticket 

            $different_categories_with_count['incident'] = collect($different_categories_with_count['incident'] ?? [])
                ->sortByDesc(function ($count) {
                    return $count;
                })
                // ->take(5)
                ->toArray();

            $different_categories_with_count['request'] = collect($different_categories_with_count['request'] ?? [])
                ->sortByDesc(function ($count) {
                    return $count;
                })
                // ->take(5)
                ->toArray();

            $ticket_by_category_incident_bar_data = [
                "type" => "horizontalBar",
                "data" => [
                    "labels" => array_map(function ($label) {
                        return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                    }, array_keys($different_categories_with_count['incident'])),
                    "datasets" => [[
                        "data" => [
                            ...array_values($different_categories_with_count['incident']),
                        ],
                        "backgroundColor" => $this->getColorShades(count(array_keys($different_categories_with_count['incident'])), true),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Incident per Categoria"],
                    "legend" => ["display" => false],
                    "scales" => [
                        "xAxes" => [
                            [
                                "ticks" => [
                                    "beginAtZero" => true,
                                ]
                            ]
                        ]
                    ],
                    "plugins" => [
                        "datalabels" => [
                           "display" => true,
                            "color" => "white",
                            "align" => "center",
                            "anchor" => "center",
                            "font" => [
                                "weight" => "bold"
                            ]
                        ]
                    ]
                ]
            ];
            $maxValue = max([0, ...array_values($ticket_by_category_incident_bar_data['data']['datasets'][0]['data'])]);
            if ($maxValue < 10) {
                // $ticket_by_category_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
                $ticket_by_category_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
                $ticket_by_category_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
            } else {
                // Inseriti in ogni caso
                // $ticket_by_category_incident_bar_data['options']["plugins"]["datalabels"] = [
                //     "display" => true,
                //     "color" => "white",
                //     "align" => "center",
                //     "anchor" => "center",
                //     "font" => [
                //         "weight" => "bold"
                //     ]
                // ];
            }

            $ticket_by_category_incident_bar_url = $charts_base_url . urlencode(json_encode($ticket_by_category_incident_bar_data));

            $ticket_by_category_request_bar_data = [
                "type" => "horizontalBar",
                "data" => [
                    "labels" => array_map(function ($label) {
                        return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                    }, array_keys($different_categories_with_count['request'])),
                    "datasets" => [[
                        "data" => [
                            ...array_values($different_categories_with_count['request']),
                        ],
                        "backgroundColor" => $this->getColorShades(count(array_keys($different_categories_with_count['request'])), true, true, false, "blue"),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Request più frequenti per Categoria"],
                    "legend" => ["display" => false],
                    "scales" => [
                        "xAxes" => [
                            [
                                "ticks" => [
                                    "beginAtZero" => true,
                                ]
                            ]
                        ]
                    ],
                    "plugins" => [
                        "datalabels" => [
                            "display" => true,
                            "color" => "white",
                            "align" => "center",
                            "anchor" => "center",
                            "font" => [
                                "weight" => "bold"
                            ]
                        ]
                    ]
                ]
            ];

            $maxValue = count($ticket_by_category_request_bar_data['data']['datasets'][0]['data']) > 0 
                ? max([0, ...array_values($ticket_by_category_request_bar_data['data']['datasets'][0]['data'])]) 
                : 0;
            if ($maxValue < 10) {
                // beginAtZero già inserito prima
                // $ticket_by_category_request_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
                $ticket_by_category_request_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
                $ticket_by_category_request_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
            } else {
                // Inseriti in ogni caso
                // $ticket_by_category_request_bar_data['options']["plugins"]["datalabels"] = [
                //     "display" => true,
                //     "color" => "white",
                //     "align" => "center",
                //     "anchor" => "center",
                //     "font" => [
                //         "weight" => "bold"
                //     ]
                // ];
            }

            $ticket_by_category_request_bar_url = $charts_base_url . urlencode(json_encode($ticket_by_category_request_bar_data));

            // 4 - Grafico tipo di ticket

            $different_type_with_count['incident'] = collect($different_type_with_count['incident'] ?? [])
                ->sortByDesc(function ($count) {
                    return $count;
                })
                ->take(5)
                ->toArray();

            $different_type_with_count['request'] = collect($different_type_with_count['request'] ?? [])
                ->sortByDesc(function ($count) {
                    return $count;
                })
                ->take(5)
                ->toArray();


            $ticket_by_type_incident_bar_data = [
                "type" => "horizontalBar",
                "data" => [
                    "labels" => array_map(function ($label) {
                        return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                    }, array_keys($different_type_with_count['incident'])),
                    "datasets" => [[
                        "data" => [
                            ...array_values($different_type_with_count['incident']),
                        ],
                        "backgroundColor" => $this->getColorShades(count(array_keys($different_type_with_count['incident'])), true),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Top 5 Incident per Tipo"],
                    "legend" => ["display" => false],
                    "scales" => [
                        "xAxes" => [
                            [
                                "ticks" => [
                                    "beginAtZero" => true,
                                ]
                            ]
                        ]
                    ],
                    "plugins" => [
                        "datalabels" => [
                            "display" => true,
                            "color" => "white",
                            "align" => "center",
                            "anchor" => "center",
                            "font" => [
                                "weight" => "bold"
                            ]
                        ]
                    ]
                ]
            ];

            $maxValue = max([0, ...array_values($ticket_by_type_incident_bar_data['data']['datasets'][0]['data'])]);
            if ($maxValue < 10) {
                // $ticket_by_type_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
                $ticket_by_type_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
                $ticket_by_type_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
            } else {
                // $ticket_by_type_incident_bar_data['options']["plugins"]["datalabels"] = [
                //     "display" => true,
                //     "color" => "white",
                //     "align" => "center",
                //     "anchor" => "center",
                //     "font" => [
                //         "weight" => "bold"
                //     ]
                // ];
            }

            $ticket_by_type_incident_bar_url = $charts_base_url . urlencode(json_encode($ticket_by_type_incident_bar_data));

            $ticket_by_type_request_bar_data = [
                "type" => "horizontalBar",
                "data" => [
                    "labels" => array_map(function ($label) {
                        return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                    }, array_keys($different_type_with_count['request'])),
                    "datasets" => [[
                        "data" => [
                            ...array_values($different_type_with_count['request']),
                        ],
                        "backgroundColor" => $this->getColorShades(count(array_keys($different_type_with_count['request'])), true, true, false, "blue"),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Top 5 Request per Tipo"],
                    "legend" => ["display" => false],
                    "plugins" => [
                        "datalabels" => [
                            "display" => true,
                            "color" => "white",
                            "align" => "center",
                            "anchor" => "center",
                            "font" => [
                                "weight" => "bold"
                            ]
                        ]
                    ]
                ]
            ];

            $maxValue = count($ticket_by_type_request_bar_data['data']['datasets'][0]['data']) > 0 
                ? max([0, ...array_values($ticket_by_type_request_bar_data['data']['datasets'][0]['data'])]) 
                : 0;
                
            $ticket_by_type_request_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            if ($maxValue < 10) {
                // $ticket_by_type_request_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
                $ticket_by_type_request_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
                $ticket_by_type_request_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
            } else {
                // $ticket_by_type_request_bar_data['options']["plugins"]["datalabels"] = [
                //     "display" => true,
                //     "color" => "white",
                //     "align" => "center",
                //     "anchor" => "center",
                //     "font" => [
                //         "weight" => "bold"
                //     ]
                // ];
            }

            $ticket_by_type_request_bar_url = $charts_base_url . urlencode(json_encode($ticket_by_type_request_bar_data));

            // 5 - Provenienza ticket

            $ticket_by_source_data = [
                "type" => "horizontalBar",
                "data" => [
                    "labels" => ["Email", "Telefono", "Tecnico onsite", "Piattaforma", "Supporto", "Automatico"],
                    "datasets" => [[
                        "label" => "Numero di Ticket",
                        "data" => [
                            $ticket_by_source['email'] ?? 0,
                            $ticket_by_source['phone'] ?? 0,
                            $ticket_by_source['on_site_technician'] ?? 0,
                            $ticket_by_source['platform'] ?? 0,
                            $ticket_by_source['internal'] ?? 0,
                            $ticket_by_source['automatic'] ?? 0
                        ],
                        "backgroundColor" => $this->getColorShades(5, true)
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Ticket per Provenienza"],
                    "legend" => ["display" => false],
                    "plugins" => [
                        "datalabels" => [
                            "display" => true,
                            "color" => "white",
                            "align" => "center",
                            "anchor" => "center",
                            "font" => [
                                "weight" => "bold"
                            ]
                        ]
                    ]
                ]
            ];

            $ticket_by_source_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $maxValue = max([0, ...array_values($ticket_by_source_data['data']['datasets'][0]['data'])]);
            if ($maxValue < 10) {
                $ticket_by_source_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
                $ticket_by_source_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
            } else {
                // $ticket_by_source_data['options']["plugins"]["datalabels"] = [
                //     "display" => true,
                //     "color" => "white",
                //     "align" => "center",
                //     "anchor" => "center",
                //     "font" => [
                //         "weight" => "bold"
                //     ]
                // ];
            }

            $ticket_by_source_url = $charts_base_url . urlencode(json_encode($ticket_by_source_data));

            // 6 - Ticket per giorno della settimana

            $daysOfWeek = ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
            $ticket_by_weekday = array_merge(array_flip($daysOfWeek), $ticket_by_weekday);

            $ticket_by_weekday_data = [
                "type" => "bar",
                "data" => [
                    "labels" => array_keys($ticket_by_weekday),
                    "datasets" => [[
                        "label" => "Numero di Ticket",
                        "data" => array_values($ticket_by_weekday),
                        "backgroundColor" => $this->getColorShades(7, false, false, true),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Ticket per Giorno della Settimana"],
                    "legend" => ["display" => false],

                ]
            ];

            $ticket_by_weekday_data['options']['scales']['yAxes'][0]['ticks']['beginAtZero'] = true;
            $maxValue = max([0, ...array_values($ticket_by_weekday_data['data']['datasets'][0]['data'])]);
            if ($maxValue < 10) {
                $ticket_by_weekday_data['options']['scales']['yAxes'][0]['ticks']['stepSize'] = 1;
                $ticket_by_weekday_data['options']['scales']['yAxes'][0]['ticks']['max'] = 10;
            } else {
                $ticket_by_weekday_data['options']["plugins"]["datalabels"] = [
                    "display" => true,
                    "color" => "white",
                    "align" => "center",
                    "anchor" => "center",
                    "font" => [
                        "weight" => "bold"
                    ]
                ];
            }


            $ticket_by_weekday_url = $charts_base_url . urlencode(json_encode($ticket_by_weekday_data));

            // 7 - Ticket per mese

            if ($dates_are_more_than_one_month_apart) {

                $ticket_by_month_data = [
                    "type" => "bar",
                    "data" => [
                        "labels" => array_keys($tickets_by_month),
                        "datasets" => [
                            [
                                "label" => "Incidents",
                                "data" => array_values(array_column($tickets_by_month, 'incident')),
                                "backgroundColor" => $base_incident_color
                            ],
                            [
                                "label" => "Requests",
                                "data" => array_values(array_column($tickets_by_month, 'request')),
                                "backgroundColor" => $base_request_color
                            ]
                        ]
                    ],
                    "options" => [
                        "title" => [
                            "display" => true,
                            "text" => "Ticket per Mese"
                        ],
                        "legend" => [
                            "display" => true,
                        ],
                        "scales" => [
                            "xAxes" => [[
                                "stacked" => true
                            ]],
                            "yAxes" => [[
                                "stacked" => true
                            ]]
                        ],
                        "plugins" => [
                            "datalabels" => [
                                "display" => true,
                                "color" => "white",
                                "font" => [
                                    "size" => 8
                                ]
                            ]
                        ]
                    ]
                ];

                $ticket_per_month_url = $charts_base_url . urlencode(json_encode($ticket_by_month_data));
            } else {
                $ticket_per_month_url = "";
            }

            // 8 - Barre per priorità dei ticket

            $ticket_by_priority = [
                "Critica" => $ticket_by_priority['critical'] ?? [
                    "incidents" => 0,
                    "requests" => 0,
                ],
                "Alta" => $ticket_by_priority['high'] ?? [
                    "incidents" => 0,
                    "requests" => 0,
                ],
                "Media" => $ticket_by_priority['medium'] ?? [
                    "incidents" => 0,
                    "requests" => 0,
                ],
                "Bassa" => $ticket_by_priority['low'] ?? [
                    "incidents" => 0,
                    "requests" => 0,
                ],
            ];

            $ticket_by_priority_bar_data = [
                "type" => "horizontalBar",
                "data" => [
                    "labels" => array_keys($ticket_by_priority),
                    "datasets" => [
                        [
                            "label" => "Incidents",
                            "data" => array_values(array_column($ticket_by_priority, 'incidents')),
                            "backgroundColor" => $base_incident_color
                        ],
                        [
                            "label" => "Requests",
                            "data" => array_values(array_column($ticket_by_priority, 'requests')),
                            "backgroundColor" => $base_request_color
                        ]
                    ]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Ticket per Priorità"],
                    "legend" => ["display" => true],
                    "scales" => [
                        "xAxes" => [[
                            "stacked" => true
                        ]],
                        "yAxes" => [[
                            "stacked" => true
                        ]]
                    ],
                    "plugins" => [
                        "datalabels" => [
                            "display" => true,
                            "color" => "white",
                            "font" => [
                                "size" => 8
                            ]
                        ]
                    ]
                ]
            ];

            $ticket_by_priority_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $maxValue = max([0, ...array_values($ticket_by_priority_bar_data['data']['datasets'][0]['data'])]);
            if ($maxValue < 10) {
                // $ticket_by_priority_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
                $ticket_by_priority_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
                $ticket_by_priority_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
            }


            $ticket_by_priority_url = $charts_base_url . urlencode(json_encode($ticket_by_priority_bar_data));

            // 9 - Ticket per utente

            $tickets_by_user = collect($tickets_by_user)->sortByDesc(function ($count) {
                return $count;
            })->toArray();


            $tickets_by_user_data = [
                "type" => "bar",
                "data" => [
                    "labels" => array_map(function ($label) {
                        return strlen($label) > 20 ? substr($label, 0, 17) . '...' : $label;
                    }, array_map(function ($user_id) use (&$userCache) {

                        if ($user_id == 'Support') {
                            return 'Supporto';
                        }

                        if (!isset($userCache[$user_id])) {
                            $user = User::find($user_id);
                            $userCache[$user_id] = $user->name . ' ' . $user->surname;
                        }
                        return $userCache[$user_id];
                    }, array_keys($tickets_by_user))),
                    "datasets" => [[
                        "label" => "Numero di Ticket",
                        "data" => array_values($tickets_by_user),
                        "backgroundColor" => $this->getColorShadesForUsers(count(array_keys($tickets_by_user)), true),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Ticket per Utente"],
                    "legend" => ["display" => false],

                ]
            ];

            $maxValue = max([0, ...array_values($tickets_by_user_data['data']['datasets'][0]['data'])]);

            $tickets_by_user_data['options']['scales']['yAxes'][0]['ticks']['beginAtZero'] = true;
            $tickets_by_user_data['options']['scales']['yAxes'][0]['ticks']['stepSize'] = $maxValue <= 10 
                ? 1
                : ceil($maxValue / 10 / 5) * 5;
            if ($maxValue < 10) {
                // $tickets_by_user_data['options']['scales']['yAxes'][0]['ticks']['beginAtZero'] = true;
                $tickets_by_user_data['options']['scales']['yAxes'][0]['ticks']['stepSize'] = 1;
                $tickets_by_user_data['options']['scales']['yAxes'][0]['ticks']['max'] = 10;
            } else {
                $tickets_by_user_data['options']["plugins"]["datalabels"] = [
                    "display" => true,
                    "color" => "white",
                    "align" => "center",
                    "anchor" => "center",
                    "font" => [
                        "weight" => "bold"
                    ]
                ];
            }


            $tickets_by_user_url = $charts_base_url . urlencode(json_encode($tickets_by_user_data));

            // 10 - SLA

            $tickets_sla_data = [
                "type" => "doughnut",
                "data" => [
                    "labels" => [
                        "Meno di 30 minuti",
                        "Meno di 1 ora",
                        "Meno di 2 ore",
                        "Più di 2 ore"
                    ],
                    "datasets" => [[
                        "data" => [
                            $sla_data['less_than_30_minutes'],
                            $sla_data['less_than_1_hour'],
                            $sla_data['less_than_2_hours'],
                            $sla_data['more_than_2_hours']
                        ],
                        "backgroundColor" => $this->getColorShades(4, true, true, false),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "SLA"],
                    "legend" => [
                        "display" => true,
                        "position" => "bottom",
                        "labels" => [
                            "boxWidth" => 20,
                            "padding" => 20,
                            "usePointStyle" => true
                        ]
                    ],
                    "plugins" => [
                        "datalabels" => [
                            "color" => "white",
                            "font" => [
                                "weight" => "bold"
                            ]
                        ]
                    ]
                ]
            ];

            $tickets_sla_url = $charts_base_url . urlencode(json_encode($tickets_sla_data));

            // 11 - Form non corretto 

            $wrong_type_data = [
                "type" => "bar",
                "data" => [
                    "labels" => [
                        "Request",
                        "Incident"
                    ],
                    "datasets" => [[
                        "label" => "Numero di Ticket",
                        "data" => [
                            $wrong_type['request'],
                            $wrong_type['incident']
                        ],
                        "backgroundColor" => [$base_request_color, $base_incident_color],
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Form non corretto"],
                    "legend" => ["display" => false],
                    "scales" => [
                        "yAxes" => [
                            [
                                "ticks" => [
                                    "beginAtZero" => true,
                                    "stepSize" => 1,
                                    "max" => 10
                                ]
                            ]
                        ]
                    ],
                    "plugins" => [
                        "datalabels" => [
                            "display" => true,
                            "color" => "white",
                            "align" => "center",
                            "anchor" => "center",
                            "font" => [
                                "weight" => "bold"
                            ]
                        ]
                    ]
                ]
            ];

            $maxValue = max([0, ...array_values($wrong_type_data['data']['datasets'][0]['data'])]);
            if ($maxValue < 10) {
                // $wrong_type_data['options']['scales']['yAxes'][0]['ticks']['beginAtZero'] = true;
                $wrong_type_data['options']['scales']['yAxes'][0]['ticks']['stepSize'] = 1;
                $wrong_type_data['options']['scales']['yAxes'][0]['ticks']['max'] = 10;
            } else {
                // $wrong_type_data['options']["plugins"]["datalabels"] = [
                //     "display" => true,
                //     "color" => "white",
                //     "align" => "center",
                //     "anchor" => "center",
                //     "font" => [
                //         "weight" => "bold"
                //     ]
                // ];
            }

            $wrong_type_url = $charts_base_url . urlencode(json_encode($wrong_type_data));

            // 12 - Fatturabili

            $billable_tickets = collect($tickets_by_billable_time['billable'] ?? [])
                ->sortByDesc(function ($value) {
                    return $value;
                })
                ->toArray();

            $billable_tickets_time_data = [
                "type" => "horizontalBar",
                "data" => [
                    "labels" => array_slice(array_keys($billable_tickets), 0, 5),
                    "datasets" => [[
                        "label" => "Numero di Ticket",
                        "data" => [
                            ...array_map(function ($el) {
                                    return is_numeric($el) ? round(((int) $el / 60), 2) : 0;
                                }, 
                                array_slice(array_values($billable_tickets), 0, 5)
                            ),
                        ],
                        "backgroundColor" => $this->getColorShades(5, true),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Top tempi ticket fatturabili per Categoria"],
                    "legend" => ["display" => false],
                ]
            ];

            $maxValue = max([0, ...array_values($billable_tickets_time_data['data']['datasets'][0]['data'])]);

            $billable_tickets_time_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $billable_tickets_time_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = $maxValue < 20 
                ? 1
                : ceil($maxValue / 10 / 5) * 5; // Calcola il passo in base a $maxValue con incrementi di 5. massimo 10 step.
            $billable_tickets_time_data['options']['scales']['xAxes'][0]['ticks']['max'] = ceil($maxValue / 5) * 5; // Max value in hours
            $billable_tickets_time_data['options']["plugins"]["datalabels"] = [
                "display" => true,
                "color" => "white",
                "align" => "center",
                "anchor" => "center",
                "font" => [
                    "weight" => "bold"
                ]
            ];
            
            $billable_tickets_time_data['options']["scales"]["xAxes"][0]["scaleLabel"] = [
                "display" => true,
                "labelString" => "Ore"
            ];

            $ticket_by_billable_time_url = $charts_base_url . urlencode(json_encode($billable_tickets_time_data));

            // 13 - Non fatturabili

            $unbillable_tickets = collect($tickets_by_billable_time['unbillable'] ?? [])
                ->sortByDesc(function ($value) {
                    return $value;
                })
                ->toArray();

            $unbillable_tickets_time_data = [
                "type" => "horizontalBar",
                "data" => [
                    "labels" => array_slice(array_keys($unbillable_tickets), 0, 5),
                    "datasets" => [[
                        "label" => "Numero di Ticket",
                        "data" => [
                            ...array_map(function ($el) {
                                    return is_numeric($el) ? round(((int) $el / 60), 2) : 0;
                                }, 
                                array_slice(array_values($unbillable_tickets), 0, 5)
                            ),
                        ],
                        // private function getColorShades($number = 1, $random = false, $fromDarker = true, $fromLighter = false, $shadeColor = "red")
                        // $this->getColorShades(count(array_keys($different_type_with_count['request'])), true, true, false, "blue"),
                        "backgroundColor" => $this->getColorShades(5, false, true, false, "green"),
                        "maxBarThickness" => 40
                    ]]
                ],
                "options" => [
                    "title" => ["display" => true, "text" => "Top tempi ticket non fatturabili per Categoria"],
                    "legend" => ["display" => false],
                ]
            ];

            $maxValue = max([0, ...array_values($unbillable_tickets_time_data['data']['datasets'][0]['data'])]);

            $unbillable_tickets_time_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $unbillable_tickets_time_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = $maxValue < 20 
                ? 1
                : ceil($maxValue / 10 / 5) * 5; // Calcola il passo in base a $maxValue con incrementi di 5. massimo 10 step.
            $unbillable_tickets_time_data['options']['scales']['xAxes'][0]['ticks']['max'] = ceil($maxValue / 5) * 5; // Max value in hours
            $unbillable_tickets_time_data['options']["plugins"]["datalabels"] = [
                "display" => true,
                "color" => "white",
                "align" => "center",
                "anchor" => "center",
                "font" => [
                    "weight" => "bold"
                ]
            ];
            
            $unbillable_tickets_time_data['options']["scales"]["xAxes"][0]["scaleLabel"] = [
                "display" => true,
                "labelString" => "Ore"
            ];

            $ticket_by_unbillable_time_url = $charts_base_url . urlencode(json_encode($unbillable_tickets_time_data));


            // Logo da usare

            $brand = $company->brands()->first();
            $google_url = $brand->withGUrl()->logo_url;

            $data = [
                'tickets' => $reduced_tickets,
                'title' => "Esportazione tickets",
                'date_from' => \Carbon\Carbon::createFromFormat('Y-m-d', $report->start_date),
                'date_to' => \Carbon\Carbon::createFromFormat('Y-m-d', $report->end_date),
                'company' => $company,
                'request_number' => $total_requests,
                'incident_number' => $total_incidents,
                'opened_tickets_count' => $opened_tickets_count,
                'closed_tickets_count' => $closed_tickets_count,
                'still_open_tickets_count' => $still_open_tickets_count,
                'other_tickets_count' => $other_tickets_count,
                'unbillable_on_site_tickets_count' => $unbillable_on_site_tickets_count,
                'unbillable_remote_tickets_count' => $unbillable_remote_tickets_count,
                'unbillable_on_site_work_time' => $unbillable_on_site_work_time,
                'unbillable_remote_work_time' => $unbillable_remote_work_time,
                'remote_billable_tickets_count' => $remote_billable_tickets_count,
                'on_site_billable_tickets_count' => $on_site_billable_tickets_count,
                'remote_billable_work_time' => $remote_billable_work_time,
                'on_site_billable_work_time' => $on_site_billable_work_time,
                'ticket_graph_data' => $ticket_graph_data,
                'ticket_by_category_url' => $ticket_by_category_url,
                'ticket_closed_time_url' => $ticket_closed_time_url,
                'ticket_by_category_incident_bar_url' => $ticket_by_category_incident_bar_url,
                'ticket_by_category_request_bar_url' => $ticket_by_category_request_bar_url,
                'ticket_by_type_incident_bar_url' => $ticket_by_type_incident_bar_url,
                'ticket_by_type_request_bar_url' => $ticket_by_type_request_bar_url,
                'ticket_by_source_url' => $ticket_by_source_url,
                'ticket_by_weekday_url' => $ticket_by_weekday_url,
                'dates_are_more_than_one_month_apart' => $dates_are_more_than_one_month_apart,
                'ticket_per_month_url' => $ticket_per_month_url,
                'ticket_by_priority_url' => $ticket_by_priority_url,
                'tickets_by_user_url' => $tickets_by_user_url,
                'tickets_sla_url' => $tickets_sla_url,
                'logo_url' => $google_url,
                'wrong_type_url' => $wrong_type_url,
                'ticket_by_billable_time_url' => $ticket_by_billable_time_url,
                'ticket_by_unbillable_time_url' => $ticket_by_unbillable_time_url,
                'filter' => $filter,
            ];


            Pdf::setOptions([
                'dpi' => 150,
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true
            ]);
            $pdf = Pdf::loadView('pdf.exportpdf', $data);

            if(!$pdf) {
                throw new Exception("PDF generation failed");
            }

            $disk = \App\Http\Controllers\FileUploadController::getStorageDisk();
            Storage::disk($disk)->put($report->file_path, $pdf->output());

            // Se ci mette troppo tempo potremmo rispondere ok alla creazione del report e generarlo tramite un job, che quando ha fatto aggiorna il report
            
            $report->update([
                'is_generated' => true,
                'error_message' => null,
                'is_failed' => false
            ]);

            if ($report->send_email) {
                $admins = $company->users()->where('is_admin', true)->get();
                foreach ($admins as $admin) {
                    Mail::to($admin)->send(new AvailableReportNotification($report));
                }
            }
        } catch (Exception $e) {
            $shortenedMessage = $e->getMessage();
            if (strlen($shortenedMessage) > 500) {
                $shortenedMessage = substr($shortenedMessage, 0, 500) . '...';
            }

            if ($this->attempts() >= $this->tries) {
                $this->report->is_failed = true;
                
                $this->report->error_message = 'Error generating the report at ' . now() . '. ' . $shortenedMessage;

                $this->report->save();
            } else {
                throw $e;
            }
        }
    }

    private function getColorShades($number = 1, $random = false, $fromDarker = true, $fromLighter = false, $shadeColor = "red") {

        if ($shadeColor == "red") {
            $colorShadesBank = [
                '#5c1310',
                '#741815',
                '#8b1d19',
                '#a2221d',
                '#b92621',
                '#d02b25',
                '#e73029',
                '#e9453e',
                '#ec5954',
                '#ee6e69',
                '#f1837f',
                '#f39894',
                '#f5aca9',
                '#f8c1bf',
                '#fad6d4',
                '#fad6d4',
            ];
        } else if ($shadeColor == "green") {
            $colorShadesBank = [
                '#0d3b1e',
                '#145c2a',
                '#1b7d36',
                '#22a042',
                '#29c24e',
                '#30e45a',
                '#45e96e',
                '#59ee82',
                '#6ef396',
                '#83f8aa',
                '#98fdbe',
                '#acf3c1',
                '#c1fad6',
                '#d6fae6',
                '#e6faef',
                '#e6faef'
            ];
        } else {
            $colorShadesBank = [
                '#00090e',
                '#01121c',
                '#011c29',
                '#022537',
                '#032e45',
                '#033753',
                '#044061',
                '#044a6e',
                '#05537c',
                '#055c8a',
                '#1e6c96',
                '#377da1',
                '#508dad',
                '#699db9',
                '#82aec5',
                '#9bbed0'
            ];
        }



        if ($random) {
            // shuffle($colorShadesBank);

            $colorShades = [];
            $groups = array_chunk($colorShadesBank, 4);

            for ($i = 0; $i < $number; $i++) {
                $colorShades[] = $groups[$i % count($groups)][rand(0, 3)];
            }

            return $colorShades;
        }

        if ($fromLighter) {
            $colorShadesBank = array_reverse($colorShadesBank);
        }

        while ($number > count($colorShadesBank)) {
            $colorShadesBank = array_merge($colorShadesBank, $colorShadesBank);
        }

        return array_slice($colorShadesBank, 0, $number);
    }

    private function getColorShadesForUsers($number = 1, $random = false) {
        $colorShadesBank = [
            "#f97316",
            "#f59e0b",
            "#eab308",
            "#84cc16",
            "#22c55e",
            "#10b981",
            "#14b8a6",
            "#06b6d4",
            "#0ea5e9",
            "#2563eb",
            "#6366f1",
            "#8b5cf6",
            "#a855f7",
            "#d946ef",
            "#db2777",
            "#f43f5e"
        ];

        if ($random) {
            // shuffle($colorShadesBank);

            $colorShades = [];
            $groups = array_chunk($colorShadesBank, 4);

            for ($i = 0; $i < $number; $i++) {
                $colorShades[] = $groups[$i % count($groups)][rand(0, 3)];
            }

            return $colorShades;
        }

        while ($number > count($colorShadesBank)) {
            $colorShadesBank = array_merge($colorShadesBank, $colorShadesBank);
        }

        return array_slice($colorShadesBank, 0, $number);
    }

}

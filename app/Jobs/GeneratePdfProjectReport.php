<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\ProjectReportPdfExport;
use App\Models\Ticket;
use App\Models\TicketStage;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception as Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GeneratePdfProjectReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 420; // Timeout in seconds

    public $tries = 2; // Number of attempts

    public $report;

    public $isRegeneration;

    /**
     * Create a new job instance.
     */
    public function __construct(ProjectReportPdfExport $report, bool $isRegeneration = false)
    {
        //
        $this->report = $report;
        $this->isRegeneration = $isRegeneration;

        // Aumenta il limite di memoria a 512MB per questo job
        ini_set('memory_limit', '512M');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Blocco per implementazione in corso.
        // $this->report->is_failed = true;
        // $this->report->error_message = 'Questo tipo di report non è ancora implementato.';
        // $this->report->save();
        // return;

        try {
            $report = $this->report;
            $user = User::find($report->user_id);
            $company = Company::find($report->company_id);
            $project = Ticket::with(['ticketType.category'])->find($report->project_id);

            $queryTo = \Carbon\Carbon::parse($report->end_date)->endOfDay()->toDateTimeString();

            $closedStageId = TicketStage::where('system_key', 'closed')->first()?->id;

            // I ticket non si possono riaprire, quindi si controllano gli status update di chiusura e se c'è significa che è chiuso.
            // Se si fanno modifiche manuali nel db si deve eliminare l'update di chiusura che non vale più.

            // Query base comune per tutti i ticket del progetto
            $baseTicketsQuery = Ticket::with(['ticketType.category', 'user', 'stage'])
                ->where('company_id', $report->company_id)
                ->where('project_id', $project->id)
                ->where('created_at', '<=', $queryTo)
                ->where('description', 'NOT LIKE', 'Ticket importato%');

            // Tutti i ticket di questo progetto, chiusi nel periodo selezionato
            $selectedPeriodTickets = (clone $baseTicketsQuery)
                ->whereHas('statusUpdates', function ($query) use ($report, $queryTo) {
                    if (! empty($report->start_date)) {
                        $query->where('type', 'closing')
                            ->where('created_at', '>', $report->start_date)
                            ->where('created_at', '<=', $queryTo);
                    }
                })
                ->get();

            // Tutti i ticket di questo progetto, chiusi prima del periodo selezionato
            $beforePeriodTickets = (clone $baseTicketsQuery)
                ->whereHas('statusUpdates', function ($query) use ($report) {
                    if (! empty($report->start_date)) {
                        $query->where('type', 'closing')
                            ->where('created_at', '<=', $report->start_date);
                    }
                })
                ->get();

            // Tutti i ticket di questo progetto, non ancora chiusi o chiusi dopo il periodo selezionato
            $stillOpenTickets = (clone $baseTicketsQuery)
                ->whereDoesntHave('statusUpdates', function ($query) use ($report, $queryTo) {
                    if (! empty($report->end_date)) {
                        $query->where('type', 'closing')
                            ->where('created_at', '<=', $queryTo);
                    }
                })
                ->get();

            // === VALIDAZIONE DATI TICKET ===
            $errorsString = '';

            // Controllo validità dati per tutti i ticket
            $errorsString .= $this->validateTicketsData($selectedPeriodTickets);
            $errorsString .= $this->validateTicketsData($beforePeriodTickets);

            // Se ci sono errori di validazione, interrompi la generazione
            if (! empty($errorsString)) {
                throw new \Exception('Errori di validazione dati: '.$errorsString);
            }

            // Caricare le relazioni necessarie per tutti i ticket
            $allTickets = $selectedPeriodTickets->merge($beforePeriodTickets)->merge($stillOpenTickets);
            if (! $allTickets->isEmpty()) {
                $allTickets->load(['ticketType.category', 'user', 'messages.user', 'statusUpdates']);
            }

            $ticketSources = config('app.ticket_sources');
            $optional_parameters = json_decode($report->optional_parameters ?? '{}');

            $brand = $company->brands()->first();
            $google_url = $brand->withGUrl()->logo_url;

            // === DATI DEL PROGETTO ===
            $projectData = [
                'id' => $project->id,
                'name' => $project->description,
                'company' => $company->name,
                'category' => $project->ticketType?->category?->name ?? null,
                'type' => $project->ticketType?->name ?? null,
                'start_date' => $project->project_start ? \Carbon\Carbon::parse($project->project_start)->format('d/m/Y') : null,
                'end_date' => $project->project_end ? \Carbon\Carbon::parse($project->project_end)->format('d/m/Y') : null,
                'expected_duration' => $project->project_expected_duration,
                'created_at' => \Carbon\Carbon::parse($project->created_at)->format('d/m/Y H:i'),
                'status' => $project->stage_id == $closedStageId ? 'Chiuso' : 'Aperto',
                'total_tickets' => $allTickets->count(),
                'logo_url' => $google_url,
            ];

            // === CALCOLI STATISTICHE ===

            // Statistiche periodo selezionato
            $selectedPeriodStats = [
                'total_tickets' => $selectedPeriodTickets->count(),
                'billable_tickets' => $selectedPeriodTickets->where('is_billable', 1)->count(),
                'unbillable_tickets' => $selectedPeriodTickets->where('is_billable', 0)->count(),
                'total_time' => $selectedPeriodTickets->sum('actual_processing_time'),
                'billable_time' => $selectedPeriodTickets->where('is_billable', 1)->sum('actual_processing_time'),
                'unbillable_time' => $selectedPeriodTickets->where('is_billable', 0)->sum('actual_processing_time'),
                'remote_tickets' => $selectedPeriodTickets->where('work_mode', 'remote')->count(),
                'onsite_tickets' => $selectedPeriodTickets->where('work_mode', 'on_site')->count(),
                'remote_tickets_time' => $selectedPeriodTickets->where('work_mode', 'remote')->sum('actual_processing_time'),
                'onsite_tickets_time' => $selectedPeriodTickets->where('work_mode', 'on_site')->sum('actual_processing_time'),
                'incidents' => $selectedPeriodTickets->filter(function ($t) {
                    return $t->ticketType->category->is_problem == 1;
                })->count(),
                'requests' => $selectedPeriodTickets->filter(function ($t) {
                    return $t->ticketType->category->is_request == 1;
                })->count(),
            ];

            // Statistiche periodo precedente
            $beforePeriodStats = [
                'total_tickets' => $beforePeriodTickets->count(),
                'billable_tickets' => $beforePeriodTickets->where('is_billable', 1)->count(),
                'unbillable_tickets' => $beforePeriodTickets->where('is_billable', 0)->count(),
                'total_time' => $beforePeriodTickets->sum('actual_processing_time'),
                'billable_time' => $beforePeriodTickets->where('is_billable', 1)->sum('actual_processing_time'),
                'unbillable_time' => $beforePeriodTickets->where('is_billable', 0)->sum('actual_processing_time'),
                'remote_tickets' => $beforePeriodTickets->where('work_mode', 'remote')->count(),
                'onsite_tickets' => $beforePeriodTickets->where('work_mode', 'on_site')->count(),
                'remote_tickets_time' => $beforePeriodTickets->where('work_mode', 'remote')->sum('actual_processing_time'),
                'onsite_tickets_time' => $beforePeriodTickets->where('work_mode', 'on_site')->sum('actual_processing_time'),
            ];

            // Statistiche ticket ancora aperti
            $stillOpenStats = [
                'total_tickets' => $stillOpenTickets->count(),
                'current_total_time' => $stillOpenTickets->sum('actual_processing_time'),
                'created_in_period' => $stillOpenTickets->filter(function ($t) use ($report) {
                    return \Carbon\Carbon::parse($t->created_at)->gte(\Carbon\Carbon::parse($report->start_date));
                })->count(),
                'created_before_period' => $stillOpenTickets->filter(function ($t) use ($report) {
                    return \Carbon\Carbon::parse($t->created_at)->lt(\Carbon\Carbon::parse($report->start_date));
                })->count(),
            ];

            // === LISTA COMPLETA TICKET PER TABELLA ===
            // Tutti i ticket del progetto (incluso il progetto stesso come prima riga)
            $allProjectTickets = collect([$project])->merge($allTickets);

            $ticketsForTable = [];
            foreach ($allProjectTickets as $ticket) {

                $isProjectTicket = $ticket->id == $project->id;

                $ticketsForTable[] = [
                    'id' => $ticket->id,
                    'type' => $isProjectTicket ? 'PROGETTO' : ($ticket->ticketType->category->is_problem ? 'Incident' : 'Request'),
                    'category' => $ticket->ticketType->category->name,
                    'ticket_type' => $ticket->ticketType->name,
                    'description' => $ticket->description,
                    'opened_at' => \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i'),
                    'opened_by_initials' => $ticket->user->is_admin ? 'SUP' : (
                        (!empty($ticket->user->name) ? strtoupper(substr($ticket->user->name, 0, 1)) . '.' : '') .
                        (!empty($ticket->user->surname) ? strtoupper(substr($ticket->user->surname, 0, 1)) . '.' : '')
                    ),
                    'opened_by' => $ticket->user->is_admin ? 'Supporto' : $ticket->user->name.' '.$ticket->user->surname,
                    'current_status' => $ticket->stage->name,
                    'is_billable' => $isProjectTicket ? null : ($ticket->is_billable ? 'Sì' : 'No'),
                    'work_mode' => $isProjectTicket ? null : ($ticket->work_mode == 'remote' ? 'Remoto' : 'In sede'),
                    'actual_processing_time' => $isProjectTicket ? null : ($ticket->actual_processing_time ?? 0),
                    'closed_at' => null, // Sarà compilato nel dettaglio se necessario
                    'is_project' => $isProjectTicket,
                    'period' => $isProjectTicket ? 'progetto' : $this->getTicketPeriod($ticket, $selectedPeriodTickets, $beforePeriodTickets, $stillOpenTickets),
                    'master_id' => $ticket->master_id,
                    'is_master' => $ticket->ticketType ? $ticket->ticketType->is_master : false,
                ];
            }

            // === DETTAGLIO TICKET ===
            $ticketsDetail = [];

            foreach ($allProjectTickets as $ticket) {
                $isProjectTicket = $ticket->id == $project->id;

                if ($isProjectTicket) {
                    // Dettaglio del progetto
                    $ticketsDetail[] = [
                        'ticket_data' => $ticket,
                        'is_project' => true,
                        'type' => 'PROGETTO',
                        'webform_data' => null,
                        'messages' => [],
                        'status_updates' => [],
                        'closing_info' => null,
                    ];
                } else {
                    // Dettaglio ticket normale
                    $webform_data = null;
                    $messages = [];

                    // Recupera primo messaggio (webform data)
                    $firstMessage = $ticket->messages()->orderBy('created_at')->first();
                    if ($firstMessage) {
                        $webform_data = json_decode($firstMessage->message, true);

                        // Processa office
                        if (isset($webform_data['office'])) {
                            $office = $company->offices()->where('id', $webform_data['office'])->first();
                            $webform_data['office'] = $office ? $office->name : null;
                        }

                        // Processa referer
                        if (isset($webform_data['referer'])) {
                            $referer = \App\Models\User::find($webform_data['referer']);
                            $webform_data['referer'] = $referer ? $referer->name.' '.$referer->surname : null;
                        }

                        if (isset($webform_data['referer_it'])) {
                            $referer_it = \App\Models\User::find($webform_data['referer_it']);
                            $webform_data['referer_it'] = $referer_it ? $referer_it->name.' '.$referer_it->surname : null;
                        }
                    }

                    // Recupera tutti i messaggi per il dettaglio, escluso il primo che è quello del webform e il secondo che è quello della descrizione
                    $allMessages = $ticket->messages()->with('user')->orderBy('created_at')->get();

                    if($allMessages->count() > 1){
                        $description= $allMessages[1]->description;
                    }

                    foreach ($allMessages->slice(2) as $msg) {
                        $messages[] = [
                            'id' => $msg->id,
                            'user' => $msg->user->is_admin ? 'Supporto' : $msg->user->name.' '.$msg->user->surname,
                            'message' => $msg->message,
                            'created_at' => \Carbon\Carbon::parse($msg->created_at)->format('d/m/Y H:i'),
                        ];
                    }

                    // Recupera status updates
                    $statusUpdates = $ticket->statusUpdates()->with('newStage')->orderBy('created_at')->get();
                    $statusHistory = [];
                    foreach ($statusUpdates as $update) {
                        $statusHistory[] = [
                            'type' => $update->type,
                            'stage_name' => $update->newStage?->name ?? 'N/A',
                            'content' => $update->content,
                            'created_at' => \Carbon\Carbon::parse($update->created_at)->format('d/m/Y H:i'),
                        ];
                    }

                    // Info chiusura
                    $closingUpdate = $ticket->statusUpdates()->where('type', 'closing')->orderBy('created_at', 'desc')->first();
                    $closingInfo = null;
                    if ($closingUpdate) {
                        $closingInfo = [
                            'message' => $closingUpdate->content,
                            'closed_at' => \Carbon\Carbon::parse($closingUpdate->created_at)->format('d/m/Y H:i'),
                        ];
                    }

                    $ticketsDetail[] = [
                        'ticket_data' => $ticket,
                        'is_project' => false,
                        'type' => $ticket->ticketType->category->is_problem ? 'Incident' : 'Request',
                        'webform_data' => $webform_data,
                        'messages' => $messages,
                        'status_updates' => $statusHistory,
                        'closing_info' => $closingInfo,
                        'source' => $ticketSources[$ticket->source] ?? 'N/A',
                        'opened_by' => $ticket->user->is_admin == 1 ? 'Supporto' : $ticket->user->name.' '.$ticket->user->surname,
                        'is_master' => $ticket->ticketType ? $ticket->ticketType->is_master : false,
                        'slave_ids' => Ticket::where('master_id', $ticket->id)->pluck('id')->toArray(),
                        'should_show_more' => $ticket->messages()->count() > 5,
                        'ticket_frontend_url' => env('FRONTEND_URL').'/support/user/ticket/'.$ticket->id,
                    ];
                }
            }

            // === GRAFICI PER IL REPORT ===
            $charts_base_url = 'https://quickchart.io/chart?c=';
            $charts = [];

            // Calcolo dei tempi in ore per il grafico principale
            $timeBeforePeriodHours = round($beforePeriodStats['total_time'] / 60, 1);
            $timeSelectedPeriodHours = round($selectedPeriodStats['total_time'] / 60, 1);
            $estimatedTimeHours = round(($project->project_expected_duration ?? 0) / 60, 1);

            // === GRAFICO ANDAMENTO CHIUSURE ===
            // Determina l'arco temporale e la granularità
            $projectStartDate = $project->project_start ? \Carbon\Carbon::parse($project->project_start) : \Carbon\Carbon::parse($project->created_at);
            $projectEndDate = \Carbon\Carbon::parse($queryTo);
            $daysDiff = $projectStartDate->diffInDays($projectEndDate);
            
            $trendLabels = [];
            $trendData = [];
            
            if ($daysDiff <= 14) {
                // Suddivisione per giorno
                $currentDate = $projectStartDate->copy();
                while ($currentDate->lte($projectEndDate)) {
                    $trendLabels[] = $currentDate->format('d/m');
                    
                    // Conta i ticket chiusi in questo giorno
                    $closedInDay = $allTickets->filter(function($ticket) use ($currentDate) {
                        $closingUpdate = $ticket->statusUpdates()->where('type', 'closing')->first();
                        if (!$closingUpdate) return false;
                        
                        $closingDate = \Carbon\Carbon::parse($closingUpdate->created_at);
                        return $closingDate->isSameDay($currentDate);
                    })->count();
                    
                    $trendData[] = $closedInDay;
                    $currentDate->addDay();
                }
                $periodLabel = 'Giornaliero';
            } elseif ($daysDiff <= 90) {
                // Suddivisione per settimana
                $currentDate = $projectStartDate->copy()->startOfWeek();
                while ($currentDate->lte($projectEndDate)) {
                    $weekEnd = $currentDate->copy()->endOfWeek();
                    if ($weekEnd->gt($projectEndDate)) {
                        $weekEnd = $projectEndDate->copy();
                    }
                    
                    $trendLabels[] = $currentDate->format('d/m') . '-' . $weekEnd->format('d/m');
                    
                    // Conta i ticket chiusi in questa settimana
                    $closedInWeek = $allTickets->filter(function($ticket) use ($currentDate, $weekEnd) {
                        $closingUpdate = $ticket->statusUpdates()->where('type', 'closing')->first();
                        if (!$closingUpdate) return false;
                        
                        $closingDate = \Carbon\Carbon::parse($closingUpdate->created_at);
                        return $closingDate->gte($currentDate) && $closingDate->lte($weekEnd);
                    })->count();
                    
                    $trendData[] = $closedInWeek;
                    $currentDate->addWeek();
                }
                $periodLabel = 'Settimanale';
            } else {
                // Suddivisione per mese
                $currentDate = $projectStartDate->copy()->startOfMonth();
                while ($currentDate->lte($projectEndDate)) {
                    $monthEnd = $currentDate->copy()->endOfMonth();
                    if ($monthEnd->gt($projectEndDate)) {
                        $monthEnd = $projectEndDate->copy();
                    }
                    
                    $trendLabels[] = $currentDate->format('M Y');
                    
                    // Conta i ticket chiusi in questo mese
                    $closedInMonth = $allTickets->filter(function($ticket) use ($currentDate, $monthEnd) {
                        $closingUpdate = $ticket->statusUpdates()->where('type', 'closing')->first();
                        if (!$closingUpdate) return false;
                        
                        $closingDate = \Carbon\Carbon::parse($closingUpdate->created_at);
                        return $closingDate->gte($currentDate) && $closingDate->lte($monthEnd);
                    })->count();
                    
                    $trendData[] = $closedInMonth;
                    $currentDate->addMonth();
                }
                $periodLabel = 'Mensile';
            }
            
            // Genera grafico andamento solo se ci sono dati
            if (!empty($trendData) && array_sum($trendData) > 0) {
                $trendChartData = [
                    'type' => 'line',
                    'data' => [
                        'labels' => $trendLabels,
                        'datasets' => [[
                            'label' => 'Ticket Chiusi',
                            'data' => $trendData,
                            'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                            'borderColor' => '#4BC0C0',
                            'borderWidth' => 2,
                            'fill' => true,
                            'tension' => 0.1,
                            'pointRadius' => 4,
                            'pointBackgroundColor' => '#4BC0C0'
                        ]]
                    ],
                    'options' => [
                        'title' => ['display' => true, 'text' => 'Andamento Chiusure - ' . $periodLabel, 'fontSize' => 14],
                        'legend' => ['display' => true, 'position' => 'bottom'],
                        'plugins' => [
                            'datalabels' => [
                                'display' => true,
                                'color' => '#333',
                                'font' => ['weight' => 'bold', 'size' => 10],
                                'formatter' => "(value, context) => { return value > 0 ? value : ''; }",
                                'anchor' => 'end',
                                'align' => 'top'
                            ]
                        ],
                        'scales' => [
                            'xAxes' => [[
                                'scaleLabel' => ['display' => true, 'labelString' => 'Periodo']
                            ]],
                            'yAxes' => [[
                                'ticks' => ['beginAtZero' => true, 'stepSize' => 1],
                                'scaleLabel' => ['display' => true, 'labelString' => 'N° Ticket']
                            ]]
                        ],
                        'responsive' => true,
                        'maintainAspectRatio' => false
                    ]
                ];
                $charts['trend'] = $charts_base_url . urlencode(json_encode($trendChartData));
            }

            // 1. GRAFICO TEMPO PROGETTO (Orizzontale)
            // Tempo previsto vs tempo effettivamente impiegato
            if ($estimatedTimeHours > 0 || $timeBeforePeriodHours > 0 || $timeSelectedPeriodHours > 0) {
                $projectTimeData = [
                    'type' => 'horizontalBar',
                    'data' => [
                        'labels' => ['Tempo Previsto', 'Tempo Effettivo'],
                        'datasets' => [
                            [
                                'label' => 'Periodo Precedente',
                                'data' => [0, $timeBeforePeriodHours],
                                'backgroundColor' => '#ff9800',
                                'borderColor' => '#ff9800',
                                'maxBarThickness' => 40,
                            ],
                            [
                                'label' => 'Periodo Selezionato', 
                                'data' => [0, $timeSelectedPeriodHours],
                                'backgroundColor' => '#4CAF50',
                                'borderColor' => '#4CAF50',
                                'maxBarThickness' => 40,
                            ],
                            [
                                'label' => 'Tempo Previsto Totale',
                                'data' => [$estimatedTimeHours, 0],
                                'backgroundColor' => '#2196F3',
                                'borderColor' => '#2196F3',
                                'maxBarThickness' => 40,
                            ]
                        ]
                    ],
                    'options' => [
                        'title' => ['display' => true, 'text' => 'Confronto Tempo Previsto vs Effettivo (Ore)', 'fontSize' => 14],
                        'legend' => ['display' => true, 'position' => 'bottom'],
                        'plugins' => [
                            'datalabels' => [
                                'display' => true,
                                'color' => '#ffffff',
                                'font' => ['weight' => 'bold', 'size' => 12],
                                'formatter' => "(value, context) => { return value > 0 ? value + 'h' : ''; }",
                                'anchor' => 'center',
                                'align' => 'center'
                            ]
                        ],
                        'scales' => [
                            'xAxes' => [[
                                'stacked' => true,
                                'ticks' => ['beginAtZero' => true],
                                'scaleLabel' => ['display' => true, 'labelString' => 'Ore']
                            ]],
                            'yAxes' => [[
                                'stacked' => true
                            ]]
                        ],
                        'responsive' => true,
                        'maintainAspectRatio' => false
                    ]
                ];
                $charts['project_time'] = $charts_base_url.urlencode(json_encode($projectTimeData));
            }

            // 2. GRAFICO MODALITÀ DI GESTIONE (Periodo Selezionato)
            // Ticket gestiti da remoto vs in sede
            if ($selectedPeriodStats['remote_tickets'] + $selectedPeriodStats['onsite_tickets'] > 0) {
                $workModeData = [
                    'type' => 'doughnut',
                    'data' => [
                        'labels' => ['Gestione Remota', 'Gestione In Sede'],
                        'datasets' => [[
                            'data' => [
                                $selectedPeriodStats['remote_tickets'],
                                $selectedPeriodStats['onsite_tickets']
                            ],
                            'backgroundColor' => ['#2196F3', '#CC3825'],
                            'borderColor' => ['#2196F3', '#CC3825'],
                            'borderWidth' => 2
                        ]]
                    ],
                    'options' => [
                        'title' => ['display' => true, 'text' => 'Modalità di Gestione - Periodo Selezionato', 'fontSize' => 14],
                        'legend' => ['display' => true, 'position' => 'bottom'],
                        'plugins' => [
                            'datalabels' => [
                                'display' => true,
                                'color' => '#ffffff',
                                'font' => ['weight' => 'bold', 'size' => 12],
                                'formatter' => "(value, context) => { return value > 0 ? value : ''; }",
                                'anchor' => 'center',
                                'align' => 'center'
                            ]
                        ],
                        'responsive' => true,
                        'maintainAspectRatio' => false
                    ]
                ];
                $charts['work_mode'] = $charts_base_url.urlencode(json_encode($workModeData));
            }

            // === STRUTTURA DATI FINALE ===
            $reportData = [
                'report_info' => [
                    'start_date' => \Carbon\Carbon::parse($report->start_date)->format('d/m/Y'),
                    'end_date' => \Carbon\Carbon::parse($report->end_date)->format('d/m/Y'),
                    'generated_at' => now()->format('d/m/Y H:i'),
                    'user' => $user ? ($user->name.' '.$user->surname) : 'Sistema',
                ],
                'project_data' => $projectData,
                'period_stats' => $selectedPeriodStats,
                'before_period_stats' => $beforePeriodStats,
                'total_closed_time' => $beforePeriodStats['total_time'] + $selectedPeriodStats['total_time'],
                'still_open_stats' => $stillOpenStats,
                'charts' => $charts,
                'tickets_table' => $ticketsForTable,
                'tickets_detail' => $ticketsDetail,
            ];

            Pdf::setOptions([
                'dpi' => 150,
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);
            $pdf = Pdf::loadView('pdf.exportprojectpdf', $reportData);

            if (! $pdf) {
                throw new Exception('PDF generation failed');
            }

            $disk = \App\Http\Controllers\FileUploadController::getStorageDisk();
            Storage::disk($disk)->put($report->file_path, $pdf->output());

            // Se ci mette troppo tempo potremmo rispondere ok alla creazione del report e generarlo tramite un job, che quando ha fatto aggiorna il report

            $updateData = [
                'is_generated' => true,
                'error_message' => null,
                'is_failed' => false,
            ];

            // Imposta last_regenerated_at solo se è una rigenerazione
            if ($this->isRegeneration) {
                $updateData['last_regenerated_at'] = now();
            }

            $report->update($updateData);

            Log::info('Project report generated successfully', ['report_id' => $this->report->id, 'file_path' => $report->file_path]);

        } catch (\Exception $e) {
            // Gestione errori
            $this->report->update([
                'is_failed' => true,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Project report generation failed', [
                'report_id' => $this->report->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->report->update([
            'is_failed' => true,
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('Project report job failed completely', [
            'report_id' => $this->report->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Determina in quale periodo è stato chiuso il ticket
     */
    private function getTicketPeriod($ticket, $selectedPeriodTickets, $beforePeriodTickets, $stillOpenTickets): string
    {
        if ($selectedPeriodTickets->contains('id', $ticket->id)) {
            return 'periodo_selezionato';
        }

        if ($beforePeriodTickets->contains('id', $ticket->id)) {
            return 'periodo_precedente';
        }

        if ($stillOpenTickets->contains('id', $ticket->id)) {
            return 'ancora_aperto';
        }

        return 'sconosciuto';
    }

    /**
     * Valida i dati dei ticket e restituisce una stringa con gli errori trovati
     */
    private function validateTicketsData($tickets): string
    {
        $errorsString = '';

        foreach ($tickets as $ticket) {

            // Per ora ancora non ci sono i ticket di tipo scheduling in questo report. però il controllo lo metto adesso, altrimenti mi scordo
            if($ticket->ticketType->is_scheduling && !$ticket->is_scheduling_time_approved) {
                $errorsString .= '- #'.$ticket->id.' è di tipo scheduling ma non ha il tempo approvato. ';
            }

            if (! $ticket->actual_processing_time && ! ($ticket->ticketType && $ticket->ticketType->is_master)) {
                $errorsString .= '- #'.$ticket->id.' non ha il tempo di lavoro. ';
            }

            if ($ticket->is_billable === null) {
                $errorsString .= '- #'.$ticket->id.' non ha il flag di fatturabilità. ';
            }

            if ($ticket->is_billing_validated != true) {
                $errorsString .= '- #'.$ticket->id.' non ha la validazione fatturabilità. ';
            }

            if ($ticket->work_mode === null) {
                $errorsString .= '- #'.$ticket->id.' non ha la modalità di lavoro. ';
            }

            if ($ticket->work_mode == 'on_site' && ($ticket->admin_user_id == null)) {
                $errorsString .= '- #'.$ticket->id.' ticket on_site senza gestore. ';
            }
        }

        return $errorsString;
    }

    /**
     * Genera sfumature di colore per i grafici
     */
    private function getColorShades($number = 1, $random = false, $fromDarker = true, $fromLighter = false, $shadeColor = 'red'): array
    {
        if ($shadeColor == 'red') {
            $colorShadesBank = [
                '#5c1310', '#741815', '#8b1d19', '#a2221d', '#b92621', '#d02b25', '#e73029',
                '#e9453e', '#ec5954', '#ee6e69', '#f1837f', '#f39894', '#f5aca9', '#f8c1bf', '#fad6d4',
            ];
        } elseif ($shadeColor == 'green') {
            $colorShadesBank = [
                '#0d3b1e', '#145c2a', '#1b7d36', '#22a042', '#29c24e', '#30e45a', '#45e96e',
                '#59ee82', '#6ef396', '#83f8aa', '#98fdbe', '#acf3c1', '#c1fad6', '#d6fae6', '#e6faef',
            ];
        } else {
            $colorShadesBank = [
                '#00090e', '#01121c', '#011c29', '#022537', '#032e45', '#033753', '#044061',
                '#044a6e', '#05537c', '#055c8a', '#1e6c96', '#377da1', '#508dad', '#699db9', '#82aec5', '#9bbed0',
            ];
        }

        if ($random) {
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

    /**
     * Genera colori specifici per i grafici degli utenti
     */
    private function getColorShadesForUsers($number = 1, $random = false): array
    {
        $colorShadesBank = [
            '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#10b981', '#14b8a6',
            '#06b6d4', '#0ea5e9', '#2563eb', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#db2777', '#f43f5e',
        ];

        if ($random) {
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

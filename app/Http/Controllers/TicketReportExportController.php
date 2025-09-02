<?php

namespace App\Http\Controllers;

use App\Models\TicketReportExport;
use Illuminate\Http\Request;
use App\Jobs\GenerateGenericReport;
use App\Jobs\GeneratePdfReport;
use App\Jobs\GenerateReport;
use App\Jobs\GenerateUserReport;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketStatusUpdate;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;

class TicketReportExportController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
    }

    /**
     * Lista per company singola
     */

    public function company(Company $company) {
        $reports = TicketReportExport::where('company_id', $company->id)->where(
            'is_generated',
            true
        )
            ->orderBy('created_at', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function generic() {
        $reports = TicketReportExport::where('optional_parameters', '!=', "[]")
            ->where('is_generated', true)
            ->orderBy('created_at', 'DESC')
            ->get();

        $reports->each(function ($report) {
            $optionalParameters = json_decode($report->optional_parameters);
            if (isset($optionalParameters->specific_types)) {
                $specificTypes = $optionalParameters->specific_types;
                $ticketTypes = TicketType::whereIn('id', $specificTypes)->get();
                $report->specific_types = $ticketTypes;
            }

            if ($report->company_id != 1) {
                $report->company = Company::find($report->company_id);
            } else {
                $report->company = [
                    'name' => 'Non specificata'
                ];
            }
        });

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function user(Request $request) {
        $user = $request->user();

        $reports = TicketReportExport::where('company_id', $user->selectedCompany()->id)
            ->where('is_user_generated', true)
            ->orderBy('created_at', 'DESC')
            ->get();

        return response([
            'reports' => $reports,
        ], 200);
    }

    public function download(TicketReportExport $ticketReportExport, Request $request) {

        $user = $request->user();
        if ($user["is_admin"] != 1 && $user["is_company_admin"] != 1) {
            return response([
                'message' => 'The user must be at least company admin.',
            ], 401);
        }
        if($user["is_company_admin"] == 1 && $user->selectedCompany()->id != $ticketReportExport->company_id) {
            return response([
                'message' => 'You can\'t download this report.',
            ], 401);
        }

        $url = $this->generatedSignedUrlForFile($ticketReportExport->file_path);

        return response([
            'url' => $url,
            'filename' => $ticketReportExport->file_name
        ], 200);
    }

    private function generatedSignedUrlForFile($path) {

        /**
         * @disregard P1009 Undefined type
         */

        $url = Storage::disk('gcs')->temporaryUrl(
            $path,
            now()->addMinutes(65)
        );

        return $url;
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TicketReportExport $ticketReportExport) {
        //
    }

    /**
     * Export the specified resource from storage.
     */

    public function export(Request $request) {

        $name = time() . '_' . $request->company_id . '_tickets.xlsx';

        $company = Company::find($request->company_id);
        // $file =  Excel::store(new TicketsExport($company, $request->start_date, $request->end_date), 'exports/' . $request->company_id . '/' . $name, 'gcs');


        $report = TicketReportExport::create([
            'company_id' => $company->id,
            'file_name' => $name,
            'file_path' => 'exports/' . $request->company_id . '/' . $name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'optional_parameters' => json_encode($request->optional_parameters)
        ]);

        dispatch(new GenerateReport($report));


        return response()->json(['file' => $name]);
    }


    // copiato uguale anche in GeneratePdfReport
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

    // copiato uguale anche in GeneratePdfReport
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

    public function exportpdf(Ticket $ticket) {

        $name = time() . '_' . $ticket->id . '_tickets.xlsx';
        //? Webform

        $webform_data = json_decode($ticket->messages()->first()->message);

        $office = $ticket->company->offices()->where('id', $webform_data->office)->first();
        $webform_data->office = $office ? $office->name : null;

        if (isset($webform_data->referer)) {
            $referer = User::find($webform_data->referer);
            $webform_data->referer = $referer ? $referer->name . " " . $referer->surname : null;
        }

        if (isset($webform_data->referer_it)) {
            $referer_it = User::find($webform_data->referer_it);
            $webform_data->referer_it = $referer_it ? $referer_it->name . " " . $referer_it->surname : null;
        }

        //? Avanzamento

        $avanzamento = [
            "attesa" => 0,
            "assegnato" => 0,
            "in_corso" => 0,
        ];

        foreach ($ticket->statusUpdates as $update) {
            if ($update->type == 'status') {

                if (strpos($update->content, 'In attesa') !== false) {
                    $avanzamento["attesa"]++;
                }
                if (
                    (strpos($update->content, 'Assegnato') !== false) || (strpos($update->content, 'assegnato') !== false)
                ) {
                    $avanzamento["assegnato"]++;
                }
                if (strpos($update->content, 'In corso') !== false) {
                    $avanzamento["in_corso"]++;
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

        $data = [
            'title' => $name,
            'ticket' => $ticket,
            'webform_data' => $webform_data,
            'status_updates' => $avanzamento,
            'closing_messages' => $closingMessage,

        ];

        Pdf::setOptions([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true // ✅ Abilita il caricamento di immagini da URL esterni
        ]);

        $pdf = Pdf::loadView('pdf.export', $data);

        // return $pdf->stream();
        return $pdf->download($name);
    }

    public function exportBatch(Request $request) {
        $user = $request->user();
        if ($user["is_admin"] != 1 && $user["is_company_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        if ($user["is_admin"] == 1) {
            $cacheKey = 'admin_batch_report_' . $request->company_id . '_' . $request->from . '_' . $request->to . '_' . $request->type_filter;
        } else {
            $cacheKey = 'user_batch_report_' . $request->company_id . '_' . $request->from . '_' . $request->to . '_' . $request->type_filter;
        }

        $company = Company::find($request->company_id);
        $tickets_data = Cache::get($cacheKey);

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

        $dates_are_more_than_one_month_apart = \Carbon\Carbon::createFromFormat('Y-m-d', $request->from)->diffInMonths(\Carbon\Carbon::createFromFormat('Y-m-d', $request->to)) > 0;
        $tickets_by_month = [];

        $closed_tickets_count = 0;
        $other_tickets_count = 0;

        $wrong_type = [
            "incident" => 0,
            "request" => 0
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

            // Se chiuso o meno

            if ($ticket['data']['status'] == 5) {
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
                ->where('created_at', '<', \Carbon\Carbon::createFromFormat('Y-m-d', $request->to))
                ->orderBy('created_at', 'DESC')
                ->first();

            $current_status = "Aperto";

            if ($latest_status_update) {
                if (strpos($latest_status_update->content, 'In attesa') !== false) {
                    $current_status = "In Attesa";
                }
                if (strpos($latest_status_update->content, 'Assegnato') !== false) {
                    $current_status = "Assegnato";
                }
                if (strpos($latest_status_update->content, 'In corso') !== false) {
                    $current_status = "In corso";
                }
                if ($latest_status_update->type == 'closing') {
                    $current_status = "Chiuso";
                }
            }

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



            // Ticket ridotto

            $reduced_ticket = [
                "id" => $ticket['data']['id'],
                "incident_request" => $ticket['data']['ticketType']['category']['is_problem'] == 1 ? "Incident" : "Request",
                "category" => $ticket['data']['ticketType']['category']['name'],
                "type" => $ticket['data']['ticketType']['name'],
                "opened_by" => $ticket['data']['user']['is_admin'] == 1 ? "Supporto" : $ticket['data']['user']['name'] . " " . $ticket['data']['user']['surname'],
                "opened_at" => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $ticket['data']['created_at'])->format('d/m/Y H:i'),
                "webform_data" => $ticket['webform_data'],
                "status_updates" => $ticket['status_updates'],
                "description" => $ticket['data']['description'],
                "closing_message" => $ticket['closing_message'],
                "closed_at" => $ticket['data']['status'] == 5 ? $closed_at : "",
                'should_show_more' => false,
                'ticket_frontend_url' => env('FRONTEND_URL') . '/support/user/ticket/' . $ticket['data']['id'],
                'current_status' => $current_status,
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


        // Ordina i ticket per categoria e per data di creazione
        usort($reduced_tickets, function ($a, $b) {
            return strcmp($a['category'], $b['category']) ?: strcmp($a['opened_at'], $b['opened_at']);
        });


        for ($date = \Carbon\Carbon::createFromFormat('Y-m-d', $request->from); $date <= \Carbon\Carbon::createFromFormat('Y-m-d', $request->to); $date->addDay()) {
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
                    "backgroundColor" => [$base_request_color, $base_incident_color]
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
                    "backgroundColor" => $this->getColorShades(count(array_keys($different_categories_with_count['incident'])), true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Incident per Categoria"],
                "legend" => ["display" => false],

            ]
        ];
        $maxValue = max([0, ...array_values($ticket_by_category_incident_bar_data['data']['datasets'][0]['data'])]);
        if ($maxValue < 5) {
            $ticket_by_category_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $ticket_by_category_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
            $ticket_by_category_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
        } else {
            $ticket_by_category_incident_bar_data['options']["plugins"]["datalabels"] = [
                "display" => true,
                "color" => "white",
                "align" => "center",
                "anchor" => "center",
                "font" => [
                    "weight" => "bold"
                ]
            ];
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
                    "backgroundColor" => $this->getColorShades(count(array_keys($different_categories_with_count['request'])), true, true, false, "blue")
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Request più frequenti per Categoria"],
                "legend" => ["display" => false],

            ]
        ];

        $maxValue = max(array_values($ticket_by_category_request_bar_data['data']['datasets'][0]['data']));
        if ($maxValue < 5) {
            $ticket_by_category_request_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $ticket_by_category_request_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
            $ticket_by_category_request_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
        } else {
            $ticket_by_category_request_bar_data['options']["plugins"]["datalabels"] = [
                "display" => true,
                "color" => "white",
                "align" => "center",
                "anchor" => "center",
                "font" => [
                    "weight" => "bold"
                ]
            ];
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
                    "backgroundColor" => $this->getColorShades(count(array_keys($different_type_with_count['incident'])), true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Top 5 Incident per Tipo"],
                "legend" => ["display" => false],

            ]
        ];

        $maxValue = max([0, ...array_values($ticket_by_type_incident_bar_data['data']['datasets'][0]['data'])]);
        if ($maxValue < 5) {
            $ticket_by_type_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $ticket_by_type_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
            $ticket_by_type_incident_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
        } else {
            $ticket_by_type_incident_bar_data['options']["plugins"]["datalabels"] = [
                "display" => true,
                "color" => "white",
                "align" => "center",
                "anchor" => "center",
                "font" => [
                    "weight" => "bold"
                ]
            ];
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
                    "backgroundColor" => $this->getColorShades(count(array_keys($different_type_with_count['request'])), true, true, false, "blue")
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Top 5 Request per Tipo"],
                "legend" => ["display" => false]
            ]
        ];

        $maxValue = max(array_values($ticket_by_type_request_bar_data['data']['datasets'][0]['data']));
        if ($maxValue < 5) {
            $ticket_by_type_request_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $ticket_by_type_request_bar_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
            $ticket_by_type_request_bar_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
        } else {
            $ticket_by_type_request_bar_data['options']["plugins"]["datalabels"] = [
                "display" => true,
                "color" => "white",
                "align" => "center",
                "anchor" => "center",
                "font" => [
                    "weight" => "bold"
                ]
            ];
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

            ]
        ];

        $maxValue = max(array_values($ticket_by_source_data['data']['datasets'][0]['data']));
        if ($maxValue < 5) {
            $ticket_by_source_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
            $ticket_by_source_data['options']['scales']['xAxes'][0]['ticks']['stepSize'] = 1;
            $ticket_by_source_data['options']['scales']['xAxes'][0]['ticks']['max'] = 10;
        } else {
            $ticket_by_source_data['options']["plugins"]["datalabels"] = [
                "display" => true,
                "color" => "white",
                "align" => "center",
                "anchor" => "center",
                "font" => [
                    "weight" => "bold"
                ]
            ];
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
                    "backgroundColor" => $this->getColorShades(7, false, false, true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Ticket per Giorno della Settimana"],
                "legend" => ["display" => false],

            ]
        ];

        $maxValue = max(array_values($ticket_by_weekday_data['data']['datasets'][0]['data']));
        if ($maxValue < 5) {
            $ticket_by_weekday_data['options']['scales']['yAxes'][0]['ticks']['beginAtZero'] = true;
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

        $maxValue = max(array_values($ticket_by_priority_bar_data['data']['datasets'][0]['data']));
        if ($maxValue < 5) {
            $ticket_by_priority_bar_data['options']['scales']['xAxes'][0]['ticks']['beginAtZero'] = true;
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
                    "backgroundColor" => $this->getColorShadesForUsers(count(array_keys($tickets_by_user)), true)
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Ticket per Utente"],
                "legend" => ["display" => false],

            ]
        ];

        $maxValue = max(array_values($tickets_by_user_data['data']['datasets'][0]['data']));
        if ($maxValue < 5) {
            $tickets_by_user_data['options']['scales']['yAxes'][0]['ticks']['beginAtZero'] = true;
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
                    "backgroundColor" => $this->getColorShades(4, true, true, false)
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
                    "backgroundColor" => [$base_request_color, $base_incident_color]
                ]]
            ],
            "options" => [
                "title" => ["display" => true, "text" => "Form non corretto"],
                "legend" => ["display" => false],

            ]
        ];

        $maxValue = max(array_values($wrong_type_data['data']['datasets'][0]['data']));
        if ($maxValue < 5) {
            $wrong_type_data['options']['scales']['yAxes'][0]['ticks']['beginAtZero'] = true;
            $wrong_type_data['options']['scales']['yAxes'][0]['ticks']['stepSize'] = 1;
            $wrong_type_data['options']['scales']['yAxes'][0]['ticks']['max'] = 10;
        } else {
            $wrong_type_data['options']["plugins"]["datalabels"] = [
                "display" => true,
                "color" => "white",
                "align" => "center",
                "anchor" => "center",
                "font" => [
                    "weight" => "bold"
                ]
            ];
        }

        $wrong_type_url = $charts_base_url . urlencode(json_encode($wrong_type_data));


        // Logo da usare

        $brand = $company->brands()->first();
        $google_url = $brand->withGUrl()->logo_url;

        $data = [
            'tickets' => $reduced_tickets,
            'title' => "Esportazione tickets",
            'date_from' => \Carbon\Carbon::createFromFormat('Y-m-d', $request->from),
            'date_to' => \Carbon\Carbon::createFromFormat('Y-m-d', $request->to),
            'company' => $company,
            'request_number' => $total_requests,
            'incident_number' => $total_incidents,
            'closed_tickets_count' => $closed_tickets_count,
            'other_tickets_count' => $other_tickets_count,
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
            'wrong_type_url' => $wrong_type_url

        ];



        Pdf::setOptions([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true
        ]);
        $pdf = Pdf::loadView('pdf.exportbatch', $data);
        return $pdf->download("Esportazione tickets");
    }

    public function genericExport(Request $request) {
        $name = time() . '_generic_export.xlsx';
        $file_path = $request->company_id ? 'exports/' . $request->company_id . '/exports/ifortech/' . $name : '';


        $report = TicketReportExport::create([
            'company_id' => $request->company_id ? $request->company_id : 0,
            'file_name' => $name,
            'file_path' => $file_path,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'optional_parameters' => json_encode($request->optional_parameters)
        ]);

        dispatch(new GenerateGenericReport($report));

        return response()->json(['file' => $name]);
    }

    public function userExport(Request $request) {

        $user = $request->user();

        $name_file = str_replace("-", "_", $request->start_date) . "_" . str_replace("-", "_", $request->end_date) . str_replace("-", "_", $request->type);
        $name = time() . '_'  . $name_file . '.xlsx';

        $report = TicketReportExport::create([
            'company_id' => $user->selectedCompany()->id,
            'file_name' => $name,
            'file_path' => 'exports/' . $user->selectedCompany()->id . '/' . $name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'optional_parameters' => json_encode(["type" => $request->type]),
            'is_user_generated' => true
        ]);

        dispatch(new GenerateUserReport($report));

        return response()->json(['file' => $name]);
    }
}

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Progetto - {{ $project_data['name'] }}</title>
</head>

@include('components.project-style')

<body>
    <!-- Header con logo e titolo -->
    <div class="center-align">
        <div>
            @if (!empty($project_data['company_logo']))
                @php
                    $imgData = @file_get_contents($project_data['company_logo']);
                @endphp
                @if ($imgData !== false)
                    <img src="data:image/png;base64,{{ base64_encode($imgData) }}" alt="logo"
                        class="logo-image">
                @endif
            @endif
        </div>

        <h1 class="main-header">
            Report Progetto
        </h1>

        <div class="card">
            <h2 class="sub-header">
                {{ $project_data['company'] }}
            </h2>

            <div class="date-info-table">
                <table>
                    <tr>
                        <td class="date-label">
                            <span><b>Periodo Report:</b></span>
                        </td>
                        <td class="date-value">{{ $report_info['start_date'] }} - {{ $report_info['end_date'] }}</td>
                        <td class="date-label">
                            <span><b>Generato il:</b></span>
                        </td>
                        <td class="date-value">{{ $report_info['generated_at'] }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="spacer"></div>

    <!-- SEZIONE 1: INFORMAZIONI DEL PROGETTO -->
    <div class="card">
        <h3 class="section-header">
            Informazioni Progetto
        </h3>
        
                <table class="project-info-table">
            <tr>
                <td class="font-semibold table-cell">ID Progetto:</td>
                <td class="table-cell">{{ $project_data['id'] }}</td>
                <td class="font-semibold table-cell">Stato:</td>
                <td class="table-cell">
                    <span>
                        {{ $project_data['status'] }}
                    </span>
                </td>
            </tr>
            <tr>
                <td class="font-semibold">Nome:</td>
                <td colspan="3">{{ $project_data['name'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold">Categoria:</td>
                <td>{{ $project_data['category'] ?? 'Non specificata' }}</td>
                <td class="font-semibold">Tipologia:</td>
                <td>{{ $project_data['type'] ?? 'Non specificata' }}</td>
            </tr>
            <tr>
                <td class="font-semibold">Data Inizio:</td>
                <td>{{ $project_data['start_date'] ?? 'Non specificata' }}</td>
                <td class="font-semibold">Data Fine:</td>
                <td>{{ $project_data['end_date'] ?? 'Non specificata' }}</td>
            </tr>
            <tr>
                <td class="font-semibold">Durata Prevista:</td>
                <td>
                    @if ($project_data['expected_duration'])
                        @php
                            $hours = floor($project_data['expected_duration'] / 60);
                            $minutes = $project_data['expected_duration'] % 60;
                        @endphp
                        @if ($hours > 0 && $minutes > 0)
                            {{ $hours }} ore e {{ $minutes }} minuti
                        @elseif ($hours > 0)
                            {{ $hours }} ore
                        @else
                            {{ $minutes }} minuti
                        @endif
                    @else
                        Non specificata
                    @endif
                </td>
                <td class="font-semibold">Tot. Ticket:</td>
                <td class="font-semibold">{{ $project_data['total_tickets'] }}</td>
            </tr>
            <tr>
                <td class="font-semibold">Creato il:</td>
                <td colspan="3">{{ $project_data['created_at'] }}</td>
            </tr>
        </table>
    </div>

    <div class="spacer"></div>

    <!-- SEZIONE 2: GRAFICI GENERALI -->
    @if (!empty($charts))
    <div class="card">
        <h3 class="section-header">
            Analisi Generale del Progetto
        </h3>
        
        <div class="charts-container">
            @if (isset($charts['period_distribution']))
                <div class="chart-item">
                    <img src="{{ $charts['period_distribution'] }}" alt="Distribuzione per Periodo" 
                         class="chart-image">
                </div>
            @endif
            
            @if (isset($charts['summary']))
                <div class="chart-item">
                    <img src="{{ $charts['summary'] }}" alt="Riepilogo Generale" 
                         class="chart-image">
                </div>
            @endif
        </div>
    </div>
    <div class="spacer"></div>
    @endif

    <!-- SEZIONE 3: PERIODO SELEZIONATO -->
    <div class="card">
        <h3 class="section-header">
            Ticket del Periodo Selezionato ({{ $report_info['start_date'] }} - {{ $report_info['end_date'] }})
        </h3>
        
        <!-- Statistiche periodo selezionato -->
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-label">Totale Ticket</div>
                    <div class="stat-value primary">{{ $period_stats['total_tickets'] }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Fatturabili</div>
                    <div class="stat-value success">{{ $period_stats['billable_tickets'] }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Non Fatturabili</div>
                    <div class="stat-value danger">{{ $period_stats['unbillable_tickets'] }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Tempo Totale</div>
                    <div class="stat-value primary">{{ round($period_stats['total_time'] / 60, 1) }}h</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Incidents</div>
                    <div class="stat-value danger">{{ $period_stats['incidents'] }}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Requests</div>
                    <div class="stat-value info">{{ $period_stats['requests'] }}</div>
                </div>
            </div>
        </div>

        <!-- Grafici periodo selezionato -->
        @if (!empty($charts))
        <div class="charts-container mb-4">
            @if (isset($charts['billability']))
                <div class="chart-item">
                    <img src="{{ $charts['billability'] }}" alt="Fatturabilità" 
                         class="chart-image">
                </div>
            @endif
            
            @if (isset($charts['incident_request']))
                <div class="chart-item">
                    <img src="{{ $charts['incident_request'] }}" alt="Incident vs Request" 
                         class="chart-image">
                </div>
            @endif
            
            @if (isset($charts['work_mode']))
                <div class="chart-item mt-4">
                    <img src="{{ $charts['work_mode'] }}" alt="Modalità di Lavoro" 
                         class="chart-image">
                </div>
            @endif
            
            @if (isset($charts['timeline']))
                <div class="chart-item mt-4">
                    <img src="{{ $charts['timeline'] }}" alt="Timeline" 
                         class="chart-image">
                </div>
            @endif
        </div>
        @endif
    </div>

    <div class="spacer"></div>

    <!-- SEZIONE 4: PERIODO PRECEDENTE -->
    @if ($before_period_stats['total_tickets'] > 0)
    <div class="card">
        <h3 class="section-header">
            Ticket Chiusi in Precedenza
        </h3>
        
        <div class="stats-summary-container">
            <div class="stats-summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Totale Ticket</div>
                    <div class="summary-value">{{ $before_period_stats['total_tickets'] }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Fatturabili</div>
                    <div class="summary-value green">{{ $before_period_stats['billable_tickets'] }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Non Fatturabili</div>
                    <div class="summary-value red">{{ $before_period_stats['unbillable_tickets'] }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Tempo Totale</div>
                    <div class="summary-value">{{ round($before_period_stats['total_time'] / 60, 1) }}h</div>
                </div>
            </div>
        </div>
    </div>

    <div class="spacer"></div>
    @endif

    <!-- SEZIONE 5: TICKET ANCORA APERTI -->
    @if ($still_open_stats['total_tickets'] > 0)
    <div class="card">
        <h3 class="section-header">
            Ticket Ancora Aperti
        </h3>
        
        <div class="stats-summary-container">
            <div class="stats-summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Totale Aperti</div>
                    <div class="summary-value red">{{ $still_open_stats['total_tickets'] }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Creati nel Periodo</div>
                    <div class="summary-value orange">{{ $still_open_stats['created_in_period'] }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Creati Prima</div>
                    <div class="summary-value gray">{{ $still_open_stats['created_before_period'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="spacer"></div>
    @endif

    <!-- NUOVA PAGINA PER TABELLA TICKET -->
    <div class="page-break"></div>

    <!-- SEZIONE 6: TABELLA TICKET DEL PERIODO SELEZIONATO -->
    {{-- <div class="card"> --}}
    <div>
        <h3 class="section-header">
            Elenco Ticket del Periodo Selezionato
        </h3>
        
        <p style="font-size:9">
            <span>R/I indica Request/Incident ovvero Richiesta/Problema.</span>
            <br>
            <span>O/C/S indica se è un'operazione strutturata, se collegato a un'operazione strutturata o se è singolo.</span>
            <br>
            <span>SUP indica il Supporto.</span>
            <br>
        </p>

        @php
            $selectedPeriodTickets = collect($tickets_table)->filter(function($ticket) {
                return $ticket['period'] === 'periodo_selezionato';
            });
        @endphp

        @if ($selectedPeriodTickets->count() > 0)
        <table class="project-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>R/I</th>
                    <th>Categoria</th>
                    <th>Apertura</th>
                    <th>Gestione</th>
                    <th>Tempo</th>
                    <th>O/C/S</th>
                    <th>Aperto da</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($selectedPeriodTickets as $ticket)
                {{-- <tr class="{{ $loop->even ? 'table-row-even' : '' }}">' --}}
                <tr>'
                    <td>#{{ $ticket['id'] }}</td>
                    <td>
                        {{ $ticket['type'] == 'Incident' ? 'I' : 'R' }}
                    </td>
                    <td>{{ $ticket['category'] }}</td>
                    <td>{{ $ticket['opened_at'] }}</td>
                    <td>{{ $ticket['work_mode'] }}</td>
                    <td>
                        {{ !$ticket['is_project'] ? sprintf('%02d:%02d', intdiv($ticket['actual_processing_time'], 60), $ticket['actual_processing_time'] % 60) : '' }}
                    </td>
                    <td>
                        <span class="type-badge {{ $ticket['is_master'] ? 'master' : 'child' }}">
                            {{ $ticket['is_master'] ? 'O' : ($ticket['master_id'] ? 'C' : 'S') }}
                        </span>
                    </td>
                    <td>{{ $ticket['opened_by_initials'] }}</td>
                    {{-- <td>
                        @if (!is_null($ticket['is_billable']))
                            <span class="billable-badge {{ $ticket['is_billable'] == 'Sì' ? 'yes' : 'no' }}">
                                {{ $ticket['is_billable'] }}
                            </span>
                        @else
                            -
                        @endif
                    </td> --}}
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="no-data-message">
            Nessun ticket trovato per il periodo selezionato.
        </p>
        @endif
    </div>

    <!-- NUOVA PAGINA PER DETTAGLI -->
    <div class="page-break"></div>

    <!-- SEZIONE 7: DETTAGLI TICKET DEL PERIODO SELEZIONATO -->

    @php
        $selectedPeriodTicketsDetail = collect($tickets_detail)->filter(function($ticket) use ($selectedPeriodTickets) {
            return $selectedPeriodTickets->contains(function($t) use ($ticket) {
                return $t['id'] === $ticket['ticket_data']['id'];
            });
        });
    @endphp

        @if ($selectedPeriodTicketsDetail->count() > 0)
        @foreach ($selectedPeriodTicketsDetail as $ticket)
        @if (!$loop->first)
            <div class="page-break"></div>
        @endif
        
        <div id="ticket-{{ $ticket['ticket_data']['id'] ?? '' }}"  class="ticket-container">
            <table style="width:100%">
                <tr>
                    <td style="vertical-align: middle;">
                        <h1 class="main-header">Ticket #{{ $ticket['ticket_data']['id'] ?? $ticket['id'] ?? 'N/A' }}</h1>
                    </td>
                    <td style="vertical-align: middle;">
                        @php
                            $ticketType = $ticket['ticket_data']['ticketType']['name'] ?? 'N/A';
                            $isIncident = strpos(strtolower($ticketType), 'incident') !== false || strpos(strtolower($ticketType), 'problema') !== false;
                        @endphp
                        <div class="ticket-pill"
                            style="background-color: {{ $isIncident ? '#fad6d4' : '#82aec5' }};">
                            {{ $isIncident ? 'Incident' : 'Request' }}
                        </div>
                    </td>
                </tr>
            </table>

            <hr>

            <div class="ticket-section">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Data apertura:</span>
                                <span>{{ $ticket['ticket_data']['created_at'] ?? 'N/A' }}</span>
                            </p>
                        </td>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Stato:</span>
                                <span>{{ $ticket['ticket_data']['stage']['name'] ?? 'N/A' }}</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Categoria:</span>
                                <span>{{ $ticket['ticket_data']['ticketType']['category']['name'] ?? 'N/A' }}</span>
                            </p>
                        </td>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Tipo:</span>
                                <span>{{ $ticket['ticket_data']['ticketType']['name'] ?? 'N/A' }}</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Provenienza:</span>
                                <span>{{ $ticket['source'] ?? 'N/A' }}</span>
                            </p>
                        </td>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Aperto da:</span>
                                <span>{{ $ticket['opened_by'] ?? 'N/A' }}</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Fatturabile:</span>
                                <span>{{ $ticket['ticket_data']['is_billable'] ? 'Sì' : 'No' }}</span>
                            </p>
                        </td>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Tempo:</span>
                                @if (!is_null($ticket['ticket_data']['actual_processing_time']))
                                <span>{{ !$ticket['is_project'] ? sprintf('%02d:%02d', intdiv($ticket['ticket_data']['actual_processing_time'], 60), $ticket['ticket_data']['actual_processing_time'] % 60) : '' }}</span>
                                @endif
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <p>
                                <span class="ticket-section-title">Gestione:</span>
                                <span>{{ $ticket['ticket_data']['work_mode'] == 'remote' ? 'In remoto' : ($ticket['ticket_data']['work_mode'] == 'on_site' ? 'Onsite' : '') }}</span>
                            </p>
                        </td>
                        <td style="width: 50%;">
                            
                        </td>
                    </tr>
                    @if ($ticket['ticket_data']['master_id'] != null || $ticket['is_master'])
                        <tr>
                            <td colspan="2">
                                @if ($ticket['ticket_data']['master_id'] != null)
                                    <p>
                                        <span class="ticket-section-title">Operazione strutturata: </span>
                                        <a href="#ticket-{{ $ticket['ticket_data']['master_id'] }}">
                                            #{{ $ticket['ticket_data']['master_id'] }}
                                        </a>
                                    </p>
                                @endif
                                @if ($ticket['is_master'])
                                    <p>
                                        <span class="ticket-section-title">Ticket collegati ad operazione strutturata: </span>
                                        @if (!empty($ticket['slave_ids']))
                                            @foreach ($ticket['slave_ids'] as $slave_id)
                                                <a href="#ticket-{{ $slave_id }}">
                                                    #{{ $slave_id }}
                                                </a>
                                                @if (!$loop->last)
                                                    ,
                                                @endif
                                            @endforeach
                                        @else
                                            <span>Non ci sono ticket collegati</span>
                                        @endif
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endif
                </table>
            </div>

            @if (!empty($ticket['webform_data'] ?? []))
            @php
                $ticketType = $ticket['ticket_data']['ticketType']['name'] ?? 'N/A';
                $isIncident = strpos(strtolower($ticketType), 'incident') !== false || strpos(strtolower($ticketType), 'problema') !== false;
            @endphp
            <div class="ticket-webform-{{ $isIncident ? 'incident' : 'request' }}-section">
                <p class="box-heading"><b>Dati Richiesta</b></p>
                @foreach (($ticket['webform_data'] ?? []) as $key => $value)
                    @if (!is_null($value) && $value !== '')
                    <p>
                        <span class="ticket-section-title">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                        <span>
                            @if (is_array($value))
                                {{ implode(', ', $value) }}
                            @else
                                {{ $value }}
                            @endif
                        </span>
                    </p>
                    @endif
                @endforeach
            </div>
            @endif

            @if (!empty($ticket['ticket_data']['description'] ?? ''))
            <div class="ticket-section">
                <p><span class="ticket-section-title">Descrizione</span></p>
                <p>{{ $ticket['ticket_data']['description'] ?? '' }}</p>
            </div>
            @endif

            @if (!empty($ticket['messages'] ?? []) && count($ticket['messages'] ?? []) > 0)
            <div class="ticket-messages">
                <p><span class="ticket-section-title">Messaggi</span></p>
                @foreach (array_slice(($ticket['messages'] ?? []), 0, 3) as $message)
                <table style="width:100%">
                    <tr>
                        <td class="ticket-messages-author">
                            {{ $message['user'] }}
                        </td>
                        <td class="ticket-messages-date">
                            <span style="text-align: right">{{ $message['created_at'] }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <span>{{ Str::limit($message['message'], 200) }}</span>
                        </td>
                    </tr>
                </table>
                @if (!$loop->last)
                    <br>
                @endif
                @endforeach
                @if (count($ticket['messages'] ?? []) > 3)
                {{-- <p style="font-style: italic; color: #6b7280; font-size: 0.7rem;">
                    ... e altri {{ count($ticket['messages'] ?? []) - 3 }} messaggi
                </p> --}}
                <p>
                    <a href="{{ $ticket['ticket_frontend_url'] }}"
                        style="color: #cc7a00; font-size: 0.75rem;" target="_blank">
                        Vedi di più
                    </a>
                </p>
                @endif
            </div>
            @endif

            @if (!empty($ticket['closing_info'] ?? []))
            <div class="ticket-closing">
                <p><span class="ticket-section-title">Chiusura</span></p>
                <p>
                    <span class="ticket-section-title">Data:</span>
                    <span>{{ $ticket['closing_info']['closed_at'] ?? '' }}</span>
                </p>
                @if (!empty($ticket['closing_info']['message'] ?? ''))
                <p>
                    <span class="ticket-section-title">Note:</span>
                    <span>{{ $ticket['closing_info']['message'] ?? '' }}</span>
                </p>
                @endif
            </div>
            @endif
            
        </div>
        @endforeach
    @else
        <div class="card">
            <p class="no-data-message">
                Nessun dettaglio ticket disponibile per il periodo selezionato.
            </p>
        </div>
    @endif

</body>
</html>

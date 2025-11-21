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
            @if (!empty($project_data['logo_url']))
                @php
                    $imgData = @file_get_contents($project_data['logo_url']);
                @endphp
                @if ($imgData !== false)
                    <img src="data:image/png;base64,{{ base64_encode($imgData) }}" alt="logo"
                        class="logo-image">
                @endif
            @endif
        </div>

        <h2>
            <b>Report Progetto</b>
        </h2>
        <h3><b>{{ $project_data['name'] }}</b></h3>

        <div class="card">
            <h3 class="sub-header no-margin-top">
                {{ $project_data['company'] }}
            </h3>

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
        <h3 class="section-header no-margin-top">
            Informazioni Progetto
        </h3>
        
                <table class="project-info-table">
            <tr>
                <td><b>ID Progetto:</b></td>
                <td>{{ $project_data['id'] }}</td>
                <td><b>Stato:</b></td>
                <td>
                    <span>
                        {{ $project_data['status'] }}
                    </span>
                </td>
            </tr>
            <tr>
                <td><b>Nome:</b></td>
                <td colspan="3">{{ $project_data['name'] }}</td>
            </tr>
            <tr>
                <td><span><b>Categoria:</b></span></td>
                <td>{{ $project_data['category'] ?? 'Non specificata' }}</td>
                <td><b>Tipologia:</b></td>
                <td>{{ $project_data['type'] ?? 'Non specificata' }}</td>
            </tr>
            <tr>
                <td><b>Data Inizio:</b></td>
                <td>{{ $project_data['start_date'] ?? 'Non specificata' }}</td>
                <td><b>Data Fine:</b></td>
                <td>{{ $project_data['end_date'] ?? 'Non specificata' }}</td>
            </tr>
            <tr>
                <td><b>Creato il:</b></td>
                <td>{{ $project_data['created_at'] }}</td>
                <td><b>Tot. Ticket:</b></td>
                <td><b>{{ $project_data['total_tickets'] }}</b></td>
            </tr>
            <tr>
                <td><b>Durata prevista:</b></td>
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
                <td><b>Durata totale (chiusi al {{ $report_info['end_date'] }}):</b></td>
                <td>
                    {{-- {{ $project_data['total_closed_time'] }} --}}
                        @php
                            // $hours = floor($project_data['total_closed_time'] / 60);
                            // $minutes = $project_data['total_closed_time'] % 60;
                            $hours = floor($total_closed_time / 60);
                            $minutes = $total_closed_time % 60;
                        @endphp
                        @if ($hours > 0 && $minutes > 0)
                            {{ $hours }} ore e {{ $minutes }} minuti
                        @elseif ($hours > 0)
                            {{ $hours }} ore
                        @else
                            {{ $minutes }} minuti
                        @endif
                </td>
            </tr>
            <tr>
                <td><b>Differenza:</b></td>
                <td>
                    @if ($project_data['expected_duration'])
                        @php
                            // $difference = $project_data['total_closed_time'] - $project_data['expected_duration'];
                            $difference = $project_data['expected_duration'] - $total_closed_time;
                            $absDifference = abs($difference);
                            $hours = floor($absDifference / 60);
                            $minutes = $absDifference % 60;
                        @endphp
                        @if($difference != 0)
                            @if($difference > 0)
                                Rimanente 
                            @else
                                Sforato di 
                            @endif
                            @if ($hours > 0 && $minutes > 0)
                                {{ $hours }} ore e {{ $minutes }} minuti
                            @elseif ($hours > 0)
                                {{ $hours }} ore
                            @else
                                {{ $minutes }} minuti
                            @endif
                        @else
                            0
                        @endif
                            
                    @else
                        Non specificata
                    @endif
                </td>
                <td><b>Ancora aperti al {{ $report_info['end_date'] }} <br>(tempo non preciso):</b></td>
                <td>
                    @if ($still_open_stats['current_total_time'] > 0)
                        @php
                            $hours = floor($still_open_stats['current_total_time'] / 60);
                            $minutes = $still_open_stats['current_total_time'] % 60;
                        @endphp
                        @if ($hours > 0 && $minutes > 0)
                            {{ $hours }} ore e {{ $minutes }} minuti
                        @elseif ($hours > 0)
                            {{ $hours }} ore
                        @else
                            {{ $minutes }} minuti
                        @endif
                    @else
                        0
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="spacer"></div>

    <!-- SEZIONE 2: GRAFICI GENERALI -->
    @if (!empty($charts))
    <div class="card">
        <h3 class="section-header no-margin-top">
            Analisi Progetto
        </h3>
        
        <div class="charts-container">
            @if (isset($charts['project_time']))
                <div class="chart-item">
                    @php
                        $imgData = @file_get_contents($charts['project_time']);
                    @endphp
                    @if ($imgData !== false)
                        <img src="data:image/png;base64,{{ base64_encode($imgData) }}" alt="Confronto Tempo Progetto" 
                             class="chart-image">
                    @else
                        <p>Grafico tempo progetto non disponibile</p>
                    @endif
                </div>
            @endif
            
            @if (isset($charts['work_mode']))
                <div class="chart-item">
                    @php
                        $imgData = @file_get_contents($charts['work_mode']);
                    @endphp
                    @if ($imgData !== false)
                        <img src="data:image/png;base64,{{ base64_encode($imgData) }}" alt="Modalità di Gestione" 
                             class="chart-image">
                    @else
                        <p>Grafico modalità di gestione non disponibile</p>
                    @endif
                </div>
            @endif
        </div>
        <div class="charts-container">
            @if (isset($charts['trend']))
                <div class="chart-item">
                    @php
                        $imgData = @file_get_contents($charts['trend']);
                    @endphp
                    @if ($imgData !== false)
                        <img src="data:image/png;base64,{{ base64_encode($imgData) }}" alt="Andamento Tempo Progetto" 
                             class="chart-image">
                    @else
                        <p>Grafico andamento tempo progetto non disponibile</p>
                    @endif
                </div>
            @endif
        </div>
        <div style="clear: both;"></div>
    </div>
    <div class="spacer"></div>
    @endif

    <div class="page-break"></div>

    <div class="card">
        <!-- SEZIONE 3: PERIODO SELEZIONATO -->
        <div>
            <h3 class="section-header no-margin-top">
                Ticket del Periodo Selezionato ({{ $report_info['start_date'] }} - {{ $report_info['end_date'] }})
            </h3>
            
            <!-- Statistiche periodo selezionato -->
            <div class="stats-summary-container">
                {{-- <table style="width: 100%; border: none;">
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Totale Ticket</b></div>
                            <div class="stat-value-small">{{ $period_stats['total_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo Totale</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($period_stats['total_time'], 60), $period_stats['total_time'] % 60) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Ticket on site</b></div>
                            <div class="stat-value-small">{{ $period_stats['onsite_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo on site</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($period_stats['onsite_tickets_time'], 60), $period_stats['onsite_tickets_time'] % 60) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Ticket da remoto</b></div>
                            <div class="stat-value-small">{{ $period_stats['remote_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo da remoto</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($period_stats['remote_tickets_time'], 60), $period_stats['remote_tickets_time'] % 60) }}</div>
                        </td>
                    </tr>
                </table> --}}
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Totale Ticket</b></div>
                            <div class="stat-value-small">{{ $period_stats['total_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo Totale</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($period_stats['total_time'], 60), $period_stats['total_time'] % 60) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Ticket on site</b></div>
                            <div class="stat-value-small">{{ $period_stats['onsite_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo on site</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($period_stats['onsite_tickets_time'], 60), $period_stats['onsite_tickets_time'] % 60) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Ticket da remoto</b></div>
                            <div class="stat-value-small">{{ $period_stats['remote_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo da remoto</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($period_stats['remote_tickets_time'], 60), $period_stats['remote_tickets_time'] % 60) }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- SEZIONE 4: PERIODO PRECEDENTE -->
        @if ($before_period_stats['total_tickets'] > 0)
        <div>
            <h3 class="section-header">
                Ticket Chiusi in Precedenza
            </h3>
            
            <div class="stats-summary-container">
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Totale Ticket</b></div>
                            <div class="stat-value-small">{{ $before_period_stats['total_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo totale</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($before_period_stats['total_time'], 60), $before_period_stats['total_time'] % 60) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Ticket on site</b></div>
                            <div class="stat-value-small green">{{ $before_period_stats['onsite_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo on site</b></div>
                            <div class="stat-value-small green">{{ sprintf('%02d:%02d', intdiv($before_period_stats['onsite_tickets_time'], 60), $before_period_stats['onsite_tickets_time'] % 60) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Ticket da remoto</b></div>
                            <div class="stat-value-small red">{{ $before_period_stats['remote_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo da remoto</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($before_period_stats['remote_tickets_time'], 60), $before_period_stats['remote_tickets_time'] % 60) }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        @endif

        <!-- SEZIONE 5: TICKET ANCORA APERTI -->
        @if ($still_open_stats['total_tickets'] > 0)
        <div>
            <h3 class="section-header">
                Ticket Ancora Aperti al {{ $report_info['end_date'] }}
            </h3>
            
            <div class="stats-summary-container">
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Totale Aperti</b></div>
                            <div class="stat-value-small">{{ $still_open_stats['total_tickets'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Tempo corrente (potrebbe non essere accurato)</b></div>
                            <div class="stat-value-small">{{ sprintf('%02d:%02d', intdiv($still_open_stats['current_total_time'], 60), $still_open_stats['current_total_time'] % 60) }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Creati nel Periodo</b></div>
                            <div class="stat-value-small">{{ $still_open_stats['created_in_period'] }}</div>
                        </td>
                        <td style="width: 50%; text-align: center; padding: 0.5rem; border: none;">
                            <div><b>Creati Prima</b></div>
                            <div class="stat-value-small">{{ $still_open_stats['created_before_period'] }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        @endif
    </div>

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
                        <h1 ><b>Ticket #{{ $ticket['ticket_data']['id'] ?? $ticket['id'] ?? 'N/A' }}</b></h1>
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

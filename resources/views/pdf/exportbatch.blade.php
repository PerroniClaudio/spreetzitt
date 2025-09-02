<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>

@include('components.style')

<body>

    <div style="text-align:center; height:100%">

        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logo_url)) }}" alt="iftlogo"
            style="width: 192px; height: 38px;">


        <h1 class="main-header" style="font-size:3rem;line-height: 1;margin-top: 4rem;margin-bottom: 4rem;">
            {{ $company['name'] }}</h1>

        {{-- @php
            $logoUrl = $company->temporaryLogoUrl();
        @endphp
        @if ($logoUrl)
            <img src="data:image;base64, {{ base64_encode(file_get_contents($logoUrl)) }}" alt="Company Logo"
                style="max-height: 100px; max-width: 200px;">
        @endif --}}

        <h3>Periodo</h3>
        <p>{{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>

    </div>


    <div class="page-break"></div>

    <div>

        <div style="text-align:center;margin-top: 1rem;margin-bottom: 1rem;">
            <p>Periodo: {{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>
        </div>

        <div class="card">
            <table width="100%">
                <tr>
                    <td>

                        <b>Ticket per servizio</b>
                    </td>
                    <td>
                        <table width="100%">
                            <tr>
                                <td>
                                    <p style="font-weight: 600">{{ count($tickets) }}</p>
                                    <p class="text-small">Ticket creati nel periodo</p>
                                </td>
                                <td>
                                    <p style="font-weight: 600">{{ $closed_tickets_count }}</p>
                                    <p class="text-small">Ticket risolti nel periodo</p>
                                </td>
                                <td>
                                    <p style="font-weight: 600">{{ $other_tickets_count }}</p>
                                    <p class="text-small">Ticket in Lavorazione</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table width="100%">
                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_category_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_source_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_weekday_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>

                    @if ($dates_are_more_than_one_month_apart)
                        <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                            <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_per_month_url)) }}"
                                style="width: 100%; height: auto;">
                        </td>
                    @endif
                </tr>
            </table>
        </div>

        <table width="100%" style="margin-top: 1rem;">
            <tr>
                <td>
                    <table width="100%">
                        <tr>
                            <td>
                                <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_priority_url)) }}"
                                    style="width: 100%; height: auto;">
                            <td>
                        </tr>
                        <tr>
                            <td>
                                <img src="data:image/png;base64,{{ base64_encode(file_get_contents($tickets_by_user_url)) }}"
                                    style="width: 100%; height: auto;">
                            <td>
                        </tr>
                    </table>
                </td>

                <td>
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents($tickets_sla_url)) }}"
                        style=" width:100%;height: auto;">
                </td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    <div>
        <div style="text-align:center;margin-top: 1rem;margin-bottom: 1rem;">
            <p>Periodo: {{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>
        </div>

        <div class="card">
            <table width="100%">
                <tr>
                    <td>

                        <b>Ticket per tipologia</b>
                    </td>
                    <td>
                        <table width="100%">
                            <tr>
                                <td>
                                    <p style="font-weight: 600">{{ count($tickets) }}</p>
                                    <p class="text-small">Ticket creati nel periodo</p>
                                </td>
                                <td>
                                    <p style="font-weight: 600">{{ $incident_number }}</p>
                                    <p class="text-small">Numero di Incident</p>
                                </td>
                                <td>
                                    <p style="font-weight: 600">{{ $request_number }}</p>
                                    <p class="text-small">Numero di Request</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table width="100%">
                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_category_incident_bar_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_category_request_bar_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_type_incident_bar_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>


                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_type_request_bar_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>

                </tr>

                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($wrong_type_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>



                </tr>
            </table>
        </div>

    </div>

    <div class="page-break"></div>

    <div>
        <h1>Indice</h1>
        <hr>

        <table style="width:100%">
            <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td style="width:20%">
                            <a href="#ticket-{{ $ticket['id'] }}">Ticket
                                #{{ $ticket['id'] }}
                            </a>
                        </td>
                        <td style="width:20%" class="text-small">{{ $ticket['opened_at'] }}</td>
                        <td style="width:20%">
                            {{ $ticket['incident_request'] }}
                        </td>
                        <td style="width:20%">
                            {{ $ticket['opened_by'] }}
                        </td>
                        <td style="width:20%">
                            {{ $ticket['current_status'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>


    </div>


    <div class="page-break"></div>


    @foreach ($tickets as $ticket)
        @if (!is_null($ticket['webform_data']))
            @if (!$ticket['should_show_more'])
                <div id="ticket-{{ $ticket['id'] }}" class="ticket-container">
                    <table style="width:100%">
                        <tr>
                            <td style="vertical-align: middle;">
                                <h1 class="main-header">Ticket #{{ $ticket['id'] }}</h1>
                            </td>
                            <td style="vertical-align: middle;">
                                <div class="ticket-pill"
                                    style="background-color: {{ $ticket['incident_request'] == 'Request' ? '#d4dce3' : '#f8d7da' }};">
                                    {{ $ticket['incident_request'] }}
                                </div>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <div class="ticket-section">
                        <p><span class="ticket-section-title">Data di apertura:</span>
                            <span>{{ $ticket['opened_at'] }}</span>
                        </p>
                        <p><span class="ticket-section-title">Aperto da:</span> <span>{{ $ticket['opened_by'] }}</span>
                        </p>
                        <p><span class="ticket-section-title">Categoria:</span> <span>{{ $ticket['category'] }}</span>
                        </p>
                        <p><span class="ticket-section-title">Tipologia:</span> <span>{{ $ticket['type'] }}</span>
                        </p>
                        <p>
                            <span class="ticket-section-title">Stato al {{ $date_to->format('d/m/Y') }}:</span>
                            <span>{{ $ticket['current_status'] }}</span>
                        </p>
                    </div>

                    <div class="ticket-webform-{{ strtolower($ticket['incident_request']) }}-section">
                        <p class="box-heading"><b>Dati webform</b></p>
                        @if (!is_null($ticket['webform_data']))
                            <table style="width:100%">

                                @php
                                    unset($ticket['webform_data']->description);
                                @endphp

                                @foreach ($ticket['webform_data'] as $key => $value)
                                    @if ($loop->index % 3 == 0)
                                        <tr>
                                    @endif
                                    <td>
                                        @switch($key)
                                            @case('description')
                                            @break

                                            @case('referer')
                                                <span><b>Utente interessato</b><br> {{ $value }}</span> <br>
                                            @break

                                            @case('referer_it')
                                                <span><b>{{ \App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'Referente IT') }}</b><br> {{ $value }}</span> <br>
                                            @break

                                            @case('office')
                                                <span><b>Sede</b><br> {{ $value }}</span> <br>
                                            @break

                                            @default
                                                @if (is_array($value))
                                                    <span><b>{{ $key }}</b><br>
                                                        {{ implode(', ', $value) }}</span>
                                                    <br>
                                                @else
                                                    <span><b>{{ $key }}</b><br> {{ $value }}</span> <br>
                                                @endif
                                        @endswitch
                                    </td>
                                    @if ($loop->iteration % 3 == 0)
                                        </tr>
                                    @endif
                                @endforeach
                                @if ($loop->count % 3 != 0)
                                    </tr>
                                @endif
                            </table>
                        @endif
                    </div>

                    <div class="ticket-section">
                        <p><span class="ticket-section-title">Descrizione</span></p>
                        <p>{{ $ticket['description'] }}</p>
                    </div>

                    <div class="ticket-messages">
                        <p><span class="ticket-section-title">Messaggi</span></p>

                        @foreach ($ticket['messages'] as $key => $value)
                            @if ($loop->first)
                                @continue
                            @endif

                            <table style="width:100%">
                                <tr>
                                    <td class="ticket-messages-author">
                                        {{ $value['user'] }}
                                    </td>
                                    <td class="ticket-messages-date">
                                        <span style="text-align: right">{{ $value['created_at'] }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <span>{{ $value['message'] }}</span>
                                    </td>
                                </tr>
                            </table>
                        @endforeach
                    </div>

                    @if ($ticket['closing_message']['message'] != '')
                        <div class="ticket-closing">
                            <p><span class="ticket-section-title">Chiusura - {{ $ticket['closed_at'] }}</span></p>
                            <p>{{ $ticket['closing_message']['message'] }}</p>
                        </div>
                    @endif


                </div>

                @if (!$loop->last)
                    <div class="page-break"></div>
                @endif
            @else
                <div id="ticket-{{ $ticket['id'] }}" class="ticket-container">
                    <table style="width:100%">
                        <tr>
                            <td style="vertical-align: middle;">
                                <h1 class="main-header">Ticket #{{ $ticket['id'] }}</h1>
                            </td>
                            <td style="vertical-align: middle;">
                                <div class="ticket-pill"
                                    style="background-color: {{ $ticket['incident_request'] == 'Request' ? '#82aec5' : '#fad6d4' }};">
                                    {{ $ticket['incident_request'] }}
                                </div>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <div class="ticket-section">
                        <p><span class="ticket-section-title">Data di apertura:</span>
                            <span>{{ $ticket['opened_at'] }}</span>
                        </p>
                        <p><span class="ticket-section-title">Aperto da:</span>
                            <span>{{ $ticket['opened_by'] }}</span>
                        </p>
                        <p><span class="ticket-section-title">Categoria:</span> <span>{{ $ticket['category'] }}</span>
                        </p>
                        <p><span class="ticket-section-title">Tipologia:</span> <span>{{ $ticket['type'] }}</span>
                        </p>
                        <p>
                            <span class="ticket-section-title">Stato al {{ $date_to->format('d/m/Y') }}:</span>
                            <span>{{ $ticket['current_status'] }}</span>
                        </p>
                    </div>

                    <div class="ticket-webform-{{ strtolower($ticket['incident_request']) }}-section">
                        <p class="box-heading"><b>Dati webform</b></p>
                        @if (!is_null($ticket['webform_data']))
                            <table style="width:100%">

                                @php
                                    unset($ticket['webform_data']->description);
                                @endphp

                                @foreach ($ticket['webform_data'] as $key => $value)
                                    @if ($loop->index % 3 == 0)
                                        <tr>
                                    @endif
                                    <td>
                                        @switch($key)
                                            @case('description')
                                            @break

                                            @case('referer')
                                                <span><b>Utente interessato</b><br> {{ $value }}</span> <br>
                                            @break

                                            @case('referer_it')
                                                <span><b>{{ \App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'Referente IT') }}</b><br> {{ $value }}</span> <br>
                                            @break

                                            @case('office')
                                                <span><b>Sede</b><br> {{ $value }}</span> <br>
                                            @break

                                            @default
                                                @if (is_array($value))
                                                    <span><b>{{ $key }}</b><br>
                                                        {{ implode(', ', $value) }}</span>
                                                    <br>
                                                @else
                                                    <span><b>{{ $key }}</b><br> {{ $value }}</span> <br>
                                                @endif
                                        @endswitch
                                    </td>
                                    @if ($loop->iteration % 3 == 0)
                                        </tr>
                                    @endif
                                @endforeach
                                @if ($loop->count % 3 != 0)
                                    </tr>
                                @endif
                            </table>
                        @endif
                    </div>

                    <div class="ticket-section">
                        <p><span class="ticket-section-title">Descrizione</span></p>
                        <p>{{ $ticket['description'] }}</p>
                    </div>

                    <div class="ticket-messages">
                        <p><span class="ticket-section-title">Messaggi</span></p>

                        @foreach ($ticket['messages'] as $key => $value)
                            <table style="width:100%">
                                <tr>
                                    <td class="ticket-messages-author">

                                        {{ $value['user'] }}
                                    </td>
                                    <td class="ticket-messages-date">
                                        <span style="text-align: right">{{ $value['created_at'] }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <span>{{ $value['message'] }}</span>
                                    </td>
                                </tr>
                            </table>
                        @endforeach

                        <p>
                            <a href="{{ $ticket['ticket_frontend_url'] }}"
                                style="color: #cc7a00; font-size: 0.75rem;" target="_blank">
                                Vedi di più
                            </a>
                        </p>
                    </div>

                    @if ($ticket['closing_message']['message'] != '')
                        <div class="ticket-closing">
                            <p><span class="ticket-section-title">Chiusura - {{ $ticket['closed_at'] }}</span></p>
                            <p>{{ $ticket['closing_message']['message'] }}</p>
                        </div>
                    @endif


                </div>

                @if (!$loop->last)
                    <div class="page-break"></div>
                @endif
            @endif
        @endif
    @endforeach



</body>

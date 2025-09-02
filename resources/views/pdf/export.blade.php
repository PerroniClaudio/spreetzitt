<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>

@include('components.style')

<body>
    <h1 class="main-header">Report Esteso Ticket #{{ $ticket->id }}</h1>
    <hr>
    <div class="box">
        <p class="box-heading"><b>Descrizione</b></p>
        <p>
            {{ $webform_data->description }}
        </p>
    </div>

    <table style="width:100%">
        <tbody>
            <tr>
                <td style="vertical-align: top; width:50%;" class="box">
                    <p class="box-heading"><b>Dati webform</b></p>
                    @foreach ($webform_data as $key => $value)
                        @switch($key)
                            @case('description')
                            @break

                            @case('referer')
                                <p><b>Utente interessato</b><br> {{ $value }}</p>
                            @break

                            @case('referer_it')
                                <p><b>{{ \App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'Referente IT') }}</b><br> {{ $value }}</p>
                            @break

                            @case('office')
                                <p><b>Sede</b><br> {{ $value }}</p>
                            @break

                            @default
                                <p><b>{{ $key }}</b><br> 
                                    @if(is_array($value))
                                        {{ implode(', ', $value) }}</p>
                                    @else
                                        {{ $value }}</p>
                                    @endif
                                </p>
                        @endswitch
                    @endforeach
                </td>
                <td style="vertical-align: top; width:50%;" class="box">
                    <p class="box-heading"><b>Avanzamento</b></p>
                    <table>
                        <thead>
                            <tr>
                                <th style="text-align: left">Stato</th>
                                <th style="text-align: left">Numero</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>In Attesa</td>
                                <td>{{ $status_updates['attesa'] }}</td>
                            </tr>
                            <tr>
                                <td>Assegnato</td>
                                <td>{{ $status_updates['assegnato'] }}</td>
                            </tr>
                            <tr>
                                <td>In Corso</td>
                                <td>{{ $status_updates['in_corso'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- <div class="box" style="margin-top:.5rem;"> --}}
    <div class="box">
        <p class="box-heading"><b>Messaggio di chiusura</b></p>
        <p>
            {{ $closing_messages }}
        </p>
    </div>

    {{-- Parte limitata agli admin (se si faranno pdf anche per l'utente) --}}
    <table style="width:100%">
        <tbody>
            <tr>
                <td style="vertical-align: top; width:50%;" class="box">
                    <p class="box-heading"><b>Responsabilità del dato<b></p>
                    <p>
                        {{ $ticket->is_user_error ? 'Cliente' : 'Supporto' }}
                    </p>
                </td>
                <td style="vertical-align: top; width:50%;" class="box">
                    <p class="box-heading"><b>Form corretto<b></p>
                    <p>
                        {{ $ticket->is_form_correct ? 'Si' : 'No' }}
                    </p>
                </td>

            </tr>
            <tr>
                <td style="vertical-align: top; width:50%;" class="box">
                    <p class="box-heading"><b>Cliente autonomo<b></p>
                    <p>
                        {{ $ticket->was_user_self_sufficient ? 'Si' : 'No' }}
                    </p>
                </td>
                @if ($ticket->ticketType->category->is_problem)
                    <td style="vertical-align: top; width:50%;" class="box">
                        <p class="box-heading"><b>Responsabilità del problema<b></p>
                        <p>
                            {{ $ticket->is_user_error_problem ? 'Cliente' : 'Supporto' }}
                        </p>
                    </td>
                @endif
            </tr>
            <tr>
                <td style="vertical-align: top; width:50%;" class="box">
                    <p class="box-heading"><b>Tempo di elaborazione previsto<b></p>
                    <p>{{ $ticket->ticketType->expected_processing_time == null
                            ? 'Non impostato'
                            : (str_pad($ticket->ticketType->expected_processing_time / 60, 2, '0', STR_PAD_LEFT)) 
                                . ':' . (str_pad($ticket->ticketType->expected_processing_time % 60, 2, '0', STR_PAD_LEFT)) 
                        }}
                    </p>
                    <p class="box-heading"><b>Tempo di elaborazione effettivo</b></p>
                    <p>{{ $ticket->actual_processing_time
                        ? str_pad($ticket->actual_processing_time / 60, 2, '0', STR_PAD_LEFT)
                            . ':' . (str_pad($ticket->actual_processing_time % 60, 2, '0', STR_PAD_LEFT))
                        : "Non impostato"
                        }}
                    </p>
                </td>
            </tr>
            <tr>
                <td style="vertical-align: top; width:50%;" class="box">
                    <p class="box-heading"><b>Modalità di lavoro<b></p>
                    <p>
                        {{ $ticket->work_mode ?? 'Non definita' }}
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <h2>Note interne</h2>
    <hr>

    <div class="box">
        @foreach ($ticket->statusUpdates as $key => $value)
            <div>
                <p><b>Supporto</b></p>
                <p>
                    {{ $value->content }}
                </p>
            </div>
        @endforeach
    </div>
    {{-- Fine parte limitata agli admin --}}

    <h2>Messaggi</h2>
    <hr>
    <div class="box">
        @foreach ($ticket->messages as $key => $value)
            @if ($loop->first)
                @continue
            @endif

            <table style="width:100%">
                <tr>
                    <td style="vertical-align: top; width:70%;">
                        @if ($value->user->is_admin == 1)
                            <p><b>Supporto - Update</b></p>
                        @else
                            <p><b>{{ $value->user->name }} {{ $value->user->surname }}</b></p>
                        @endif
                    </td>
                    <td style="vertical-align: top; width:30%;">
                        <p style="text-align: right">{{ $value->created_at->format('d/m/Y H:i') }}</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p>{{ $value->message }}</p>
                    </td>
                </tr>
            </table>
        @endforeach
    </div>


    {{-- <div class="page-break"></div>
    <h1>Page 2</h1> --}}

</body>

</html>

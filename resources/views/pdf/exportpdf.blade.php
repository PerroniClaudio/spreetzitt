<!DOCTYPE html>
<html lang="en">

@php 
    $ticketSources = config('app.ticket_sources'); 
@endphp

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>

@include('components.style')

<body>

    <div style="text-align:center;">

        <div>
            @php
                $imgData = !empty($logo_url) ? @file_get_contents($logo_url) : false;
            @endphp
            @if ($imgData !== false)
                <img src="data:image/png;base64,{{ base64_encode($imgData) }}" alt="logo"
                    style="width: auto; height: 38px; position: absolute; top: 0; left: 0;">
            @endif
        </div>


        <h1 class="main-header" style="font-size:2rem;line-height: 1;margin-top: 3rem; margin-bottom: 1rem;">
            Report attività eseguite
        </h1>

        <div class="card">
            <h2 class="main-header" style="font-size:1.5rem;line-height: 1;margin-bottom: 0.5rem;">
                {{ $company ? (is_array($company) ? $company['name'] : $company->name) : 'Azienda non selezionata' }}</h2>

            <table style="margin: auto; font-size: 0.75rem; width: fit-content;">
                <tr style="width: fit-content;">
                    <td style="width: 50%; text-align: right;">
                        <span><b>Filtro:</b></span>
                        <span
                            style="margin-left: 0; margin-right:2rem;">{{ $filter == 'all' ? 'Tutti' : ($filter == 'request' ? 'Richieste' : ($filter == 'incident' ? 'Problemi' : 'Non specificato')) }}</span>
                    </td>
                    <td style="width: 50%">
                        <span><b>Periodo:</b></span>
                        <span style="margin-left: 0">{{ $date_from->format('d/m/Y') }} -
                            {{ $date_to->format('d/m/Y') }}</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <div style="height: 1rem;"></div>

    <div class="card">
        <p style="margin-bottom: 0.5rem;"><b>Conteggio e fatturabilità ticket</b></p>
        <p style="font-size: 0.75rem; margin-top: 0; margin-bottom: 0.5rem;">
            In caso di attività programmate e ticket a esse collegati, viene conteggiato solo il tempo dell'attività programmata.
            <br>
            Per "Remoto fatturabile" si intendono ad esempio: attività di progetto, non incluse nel contratto, ecc.
        </p>

        @php
            $total_billable_tickets_count =
                $on_site_billable_tickets_count + $remote_billable_tickets_count;
            $total_billable_work_time = $on_site_billable_work_time + $remote_billable_work_time;
            $total_unbillable_tickets_count =
                $unbillable_on_site_normal_tickets_count + $unbillable_on_site_activity_tickets_count + $unbillable_remote_normal_tickets_count + $unbillable_remote_activity_tickets_count;
            $total_unbillable_work_time = $unbillable_on_site_normal_work_time + $unbillable_on_site_activity_work_time + $unbillable_remote_normal_work_time + $unbillable_remote_activity_work_time;
        @endphp

        <table style="width:100%; border: 1px solid #353131; border-collapse: collapse;">

            <thead>
                <tr style="border: 1px solid #353131;">
                    <th style="border: 1px solid #353131;" class="text-small-plus  ">
                        Descrizione delle attività che andranno in fattura
                    </th>
                    <th style="border: 1px solid #353131; width:15%;" class="text-small-plus  ">
                        Conteggio ticket
                    </th>
                    <th style="border: 1px solid #353131; width:25%;" class="text-small-plus  ">
                        Tempo gestione ticket (hh:mm)
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Attività onsite fatturabili
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $on_site_billable_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ sprintf('%02d:%02d', intdiv($on_site_billable_work_time, 60), $on_site_billable_work_time % 60) }}
                        </p>
                    </td>
                </tr>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Attività in remoto fatturabile
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $remote_billable_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ sprintf('%02d:%02d', intdiv($remote_billable_work_time, 60), $remote_billable_work_time % 60) }}
                        </p>
                    </td>
                </tr>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Totale attività fatturabili
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $total_billable_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ sprintf('%02d:%02d', intdiv($total_billable_work_time, 60), $total_billable_work_time % 60) }}
                        </p>
                    </td>
                </tr>
            </tbody>

        </table>
        
        <div style="height: 1rem;"></div>

        <table style="width:100%; border: 1px solid #353131; border-collapse: collapse;">

            <thead>
                <tr style="border: 1px solid #353131;">
                    <th style="border: 1px solid #353131;" class="text-small-plus  ">
                        Descrizione delle attività incluse nelle attività programmate o nel contratto accordi di servizio
                    </th>
                    <th style="border: 1px solid #353131; width:15%;" class="text-small-plus  ">
                        Conteggio ticket
                    </th>
                    <th style="border: 1px solid #353131; width:25%;" class="text-small-plus  ">
                        Tempo gestione ticket (hh:mm)
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Attività onsite incluse nel contratto quadro/accordi di servizio (non fatturabili e non incluse in un'attività programmata)
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $unbillable_on_site_normal_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ sprintf('%02d:%02d', intdiv($unbillable_on_site_normal_work_time, 60), $unbillable_on_site_normal_work_time % 60) }}
                        </p>
                    </td>
                </tr>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Attività onsite non fatturabili incluse in un'attività programmata
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $unbillable_on_site_activity_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ sprintf('%02d:%02d', intdiv($unbillable_on_site_activity_work_time, 60), $unbillable_on_site_activity_work_time % 60) }}
                        </p>
                    </td>
                </tr>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Attività in remoto incluse nel contratto quadro/accordi di servizio (non fatturabili e non incluse in un'attività programmata)
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $unbillable_remote_normal_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ sprintf('%02d:%02d', intdiv($unbillable_remote_normal_work_time, 60), $unbillable_remote_normal_work_time % 60) }}
                        </p>
                    </td>
                </tr>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Attività in remoto non fatturabili incluse in un'attività programmata
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $unbillable_remote_activity_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ sprintf('%02d:%02d', intdiv($unbillable_remote_activity_work_time, 60), $unbillable_remote_activity_work_time % 60) }}
                        </p>
                    </td>
                </tr>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Ancora in gestione (non conteggiati)
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $still_open_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131;"></td>
                </tr>
                <tr style="border: 1px solid #353131;">
                    <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                        <p class="text-small-plus">
                            Totale attività incluse nel contratto
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ $total_unbillable_tickets_count }}
                        </p>
                    </td>
                    <td style="border: 1px solid #353131; text-align: center;">
                        <p class="text-small-plus " style="font-weight: 600">
                            {{ sprintf('%02d:%02d', intdiv($total_unbillable_work_time, 60), $total_unbillable_work_time % 60) }}
                        </p>
                    </td>
                </tr>
            </tbody>

        </table>

        <div style="height: 1rem;"></div>

        {{-- Grafici top 5 tempo di esecuzione per fatturabilità --}}
        <table width="100%" style="margin-top: 1rem;">
            <tr>
                <td>
                    @php
                        $imgData = !empty($ticket_by_billable_time_url) ? @file_get_contents($ticket_by_billable_time_url) : false;
                    @endphp
                    @if ($imgData !== false)
                        <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                            style="width: 100%; height: auto;">
                    @else
                        <span>Immagine non disponibile</span>
                    @endif
                </td>

                <td>
                    @php
                        $imgData = !empty($ticket_by_unbillable_time_url) ? @file_get_contents($ticket_by_unbillable_time_url) : false;
                    @endphp
                    @if ($imgData !== false)
                        <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                            style="width: 100%;height: auto;">
                    @else
                        <span>Immagine non disponibile</span>
                    @endif

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
                
            {{-- Tabella ticket fatturabili col dettaglio del tempo, divisi per categoria --}}
            <p style="margin-bottom: 0.5rem;"><b>Tempo di gestione ticket fatturabili per categoria</b></p>
            <p style="font-size: 0.75rem; margin-top: 0; margin-bottom: 0.5rem;">
                Qui vengono accorpati i ticket per categoria, escludendo quelli collegati alle attività programmate.
            </p>
    
            <table style="width:100%; border: 1px solid #353131; border-collapse: collapse;">
    
                <thead>
                    <tr style="border: 1px solid #353131;">
                        <th style="border: 1px solid #353131;" class="text-small-plus  ">
                            Categoria
                        </th>
                        <th style="border: 1px solid #353131; width:9%;" class="text-small-plus  ">
                            Quantità
                        </th>
                        <th style="border: 1px solid #353131; width:25%;" class="text-small-plus  ">
                            Tempo totale di gestione (hh:mm)
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Qui si può migliorare decidendo se mostrare le operazioni strutturate, quelli collegati o entrambi (per ora esclude le operazioni strutturate)
                        // Allo stesso modo si può decidere di mostrare solo l'attività strutturata o i collegati (al momento solo l'attività strutturata viene mostrata)
                        $billableTicketsByCategory = collect($tickets)
                            ->filter(function ($ticket) {
                                return $ticket['is_billable'] && ($ticket['scheduling_id'] == null) && $ticket['is_master'] == false;
                            })
                            ->groupBy('category')
                            ->sortByDesc(function ($groupedTickets) {
                                return $groupedTickets->sum('actual_processing_time');
                            });
                    @endphp
    
                    @foreach ($billableTicketsByCategory as $category => $groupedTickets)
                        <tr style="border: 1px solid #353131;">
                            <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                                <p class="text-small-plus">
                                    {{ $category }}
                                </p>
                            </td>
                            <td style="border: 1px solid #353131; text-align: center;">
                                <p class="text-small-plus " style="font-weight: 600">
                                    {{ $groupedTickets->count() }}
                                </p>
                            </td>
                            <td style="border: 1px solid #353131; text-align: center;">
                                <p class="text-small-plus " style="font-weight: 600">
                                    {{ sprintf('%02d:%02d', intdiv($groupedTickets->sum('actual_processing_time'), 60), $groupedTickets->sum('actual_processing_time') % 60) }}
                                </p>
                            </td>
                        </tr>
                    @endforeach
    
                    <tr>
                        <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                            <p style="font-weight: 600">
                                Totale
                            </p>
                        </td>
                        <td style="border: 1px solid #353131; text-align: center;">
                            <p style="font-weight: 600">
                                {{ $total_billable_tickets_count }}
                            </p>
                        </td>
                        <td style="border: 1px solid #353131; text-align: center;">
                            <p style="font-weight: 600">
                                {{ sprintf('%02d:%02d', intdiv($total_billable_work_time, 60), $total_billable_work_time % 60) }}
                            </p>
                        </td>
                    </tr>
    
                </tbody>
    
            </table>
    
        </div>
    
        <div style="height: 1rem;"></div>
    
        {{-- <div class="card">
            <table width="100%" style="margin-top: 1rem;">
                <tr>
                    <td>
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_billable_time_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
    
                    <td>
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_unbillable_time_url)) }}"
                            style=" width:100%;height: auto;">
                    </td>
                </tr>
            </table>
        </div> --}}
    
        <div class="card">
    
            @php
                $billableTicketsOnsite = collect($tickets)->filter(function ($ticket) {
                    return $ticket['is_billable'] && ($ticket['is_master'] == null) && ($ticket['scheduling_id'] == null) && ($ticket['work_mode'] == 'on_site');
                });
            @endphp
    
            {{-- Tabella ticket fatturabili col dettaglio del tempo e tecnico (handler). esclusi gli slave --}}
            <p style="margin-bottom: 0.5rem; text-align: center;"><b>Ticket onsite fatturabili</b></p>
            <p style="font-size:9; margin-top: 0; margin-bottom: 0.5rem; text-align: center;">
                <span>Esclusi i collegati</span>
            </p>
            <table style="width:100%; border: 1px solid #353131; border-collapse: collapse;">
    
                <thead>
                    <tr style="border: 1px solid #353131;">
                        <th style="border: 1px solid #353131; width:20%;" class="text-small-plus  ">
                            Giorno (chiusura)
                        </th>
                        <th style="border: 1px solid #353131; width:62%;" class="text-small-plus  ">
                            Dettaglio tecnico
                        </th>
                        <th style="border: 1px solid #353131; width:9%;" class="text-small-plus  ">
                            Ore
                        </th>
                        <th style="border: 1px solid #353131; width:9%;" class="text-small-plus  ">
                            Ticket
                        </th>
                    </tr>
                </thead>
                <tbody>
    
                    @unless ($billableTicketsOnsite->isEmpty())
    
    
                        @foreach ($billableTicketsOnsite as $ticket)
                            <tr style="border: 1px solid #353131;">
                                <td style="border: 1px solid #353131; text-align: center;">
                                    <p class="text-small-plus">
                                        {{-- {{ $ticket['closed_at']->format('d/m/Y') }} --}}
                                        {{ \Carbon\Carbon::createFromFormat('d/m/Y H:i', $ticket['closed_at'])->format('d/m/Y') }}
                                    </p>
                                </td>
                                <td style="border: 1px solid #353131; padding-left: 0.5rem;">
                                    <p class="text-small-plus ">
                                        {{ $ticket['handler_full_name'] }}
                                    </p>
                                </td>
                                <td style="border: 1px solid #353131; text-align: center;">
                                    <p class="text-small-plus " style="font-weight: 600">
                                        {{ sprintf('%02d:%02d', intdiv($ticket['actual_processing_time'], 60), $ticket['actual_processing_time'] % 60) }}
                                    </p>
                                </td>
                                <td style="border: 1px solid #353131; text-align: center;">
                                    <p class="text-small-plus " style="font-weight: 600">
                                        <a href="#ticket-{{ $ticket['id'] }}">
                                            #{{ $ticket['id'] }}
                                        </a>
                                    </p>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr style="border: 1px solid #353131;">
                            <td colspan="4" style="border: 1px solid #353131; text-align: center;">
                                <p class="text-small-plus" style="font-weight: 600">
                                    Nessun ticket onsite fatturabile trovato.
                                </p>
                            </td>
                        </tr>
                    @endunless
    
                </tbody>
    
            </table>
        </div>
    </div>

    <div class="page-break"></div>

    <div>

        <div style="text-align:center;margin-top: 1rem;margin-bottom: 1rem;">
            <p>Periodo: {{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>
        </div>

        <!-- <div class="card">
            <h3 style="font-size:1.25rem; line-height: 1.25rem; margin: 0; text-align:center;">
                Qualità del servizio
            </h3>
        </div> -->

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
                                    {{-- Non è detto che i ticket siano tutti stati creati in questo periodo. Potrebbero essere antecedenti e rientrare in questa esportazione solo perchè erano ancora aperti a inizio periodo. --}}
                                    <p style="font-weight: 600">{{ count($tickets) }}</p>
                                    <p class="text-small">Ticket risolti nel periodo</p>
                                </td>
                                {{-- <td>
                                    <p style="font-weight: 600">{{ $closed_tickets_count }}</p>
                                    <p class="text-small">Ticket risolti nel periodo</p>
                                </td> --}}
                                {{-- Quelli in lavorazione vengono esclusi dai grafici. c'è uno spazio dedicato che mostra solo quanti sono. --}}
                                {{-- <td>
                                    <p style="font-weight: 600">{{ $other_tickets_count }}</p>
                                    <p class="text-small">Ticket in Lavorazione</p>
                                </td> --}}
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table width="100%">
                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        @php
                            $imgData = !empty($ticket_by_category_url) ? @file_get_contents($ticket_by_category_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif
                    </td>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        @php
                            $imgData = !empty($ticket_by_source_url) ? @file_get_contents($ticket_by_source_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        @php
                            $imgData = !empty($ticket_by_weekday_url) ? @file_get_contents($ticket_by_weekday_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif
                    </td>

                    @if ($dates_are_more_than_one_month_apart)
                        <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                            @php
                                $imgData = !empty($ticket_per_month_url) ? @file_get_contents($ticket_per_month_url) : false;
                            @endphp
                            @if ($imgData !== false)
                                <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                    style="width: 100%; height: auto;">
                            @else
                                <span>Immagine non disponibile</span>
                            @endif
                        </td>
                    @endif
                </tr>
            </table>
        </div>

        <div style="height: 1rem;"></div>

        <div class="card">
            <table width="100%" style="margin-top: 1rem;">
                <tr>
                    <td>
                        <table width="100%">
                            <tr>
                                <td>
                                    @php
                                        $imgData = !empty($ticket_by_priority_url) ? @file_get_contents($ticket_by_priority_url) : false;
                                    @endphp
                                    @if ($imgData !== false)
                                        <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                            style="width: 100%; height: auto;">
                                    @else
                                        <span>Immagine non disponibile</span>
                                    @endif
                                <td>
                            </tr>
                            <tr>
                                <td>
                                    @php
                                        $imgData = !empty($tickets_by_user_url) ? @file_get_contents($tickets_by_user_url) : false;
                                    @endphp
                                    @if ($imgData !== false)
                                        <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                            style="width: 100%; height: auto;">
                                    @else
                                        <span>Immagine non disponibile</span>
                                    @endif
                                <td>
                            </tr>
                        </table>
                    </td>

                    <td>
                        @php
                            $imgData = !empty($tickets_sla_url) ? @file_get_contents($tickets_sla_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
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
                                    <p class="text-small">Ticket inclusi (risolti) nel periodo</p>
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
                        @php
                            $imgData = !empty($ticket_by_category_incident_bar_url) ? @file_get_contents($ticket_by_category_incident_bar_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif
                    </td>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        @php
                            $imgData = !empty($ticket_by_category_request_bar_url) ? @file_get_contents($ticket_by_category_request_bar_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        @php
                            $imgData = !empty($ticket_by_type_incident_bar_url) ? @file_get_contents($ticket_by_type_incident_bar_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif
                    </td>


                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        @php
                            $imgData = !empty($ticket_by_type_request_bar_url) ? @file_get_contents($ticket_by_type_request_bar_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif

                    </td>

                </tr>

                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        @php
                            $imgData = !empty($wrong_type_url) ? @file_get_contents($wrong_type_url) : false;
                        @endphp
                        @if ($imgData !== false)
                            <img src="data:image/png;base64,{{ base64_encode($imgData) }}"
                                style="width: 100%; height: auto;">
                        @else
                            <span>Immagine non disponibile</span>
                        @endif
                    </td>
                </tr>

            </table>
        </div>

    </div>

    <div class="page-break"></div>

    <div>
        <h1 style="text-align: center;">Indice</h1>
        <p style="font-size:9">
            <span>R/I indica Request/Incident ovvero Richiesta/Problema.</span>
            <br>
            <span>O/C/S indica se è un'operazione strutturata, se collegato a un'operazione strutturata o se è singolo.</span>
            <br>
            <span>SOD/SRD/COL/SIN indica se è un'attività programmata (supporto on site dedicato, supporto remoto dedicato), se collegato a un'attività programmata o se è singolo.</span>
            <br>
            <span>SUP indica il Supporto.</span>
            <br>
            <span>RM/OS indica Remoto o Onsite</span>
            <br>
            <span>Lo stato attuale è in riferimento alla data {{ $date_to->format('d/m/Y') }} </span>
        </p>
        <hr>

        <table style="width:100%; border: 1px solid #201e1e; border-collapse: collapse;">
            <tbody>
                <tr style=" border: 1px solid #353131;" class="text-small-plus">
                    <th style="width:6%; border: 1px solid #353131;">
                        Ticket
                    </th>
                    <th style="width:5%; border: 1px solid #353131;">
                        R/I
                    </th>
                    <th style="width:25%; border: 1px solid #353131;">
                        Categoria
                    </th>
                    <th style="width:12%; border: 1px solid #353131;">
                        Apertura
                    </th>
                    <th style="width:6%; border: 1px solid #353131;">
                        RM/OS
                    </th>
                    <th style="width:6%; border: 1px solid #353131;">
                        Tempo
                    </th>
                    <th style="width:8%; border: 1px solid #353131;">
                        Fatturabile
                    </th>
                    <th style="width:8%; border: 1px solid #353131;">
                        Aperto da
                    </th>
                    <th style="width:8%; border: 1px solid #353131;">
                        O/C/S
                    </th>
                    <th style="width:8%; border: 1px solid #353131;">
                        SOD/SRD/COL/SIN
                    </th>
                    <th style="width:8%; border: 1px solid #353131;">
                        Stato attuale
                    </th>
                </tr>
                @foreach ($tickets as $ticket)
                    <tr class="text-small">
                        <td style="width:6%; border: 1px solid #353131; text-align: center;">
                            <a href="#ticket-{{ $ticket['id'] }}">
                                #{{ $ticket['id'] }}
                            </a>
                        </td>
                        <td style="width:5%; border: 1px solid #353131; text-align: center;">
                            {{ $ticket['incident_request'] == 'Request' ? 'R' : 'I' }}
                        </td>
                        <td style="width:25%; border: 1px solid #353131; padding-left: 0.5rem;">
                            {{-- {{ $ticket['category'] }} --}}
                            {{ $ticket['category'] }}
                        </td>
                        <td style="width:12%; border: 1px solid #353131; text-align: center;" class="text-small">
                            {{ $ticket['opened_at'] }}
                        </td>
                        <td style="width:6%; border: 1px solid #353131; text-align: center;">
                            {{ $ticket['work_mode'] == 'remote' ? 'RM' : ($ticket['work_mode'] == 'on_site' ? 'OS' : '') }}
                        </td>
                        <td style="width:6%; border: 1px solid #353131; text-align: center;">
                            {{ sprintf('%02d:%02d', intdiv($ticket['actual_processing_time'], 60), $ticket['actual_processing_time'] % 60) }}
                        </td>
                        <td style="width:8%; border: 1px solid #353131; text-align: center;">
                            {{ $ticket['is_billable'] ? 'Si' : 'No' }}
                        </td>
                        <td style="width:8%; border: 1px solid #353131; text-align: center;">
                            {{ $ticket['opened_by_initials'] }} {{ $ticketSources['on_site_technician'] == $ticket['source'] ? 'ONSITE' : '' }}
                        </td>
                        <td style="width:8%; border: 1px solid #353131; text-align: center;">
                            @if ($ticket['master_id'] != null)
                                C
                            @elseif ($ticket['is_master'])
                                O
                            @else
                                S
                            @endif
                        </td>
                        <td style="width:8%; border: 1px solid #353131; text-align: center;">
                            @if ($ticket['scheduling_id'] != null)
                                COL
                            @elseif ($ticket['is_scheduling'])
                                @if ($ticket['work_mode'] == 'on_site')
                                    SOD
                                @elseif ($ticket['work_mode'] == 'remote')
                                    SRD
                                @else
                                    SD
                                @endif
                            @else
                                SIN
                            @endif
                        </td>
                        <td style="width:8%; border: 1px solid #353131; text-align: center;">
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
                                <h2 class="main-header" style="font-size:1.75rem; line-height:1.75rem;">Ticket
                                    #{{ $ticket['id'] }}</h2>
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
                        <table style="width: 100%;">
                            <tr>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Data di apertura:</span>
                                        <span>{{ $ticket['opened_at'] }}</span>
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Stato al {{ $date_to->format('d/m/Y') }}:</span>
                                        <span>{{ $ticket['current_status'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                {{-- <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Ticket </span>
                                        <span>{{ $ticket['master_id'] != null
                                            ? // ? 'Collegato a <a href="#ticket-'.e($ticket['master_id']).'">#'.e($ticket['master_id']).'</a>'
                                                'Collegato a operazione strutturata'
                                            : ($ticket['is_master']
                                                ? 'Operazione strutturata'
                                                : 'Singolo') }}</span>
                                    </p>
                                </td> --}}
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Ticket </span>
                                        <span>{{ $ticket['scheduling_id'] != null
                                            ? // ? 'Collegato a <a href="#ticket-'.e($ticket['scheduling_id']).'">#'.e($ticket['scheduling_id']).'</a>'
                                                ($ticket['master_id'] != null
                                                        ? 'Collegato ad operazione strutturata e ad attività programmata'
                                                        : ($ticket['is_master']
                                                            ? 'Operazione strutturata collegata ad attività programmata'
                                                            : 'Collegato ad attività programmata')
                                                )
                                            : ($ticket['is_scheduling']
                                                ? 'Attività programmata'
                                                : ($ticket['master_id'] != null
                                                        ? 'Collegato a operazione strutturata'
                                                        : ($ticket['is_master']
                                                            ? 'Operazione strutturata'
                                                            : 'Singolo')
                                                )) }}</span>
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">
                                            {{ $ticket['is_master'] ? 'Tempo (somma dei collegati):' : 'Tempo:' }}
                                        </span>
                                        <span>{{ 
                                            $ticket['is_master'] 
                                                ? sprintf('%02d:%02d', intdiv($ticket['slaves_actual_processing_time_sum'], 60), $ticket['slaves_actual_processing_time_sum'] % 60) 
                                                : sprintf('%02d:%02d', intdiv($ticket['actual_processing_time'], 60), $ticket['actual_processing_time'] % 60)
                                        }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Gestione:</span>
                                        <span>{{ $ticket['work_mode'] == 'remote' ? 'In remoto' : ($ticket['work_mode'] == 'on_site' ? 'Onsite' : '') }}</span>
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Fatturabile:</span>
                                        <span>{{ $ticket['is_billable'] ? 'Si' : 'No' }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p><span class="ticket-section-title">Provenienza:</span>
                                        <span>{{ $ticket['source'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p><span class="ticket-section-title">Aperto da:</span>
                                        <span>{{ $ticket['opened_by'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p><span class="ticket-section-title">Categoria:</span> <span>{{ $ticket['category'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p><span class="ticket-section-title">Tipologia:</span> <span>{{ $ticket['type'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            @if ($ticket['master_id'] != null || $ticket['is_master'] || $ticket['scheduling_id'] != null || $ticket['is_scheduling'])
                                <tr>
                                    <td colspan="2">
                                        @if ($ticket['master_id'] != null)
                                            <p>
                                                <span class="ticket-section-title">Operazione strutturata: </span>
                                                <a href="#ticket-{{ $ticket['master_id'] }}">
                                                    #{{ $ticket['master_id'] }}
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

                                        @if ($ticket['scheduling_id'] != null)
                                            <p>
                                                <span class="ticket-section-title">Attività programmata: </span>
                                                <a href="#ticket-{{ $ticket['scheduling_id'] }}">
                                                    #{{ $ticket['scheduling_id'] }}
                                                </a>
                                            </p>
                                        @endif
                                        @if ($ticket['is_scheduling'])
                                            <p>
                                                <span class="ticket-section-title">Ticket collegati ad attività programmata: </span>
                                                @if (!empty($ticket['activities_ids']))
                                                    @foreach ($ticket['activities_ids'] as $activity_id)
                                                        <a href="#ticket-{{ $activity_id }}">
                                                            #{{ $activity_id }}
                                                        </a>
                                                        @if (!$loop->last)
                                                            ,
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <span>Non ci sono ticket collegati ad attività programmata</span>
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        </table>

                    </div>

                    <div class="ticket-webform-{{ strtolower($ticket['incident_request']) }}-section">
                        <p class="box-heading"><b>Dati webform</b></p>
                        @if (!is_null($ticket['webform_data']))
                            <table style="width:100%">

                                @php
                                    unset($ticket['webform_data']->description);

                                    // Ordina i campi: prima quelli normali, poi quelli hardware
                                    $webformFields = [];
                                    $hardwareFields = [];
                                    
                                    foreach ($ticket['webform_data'] as $key => $value) {
                                        if (in_array(strtolower($key), $ticket['hardwareFields'])) {
                                            $hardwareFields[$key] = $value;
                                        } else {
                                            $webformFields[$key] = $value;
                                        }
                                    }
                                    
                                    // Unisce prima i campi normali, poi quelli hardware
                                    $sortedWebformData = array_merge($webformFields, $hardwareFields);

                                    $closedTr = false;
                                @endphp

                                @foreach ($sortedWebformData as $key => $value)

                                    @if (in_array(strtolower($key), $ticket['hardwareFields']))

                                        {{-- 
                                            Se serve, chiude tr. Per capire se chiuderlo vede se l'iteration è > 1 e il resto di iteration/3 è diverso da 1, 
                                            che se è stato chiuso nel ciclo precedente dovrebbe essere 1
                                        --}}
                                        @if ($loop->iteration > 1 && 
                                                $loop->iteration % 3 != 1 && 
                                                !$closedTr
                                            )
                                            {{-- Aggiunge i td per occupare gli spazi vuoti --}}
                                            @if($loop->iteration % 3 == 2)
                                                <td></td><td></td>
                                            @elseif($loop->iteration % 3 == 0)
                                                <td></td>
                                            @endif  
                                            </tr>
                                        @endif
                                        {{-- In ogni caso cambia il valore di closedTr perchè solo il primo campo hardware deve inserire il </tr> se serve --}}
                                        @php
                                            $closedTr = true;
                                        @endphp

                                        {{-- Tabella hardware --}}
                                        <tr>
                                            <td colspan="3">
                                                <span><b>{{ $key }}</b></span>
                                                <table style="width: 100%; margin-top: 0.3rem; font-size: 0.6rem; border-collapse: collapse;">
                                                    <thead>
                                                        <tr>
                                                            <th style="border: 1px solid #ddd; padding: 0.25rem; text-align: left;">ID</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.25rem; text-align: left;">Marca</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.25rem; text-align: left;">Modello</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.25rem; text-align: left;">Seriale</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.25rem; text-align: left;">Asset</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.25rem; text-align: left;">Etichetta</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if (is_array($value))
                                                            @foreach ($value as $hardware)
                                                                <tr>
                                                                    <td style="border: 1px solid #ddd; padding: 0.25rem;">{{ $hardware['id'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.25rem;">{{ $hardware['make'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.25rem;">{{ $hardware['model'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.25rem;">{{ $hardware['serial_number'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.25rem;">{{ $hardware['company_asset_number'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.25rem;">{{ $hardware['support_label'] ?? '' }}</td>
                                                                </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    @else

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

                        <table style="width: 100%;">
                            <tr>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Data di apertura:</span>
                                        <span>{{ $ticket['opened_at'] }}</span>
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Stato al {{ $date_to->format('d/m/Y') }}:</span>
                                        <span>{{ $ticket['current_status'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Ticket </span>
                                        <span>{{ $ticket['master_id'] != null
                                            ? // ? 'Collegato a <a href="#ticket-'.e($ticket['master_id']).'">#'.e($ticket['master_id']).'</a>'
                                            'Collegato a operazione strutturata'
                                            : ($ticket['is_master']
                                                ? 'Operazione strutturata'
                                                : 'Singolo') }}</span>
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Tempo:</span>
                                        <span>{{ sprintf('%02d:%02d', intdiv($ticket['actual_processing_time'], 60), $ticket['actual_processing_time'] % 60) }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Gestione:</span>
                                        <span>{{ $ticket['work_mode'] == 'remote' ? 'In remoto' : ($ticket['work_mode'] == 'on_site' ? 'Onsite' : '') }}</span>
                                    </p>
                                </td>
                                <td style="width: 50%;">
                                    <p>
                                        <span class="ticket-section-title">Fatturabile:</span>
                                        <span>{{ $ticket['is_billable'] ? 'Si' : 'No' }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p><span class="ticket-section-title">Provenienza:</span>
                                        <span>{{ $ticket['source'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p><span class="ticket-section-title">Aperto da:</span>
                                        <span>{{ $ticket['opened_by'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p><span class="ticket-section-title">Categoria:</span> <span>{{ $ticket['category'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <p><span class="ticket-section-title">Tipologia:</span> <span>{{ $ticket['type'] }}</span>
                                    </p>
                                </td>
                            </tr>
                            @if ($ticket['master_id'] != null || $ticket['is_master'] || $ticket['scheduling_id'] != null || $ticket['is_scheduling'])
                                <tr>
                                    <td colspan="2">
                                        @if ($ticket['master_id'] != null)
                                            <p>
                                                <span class="ticket-section-title">Operazione strutturata: </span>
                                                <a href="#ticket-{{ $ticket['master_id'] }}">
                                                    #{{ $ticket['master_id'] }}
                                                </a>
                                            </p>
                                        @endif
                                        @if ($ticket['is_master'])
                                            <p>
                                                <span class="ticket-section-title">Ticket collegati: </span>
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

                                        @if ($ticket['scheduling_id'] != null)
                                            <p>
                                                <span class="ticket-section-title">Attività programmata: </span>
                                                <a href="#ticket-{{ $ticket['scheduling_id'] }}">
                                                    #{{ $ticket['scheduling_id'] }}
                                                </a>
                                            </p>
                                        @endif
                                        @if ($ticket['is_scheduling'])
                                            <p>
                                                <span class="ticket-section-title">Ticket collegati ad attività programmata: </span>
                                                @if (!empty($ticket['activities_ids']))
                                                    @foreach ($ticket['activities_ids'] as $activity_id)
                                                        <a href="#ticket-{{ $activity_id }}">
                                                            #{{ $activity_id }}
                                                        </a>
                                                        @if (!$loop->last)
                                                            ,
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <span>Non ci sono ticket collegati ad attività programmata</span>
                                                @endif
                                            </p>
                                        @endif

                                    </td>
                                </tr>
                            @endif
                        </table>

                    </div>

                    <div class="ticket-webform-{{ strtolower($ticket['incident_request']) }}-section">
                        <p class="box-heading"><b>Dati webform</b></p>
                        @if (!is_null($ticket['webform_data']))
                            <table style="width:100%">

                                @php
                                    unset($ticket['webform_data']->description);
                                    
                                    // Ordina i campi: prima quelli normali, poi quelli hardware
                                    $webformFields = [];
                                    $hardwareFields = [];
                                    
                                    foreach ($ticket['webform_data'] as $key => $value) {
                                        if (in_array(strtolower($key), $ticket['hardwareFields'])) {
                                            $hardwareFields[$key] = $value;
                                        } else {
                                            $webformFields[$key] = $value;
                                        }
                                    }
                                    
                                    // Unisce prima i campi normali, poi quelli hardware
                                    $sortedWebformData = array_merge($webformFields, $hardwareFields);

                                    $closedTr = false;
                                @endphp

                                @foreach ($sortedWebformData as $key => $value)
                                    
                                    @if (in_array(strtolower($key), $ticket['hardwareFields']))

                                        {{-- 
                                            Se serve, chiude tr. Per capire se chiuderlo vede se l'iteration è > 1 e il resto di iteration/3 è diverso da 1, 
                                            che se è stato chiuso nel ciclo precedente dovrebbe essere 1
                                        --}}
                                        @if ($loop->iteration > 1 && 
                                                $loop->iteration % 3 != 1 && 
                                                !$closedTr
                                            )
                                            {{-- Aggiunge i td per occupare gli spazi vuoti --}}
                                            @if($loop->iteration % 3 == 2)
                                                <td></td><td></td>
                                            @elseif($loop->iteration % 3 == 0)
                                                <td></td>
                                            @endif  
                                            </tr>
                                        @endif
                                        {{-- In ogni caso cambia il valore di closedTr perchè solo il primo campo hardware deve inserire il </tr> se serve --}}
                                        @php
                                            $closedTr = true;
                                        @endphp

                                        {{-- Tabella hardware --}}
                                        <tr>
                                            <td colspan="3">
                                                <span><b>{{ $key }}</b></span>
                                                <table style="width: 100%; margin-top: 0.3rem; font-size: 0.6rem; border-collapse: collapse;">
                                                    <thead>
                                                        <tr>
                                                            <th style="border: 1px solid #ddd; padding: 0.2rem; text-align: left;">ID</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.2rem; text-align: left;">Marca</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.2rem; text-align: left;">Modello</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.2rem; text-align: left;">Seriale</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.2rem; text-align: left;">Asset</th>
                                                            <th style="border: 1px solid #ddd; padding: 0.2rem; text-align: left;">Etichetta</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if (is_array($value))
                                                            @foreach ($value as $hardware)
                                                                <tr>
                                                                    <td style="border: 1px solid #ddd; padding: 0.2rem;">{{ $hardware['id'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.2rem;">{{ $hardware['make'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.2rem;">{{ $hardware['model'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.2rem;">{{ $hardware['serial_number'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.2rem;">{{ $hardware['company_asset_number'] ?? '' }}</td>
                                                                    <td style="border: 1px solid #ddd; padding: 0.2rem;">{{ $hardware['support_label'] ?? '' }}</td>
                                                                </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    @else    

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

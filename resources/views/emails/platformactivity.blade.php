@component('mail::message')
# Report orario - Ticket non gestiti e in gestione

## Problemi
@component('mail::table')
|{{ \App\Models\TenantTerm::getCurrentTenantTerm('azienda', 'Azienda') }}|Tipologia|Aperto il|Stato|Visita|
|:--|:--|:--|:--|--:|
@foreach ($tickets as $ticket)
    @if (! $ticket->ticketType->category->is_problem)
        @continue
    @endif
|<small>{{ $ticket->company->name }}</small>|<small>{{ $ticket->ticketType->name }}</small>|<small>{{ $ticket->created_at->setTimezone('Europe/Rome')->format('d/m/Y H:i') }}</small>|<small>{{ $stages[$ticket->stage_id]['name'] }}</small>|<small><a href="{{ config('app.frontend_url') }}/support/admin/ticket/{{ $ticket->id }}">Visita</a></small>|
@endforeach
@endcomponent

## Richieste

@component('mail::table')
|{{ \App\Models\TenantTerm::getCurrentTenantTerm('azienda', 'Azienda') }}|Tipologia|Aperto il|Stato|Visita|
|:--|:--|:--|:--|--:|
@foreach ($tickets as $ticket)
    @if (! $ticket->ticketType->category->is_request)
        @continue
    @endif
|<small>{{ $ticket->company->name }}</small>|<small>{{ $ticket->ticketType->name }}</small>|<small>{{ $ticket->created_at->setTimezone('Europe/Rome')->format('d/m/Y H:i') }}</small>|<small>{{ $stages[$ticket->stage_id]['name'] }}</small>|<small><a href="{{ config('app.frontend_url') }}/support/admin/ticket/{{ $ticket->id }}">Visita</a></small>|
@endforeach
@endcomponent
@endcomponent

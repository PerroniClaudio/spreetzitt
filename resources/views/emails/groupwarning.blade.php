@component('mail::message')

@if($type == 'auto-assign')
{{-- Mail assegnazione automatica --}}
## Avviso {{ $category->is_problem ? 'Incident' : 'Request' }} {{ $ticket->id }}

{{ $update->content }}

{{ $category->is_problem ? 'Incident' : 'Request' }} n° {{ $ticket->id }} <br>
{{ \App\Models\TenantTerm::getCurrentTenantTerm('azienda', 'Azienda') }}: {{ $company->name }} <br>
Categoria: {{ $category->name }} <br>
Tipo: {{ $ticketType->name }} <br><br>
Stato:
@component('mail::status', ['status' => $ticket->status, 'stages' => $stages])
@endcomponent

<br>
@if($link)
@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent
@endif

@else
{{-- Altro tipo di mail di avviso --}}
Non specificato.
@endif

@endcomponent
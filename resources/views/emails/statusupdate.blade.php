@component('mail::message', ['previewText' => $previewText])
<h2> Update {{ $category->is_problem ? "Incident" : "Request" }} - Stato: 
@component('mail::statusnew', ['status' => $ticket->stage_id, 'stages' => $stages])
@endcomponent
</h2>

@if ($isAutomatic)
Update automatico.
@else
L'utente {{ $user->name }} ha fatto un update.
@endif

{{ $category->is_problem ? "Incident" : "Request" }} n° {{ $ticket->id }} <br>
{{ \App\Models\TenantTerm::getCurrentTenantTerm('azienda', 'Azienda') }}: {{ $company->name }} <br>
Categoria: {{ $category->name }} <br>
Tipo di ticket: {{ $ticketType->name }} <br>
Responsabilità del dato: {{ $ticket->is_user_error ? 'Cliente' : 'Supporto' }} <br><br>
Update: <br>
{{ $update->content }} <br><br>


<br>
@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent
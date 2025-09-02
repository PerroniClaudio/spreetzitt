@component('mail::message', ['brand_url' => $brand_url, 'previewText' => $previewText])
## Apertura {{ $category->is_problem ? 'Incident' : 'Request' }}

@if(in_array($mailType, ["user", "referer", "referer_it"]))
<p style="font-size: 11px; line-height: 12px;">
  <span style="font-size: 14px;"><b>Si prega di non rispondere a questa email.</b></span> Per comunicare col supporto in merito a questo ticket, 
  accedere al portale tramite il bottone sottostante ed utilizzare l'apposita sezione di messaggistica 
  nella pagina di dettaglio del ticket. In alternativa all'utilizzo del bottone sottostante si può 
  accedere al portale e selezionare dalla lista il ticket desiderato o crearne uno nuovo. <br>
  Se il proprio account non è ancora attivo si devono seguire le indicazioni contenute nell'email di attivazione ricevuta. <br>
  Si ricorda che in caso di password dimenticata, si può recuperare utilizzando il tasto apposito 
  nella schermata di login ed indicando l'indirizzo email del proprio account (solitamente il proprio indirizzo email aziendale).
</p>
@endif

@if($mailType == "user") 
Di seguito la sintesi del ticket che hai aperto. <br><br>
@elseif($mailType == "referer")
Sei stato indicato come utente interessato {{ $category->is_problem ? 'nel seguente Incident' : 'nella seguente Request' }}. <br><br>
@elseif($mailType == "referer_it")
Sei stato indicato come {{ strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'referente IT')) }} per {{ $category->is_problem ? 'il seguente Incident' : 'la seguente Request' }}. <br><br>
@endif

{{ $category->is_problem ? 'Incident' : 'Request' }} n° {{ $ticket->id }} <br>
@if($mailType == "admin") 
{{ \App\Models\TenantTerm::getCurrentTenantTerm('azienda', 'Azienda') }}: {{ $company->name }} <br>
Utente: {{ $user->is_admin ? 'Supporto' : $user->name . ' ' . $user->surname ?? '' }} <br>
@endif
Categoria: {{ $category->name }} <br>
Tipo: {{ $ticketType->name }} <br><br>
Messaggio: <br>{{ $ticket->description }} <br><br>

@if(isset($form))
  {!! $form !!}
@endif

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent
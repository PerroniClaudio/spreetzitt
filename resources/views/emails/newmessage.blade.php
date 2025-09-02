@component('mail::message', ['url' => $url, 'brand_url' => $brand_url, 'previewText' => $previewText])
@if($mailType != "admin" && $mailType != "support")
## Nuovo messaggio {{ $sender->is_admin ? "dal Supporto" : "dall'utente: " . $sender->name . ' ' . ($sender->surname ?? '') }}
@else
## Nuovo messaggio {{ $sender->is_admin ? "al cliente " . $company->name : "dal cliente " . $company->name . ' - ' . $sender->name . ' ' . ($sender->surname ?? '') }}
@endif

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

@if($mailType == "referer")
Buongiorno, <br><br>
Questa mail ti è stata inviata perchè sei indicato come utente interessato nel relativo ticket. <br><br>
@elseif($mailType == "referer_it")
Buongiorno, <br><br>
Questa mail ti è stata inviata perchè sei il {{ strtolower(\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'referente IT')) }} per il relativo ticket. <br><br>
@endif
{{ $category->is_problem ? 'Incident' : 'Request' }} n° {{ $ticket->id }} - {{ $ticketType->name }}<br>
@if($sender->is_admin)
@if($mailType == "admin" || $mailType == "support")
Aperto da: {{$opener->name . ' ' . ($opener->surname ?? '')}}<br>
{{ \App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'Referente IT') }}: {{$refererIT ? ($refererIT->name . ' ' . ($refererIT->surname ?? '')) : 'Nessuno'}}<br>
Utente interessato: {{$referer ? ($referer->name . ' ' . ($referer->surname ?? '')) : 'Nessuno'}}<br>
@endif
@endif
Inviato da: {{$sender->is_admin ? "Supporto" : ($company->name . ', ' . $sender->name . ' ' . $sender->surname ?? '')}}<br>
Testo del messaggio: <br>
{{ $message }}

@component('mail::button', ['url' => $link])
Vai al ticket
@endcomponent

@endcomponent

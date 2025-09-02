@component('mail::message')
## Creazione utenza

<p style="font-size: 11px; line-height: 12px;">
  <span style="font-size: 14px;"><b>Si prega di non rispondere a questa email.</b></span> <br>
  Si ricorda che, una volta attivato l'account, in caso di password dimenticata, si può recuperare utilizzando il tasto apposito 
  nella schermata di login ed indicando l'indirizzo email del proprio account (solitamente il proprio indirizzo email aziendale).
</p>

Buongiorno {{ $user->name }},<br>
le comunichiamo la creazione della sua utenza sul portale di supporto iFortech.

Può impostare la sua password al seguente link.

@component('mail::button', ['url' => $url])
Imposta password
@endcomponent

@endcomponent


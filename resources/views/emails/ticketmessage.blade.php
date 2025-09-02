@component('mail::message')
# Nuovo messaggio per il ticket #{{ $ticket->id }}

@component('mail::panel')
## {{ $sender->name }}
{{ $ticketMessage->message }}
@endcomponent

@component('mail::button', ['url' => "https://google.com"])
Visualizza ticket
@endcomponent
@endcomponent

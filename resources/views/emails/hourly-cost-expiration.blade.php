<x-mail::message>
# ⚠️ Avviso Scadenza Costi Orari

Ciao,

questo è un promemoria automatico per informarti che alcuni **tipi di ticket** dell'azienda **{{ $company->name }}** hanno costi orari scaduti o in scadenza nel prossimo mese.

## 📋 Dettaglio Tipi di Ticket Scaduti o in Scadenza

<x-mail::table>
| ID | Categoria | Nome Tipo | Costo Orario | Data Scadenza | Azione |
|:---|:----------|:----------|:-------------|:--------------|:-------|
@foreach($expiringTicketTypes as $ticketType)
| {{ $ticketType->id }} | {{ $ticketType->category->name ?? 'N/D' }} | {{ $ticketType->name }} | €{{ number_format($ticketType->hourly_cost, 2, ',', '.') }} | {{ \Carbon\Carbon::parse($ticketType->hourly_cost_expires_at)->format('d/m/Y') }}{{ \Carbon\Carbon::parse($ticketType->hourly_cost_expires_at)->isPast() ? ' ⚠️' : '' }} | [Modifica]({{ $frontendUrl }}/support/admin/ticket-types?typeId={{ $ticketType->id }}) |
@endforeach
</x-mail::table>

## 🎯 Cosa Fare

Per ogni tipo di ticket in scadenza, puoi:

1. **Aggiornare il costo orario** se necessario
2. **Estendere la data di scadenza** per il prossimo periodo
3. **Rimuovere la data di scadenza** se non più necessaria

<x-mail::button :url="$frontendUrl . '/support/admin/ticket-types'" color="primary">
Gestisci Tipi di Ticket
</x-mail::button>

## 📊 Riepilogo

- **Azienda**: {{ $company->name }}
- **Tipi di ticket scaduti/in scadenza**: {{ $expiringTicketTypes->count() }}
- **Già scaduti**: {{ $expiringTicketTypes->where('hourly_cost_expires_at', '<', now()->format('Y-m-d'))->count() }}
- **Scadenze più vicine**: {{ $expiringTicketTypes->where('hourly_cost_expires_at', '<=', now()->addWeeks(2)->format('Y-m-d'))->where('hourly_cost_expires_at', '>=', now()->format('Y-m-d'))->count() }} entro 2 settimane

## 📈 Statistiche Tipi Ticket Senza Data Scadenza

- **Senza prezzo (€0 o NULL)**: {{ $nullExpirationZeroPrice }} tipi di ticket
- **Con prezzo (>€0)**: {{ $nullExpirationWithPrice }} tipi di ticket

@if($nullExpirationWithPrice > 0)
⚠️ **Attenzione**: Ci sono {{ $nullExpirationWithPrice }} tipi di ticket con costo orario ma senza data di scadenza impostata.
@endif

---

*Questa email è stata generata automaticamente dal sistema di gestione ticket.*  
*Per qualsiasi domanda, contatta il team di supporto.*

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>

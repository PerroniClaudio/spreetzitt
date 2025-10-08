<x-mail::message>
# Report Disponibile

Un nuovo report è stato generato per la tua azienda.

**Nome File:** {{ $report->file_name }}

Puoi scaricare il report dal pannello di controllo.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>

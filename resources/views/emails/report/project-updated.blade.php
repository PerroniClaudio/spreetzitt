<x-mail::message>
# Il report #{{ $report->id }} del progetto #{{ $report->project->id }} {{ $report->project->project_name }} è ora disponibile (o è stato aggiornato).

Puoi visualizzare e scaricare il report direttamente dal sito, oppure scaricare l'allegato a questa email. 

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>

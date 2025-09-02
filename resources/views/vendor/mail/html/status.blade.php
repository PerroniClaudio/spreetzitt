
@props([
    'status',
    'stages'
])
<span class="status-label status-{{ $status }}-box">
<span class="status-{{ $status }}-circle">●</span>
{{ $stages[$status] }}
</span>
@props([
    'status' => '0'
])
<div class="status-label status-{{ $status }}-box">
<span class="status-{{ $status }}-circle">●</span>
{{ $slot }}
</div>
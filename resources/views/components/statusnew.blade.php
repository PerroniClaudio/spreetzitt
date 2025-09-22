@props([
    'status' => '0',
    'stages' => [],
    'use_admin_color' => true
])
@php
    $colorKey = $use_admin_color ? 'admin_color' : 'user_color';
    $color = $stages[$status][$colorKey] ?? '#d1d5db';
@endphp
<div class="status-label" style="border-color: {{ $color }}">
<span style="color: {{ $color }}">●</span>
{{ $slot }}
</div>
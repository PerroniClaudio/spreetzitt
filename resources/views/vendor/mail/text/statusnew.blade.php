@props([
    'status' => '0',
    'stages' => []
])
{{ $stages[$status]['name'] ?? 'Sconosciuto' }}

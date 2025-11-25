<?php

return [
    'tickets' => [
        'allowed_tenants' => [
            'spreetzit',
            'domustart',
        ],
        'exclusive_features' => [
            'show_visibility_fields' => [
                'domustart',
            ],
        ],
    ],
    'hardware' => [
        'allowed_tenants' => [
            'spreetzit',
        ],
    ],
    'software' => [
        'allowed_tenants' => [
            'spreetzit',
        ],
    ],
    'properties' => [
        'allowed_tenants' => [
            'domustart',
        ],
    ],
    'documents' => [
        'allowed_tenants' => [
            'domustart',
        ],
        'excluded_tenants' => [
            'spreetzit',
        ],
    ],
];

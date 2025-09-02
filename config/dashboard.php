<?php

return [
    'domustart' => [
        'leftCards' => [
            [
                'id' => 'condomini-registrati',
                'type' => 'companies-count',
                'color' => 'primary',
                'content' => 'Condomini registrati'
            ],
            [
                'id' => 'utenti-registrati',
                'type' => 'users-count',
                'color' => 'secondary',
                'content' => 'Utenti registrati'
            ]
        ],
        'rightCards' => [
            [
                'id' => 'casi-aperti',
                'type' => 'open-tickets',
                'color' => 'primary',
                'content' => 'Casi aperti'
            ],
            [
                'id' => 'ticket-redirect',
                'type' => 'tickets-redirect',
                'color' => 'secondary',
                'content' => 'Vai ai ticket'
            ]
        ]
    ],
    'spreetzit' => [
        'leftCards' => [
            [
                'id' => 'ultimi-articoli-dpo',
                'type' => 'latest-dpo-articles',
                'color' => 'primary',
                'content' => 'Ultimi articoli in DPO del comune'
            ],
            [
                'id' => 'articoli-integys',
                'type' => 'integys-articles',
                'color' => 'secondary',
                'content' => 'Ultimi Articoli in Integys'
            ],
            [
                'id' => 'ticket-frequenti',
                'type' => 'frequent-tickets',
                'color' => 'primary',
                'content' => 'Ticket più frequenti'
            ]
        ],
        'rightCards' => [
            [
                'id' => 'accesso-rapido-report',
                'type' => 'quick-access-reports',
                'color' => 'secondary',
                'content' => 'Accesso rapido report'
            ],
            [
                'id' => 'news-vendor',
                'type' => 'vendor-news',
                'color' => 'primary',
                'content' => 'News riguardanti vendor diversi'
            ],
            [
                'id' => 'ultime-funzioni-utilizzate',
                'type' => 'recent-functions',
                'color' => 'secondary',
                'content' => 'Ultime funzioni utilizzate'
            ]
        ]
    ],
    // Aggiungi qui altri tenant con le rispettive configurazioni
];

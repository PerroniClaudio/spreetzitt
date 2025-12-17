<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Access Levels Configuration
    |--------------------------------------------------------------------------
    |
    | Definisce i livelli di accesso utilizzati nel sistema.
    | Valori più bassi = maggiori privilegi.
    | Questi valori possono essere modificati senza richiedere migrazioni DB.
    |
    */

    'access_levels' => [
        'superadmin' => 1,      // Massimo livello - accesso completo
        'admin' => 2,           // Amministratore di sistema
        'company_admin' => 3,   // Amministratore aziendale
        'user' => 4,            // Utente standard
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Level Labels
    |--------------------------------------------------------------------------
    |
    | Label leggibili per i livelli di accesso, utilizzabili nelle UI.
    |
    */

    'access_level_labels' => [
        'superadmin' => 'Superadmin',
        'admin' => 'Admin',
        'company_admin' => 'Amministratore Azienda',
        'user' => 'Utente',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Access Level
    |--------------------------------------------------------------------------
    |
    | Livello di accesso predefinito quando non specificato.
    |
    */

    'default_access_level' => 'superadmin',
];

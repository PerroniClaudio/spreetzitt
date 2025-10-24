<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firecrawl API configuration
    |--------------------------------------------------------------------------
    |
    | Inserisci la tua API key in .env come FIRECRAWL_API_KEY. Per scraping
    | di pagine autenticati (ad es. LinkedIn) puoi inserire il cookie di
    | sessione (es. "li_at=...") in FIRECRAWL_AUTH_COOKIE oppure usare
    | questa chiave per impostarlo via config.
    |
    */

    'api_key' => env('FIRECRAWL_API_KEY', null),

    // Valore del Cookie da inviare, ad esempio "li_at=..."
    'auth_cookie' => env('FIRECRAWL_AUTH_COOKIE', null),
];

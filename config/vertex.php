<?php

return [
    'project_id' => env('VERTEX_PROJECT_ID'),
    'location' => env('VERTEX_LOCATION', 'europe-west8'),
    'key_file_path' => env('VERTEX_KEY_FILE_PATH', 'keys/vertex-ai-service-key.json'),

    // Service Account configuration via environment variables
    'service_account' => [
        'type' => env('VERTEX_SA_TYPE', 'service_account'),
        'project_id' => env('VERTEX_SA_PROJECT_ID'),
        'private_key_id' => env('VERTEX_SA_PRIVATE_KEY_ID'),
        'private_key' => env('VERTEX_SA_PRIVATE_KEY'),
        'client_email' => env('VERTEX_SA_CLIENT_EMAIL'),
        'client_id' => env('VERTEX_SA_CLIENT_ID'),
        'auth_uri' => env('VERTEX_SA_AUTH_URI', 'https://accounts.google.com/o/oauth2/auth'),
        'token_uri' => env('VERTEX_SA_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
        'auth_provider_x509_cert_url' => env('VERTEX_SA_AUTH_PROVIDER_CERT_URL', 'https://www.googleapis.com/oauth2/v1/certs'),
        'client_x509_cert_url' => env('VERTEX_SA_CLIENT_CERT_URL'),
        'universe_domain' => env('VERTEX_SA_UNIVERSE_DOMAIN', 'googleapis.com'),
    ],
];

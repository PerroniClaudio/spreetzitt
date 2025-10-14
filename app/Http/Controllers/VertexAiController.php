<?php

namespace App\Http\Controllers;

use App\Models\VertexAiQueryLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VertexAiController extends Controller
{
    private $projectId;

    private $location;

    private $accessToken;

    private $lastAiResponse = null;

    public function __construct()
    {
        $this->projectId = config('vertex.project_id');
        $this->location = config('vertex.location');

        if (!$this->projectId || !$this->location) {
            throw new Exception("Configurazione Vertex AI mancante. Controlla VERTEX_PROJECT_ID e VERTEX_LOCATION.");
        }

        $this->accessToken = $this->getAccessToken();
    }

    private function getAccessToken(): string
    {
        Log::info('Tentativo di ottenere access token Vertex AI', [
            'project_id' => $this->projectId,
            'location' => $this->location
        ]);
        
        try {
            $serviceAccount = $this->getServiceAccountConfig();
            
            if (!$serviceAccount) {
                throw new Exception('Configurazione service account non trovata');
            }

            // Crea JWT per l'autenticazione
            $now = time();
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $jwt = $this->createJWT($payload, $serviceAccount['private_key']);
            Log::info('JWT creato con successo per Vertex AI');

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            Log::info('Risposta Google OAuth per Vertex AI', [
                'status' => $response->status(),
                'successful' => $response->successful()
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                Log::error('Errore OAuth response per Vertex AI', [
                    'body' => $errorBody, 
                    'status' => $response->status()
                ]);
                throw new Exception("Errore nell'ottenere l'access token: " . $errorBody);
            }

            $responseData = $response->json();
            if (!isset($responseData['access_token'])) {
                throw new Exception('Access token non presente nella risposta OAuth');
            }

            Log::info('Access token Vertex AI ottenuto con successo');
            return $responseData['access_token'];
            
        } catch (Exception $e) {
            Log::error('Errore nell\'ottenere access token Vertex AI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new Exception('Impossibile ottenere access token Vertex AI: ' . $e->getMessage());
        }
    }

    private function getServiceAccountConfig(): ?array
    {
        // Configurazione tramite singole variabili d'ambiente
        $serviceAccount = config('vertex.service_account');
        
        // Verifica che i campi essenziali siano presenti
        if ($serviceAccount['client_email'] && $serviceAccount['private_key']) {
            Log::info('Service account caricato da variabili d\'ambiente');
            return $serviceAccount;
        }

        // Fallback su file (per ambiente di sviluppo locale)
        $keyFilePath = config('vertex.key_file_path');
        if ($keyFilePath) {
            $fullPath = str_starts_with($keyFilePath, '/') ? $keyFilePath : base_path($keyFilePath);
            
            if (file_exists($fullPath) && is_readable($fullPath)) {
                $serviceAccount = json_decode(file_get_contents($fullPath), true);
                if ($serviceAccount && isset($serviceAccount['client_email'], $serviceAccount['private_key'])) {
                    Log::info('Service account caricato da file', ['file' => $fullPath]);
                    return $serviceAccount;
                }
            }
        }

        Log::error('Nessuna configurazione service account valida trovata');
        return null;
    }

    private function createJWT(array $payload, string $privateKey): string
    {
        // Header
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $headerEncoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');

        // Payload
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        // Signature
        $signature = '';
        openssl_sign($headerEncoded.'.'.$payloadEncoded, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $headerEncoded.'.'.$payloadEncoded.'.'.$signatureEncoded;
    }

    private function generatePromptFromHtml(string $htmlPulito): string
    {
        return '
            Sei un assistente esperto nell\'analisi di file HTML grezzi ottenuti tramite web scraping. Il tuo compito è estrarre informazioni relative alle notizie da un file HTML di struttura variabile.

            Analizza il contenuto del file e identifica i seguenti dati:
            - title: il titolo della notizia.
            - url: il link alla notizia, generalmente trovato in tag <a>.
            - description: una breve descrizione o sommario della notizia.
            - published_at: la data di pubblicazione della notizia.

            Considera che l\'HTML potrebbe non avere una struttura uniforme, quindi utilizza pattern comuni e tecniche di analisi adattive per identificare i dati richiesti. Se necessario, sfrutta tag come <title>, <meta>, <a>, <h1>, <h2>, <p> e altri elementi HTML che potrebbero contenere queste informazioni.

            Restituisci esclusivamente i dati estratti in formato JSON, con una lista di record strutturati come segue:
            [
                {
                    "title": "Titolo della notizia",
                    "url": "URL della notizia",
                    "description": "Descrizione della notizia",
                    "published_at": "Data di pubblicazione"
                }
            ]
            Non racchiudere il JSON in blocchi di codice (come ```json o ```) e non aggiungere testo extra: restituisci solo il JSON puro.

            Se non riesci a trovare uno o più dati, lascia il campo vuoto ma includilo comunque nel JSON. Assicurati che i risultati siano accurati e ben formattati.

            I dati HTML da analizzare sono i seguenti:
        '."{$htmlPulito}";
    }

    private function excuteRequest(string $prompt, string $modelName = 'gemini-2.5-flash-lite'): string
    {

        $url = "https://{$this->location}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->location}/publishers/google/models/{$modelName}:generateContent";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 10000,
                'topP' => 0.8,
                'topK' => 40,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
            'Content-Type' => 'application/json',
        ])->timeout(500)->post($url, $payload);

        if (! $response->successful()) {
            throw new Exception('Errore chiamata Gemini: '.$response->body());
        }

        $data = $response->json();

        if (! isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Risposta Gemini non valida: '.json_encode($data));
        }

        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    public function extractNewsFromHtml($html)
    {

        $prompt = $this->generatePromptFromHtml($html);

        try {
            $responseText = $this->excuteRequest($prompt);

            return ['result' => $responseText];
        } catch (Exception $e) {
            Log::error('Errore Vertex AI: '.$e->getMessage());

            return response()->json(['error' => 'Errore durante l\'estrazione delle notizie.'], 500);
        }
    }

    public function generateReportFromPrompt(Request $request)
    {
        $startTime = microtime(true);
        $user = $request->user();
        $userPrompt = '';
        $logId = null;

        try {
            // Validazione base del prompt
            $request->validate([
                'prompt' => 'required|string|max:1000',
            ]);

            $userPrompt = $request->input('prompt');

            // Crea log iniziale
            $logData = [
                'user_id' => $user ? $user->id : null,
                'user_email' => $user ? $user->email : null,
                'user_prompt' => $userPrompt,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'was_successful' => false,
            ];

            $logEntry = VertexAiQueryLog::create($logData);
            $logId = $logEntry->id;

            // Controlli anti-prompt injection
            if ($this->isPromptSuspicious($userPrompt)) {
                $this->updateLogEntry($logId, [
                    'error_message' => 'Prompt rifiutato: contenuto potenzialmente pericoloso',
                    'execution_time' => microtime(true) - $startTime,
                ]);

                return response()->json(['error' => 'Prompt non valido o potenzialmente pericoloso.'], 400);
            }

            // Genera query SQL dal prompt
            $sqlQuery = $this->generateSqlFromPrompt($userPrompt);

            if (! $sqlQuery) {
                $this->updateLogEntry($logId, [
                    'error_message' => 'Impossibile generare query SQL dal prompt fornito',
                    'execution_time' => microtime(true) - $startTime,
                ]);

                return response()->json([
                    'error' => 'Non riesco a interpretare la richiesta con i dati disponibili.',
                    'suggestion' => 'Prova a essere più specifico. Tabelle principali: users, companies, tickets, hardware, documents, properties, news, groups, offices',
                    'examples' => [
                        'Mostra tutti gli utenti della mia azienda',
                        'Ticket aperti con priorità alta', 
                        'Hardware assegnato agli utenti',
                        'Documenti caricati nell\'ultimo mese',
                        'Statistiche ticket per stato',
                        'Proprietà immobiliari per azienda',
                        'Ultime notizie pubblicate'
                    ]
                ], 400);
            }

            // Aggiorna log con la query generata
            $this->updateLogEntry($logId, [
                'generated_sql' => $sqlQuery,
                'ai_response' => $this->lastAiResponse,
            ]);

            // Valida e esegue la query
            $results = $this->executeSecureQuery($sqlQuery);

            if (empty($results)) {
                $this->updateLogEntry($logId, [
                    'result_count' => 0,
                    'error_message' => 'Nessun dato trovato per la query',
                    'execution_time' => microtime(true) - $startTime,
                ]);

                return response()->json(['error' => 'Nessun dato trovato per la richiesta.'], 404);
            }

            // Genera CSV
            $csvData = $this->generateCsvFromResults($results);

            // Aggiorna log con successo
            $this->updateLogEntry($logId, [
                'result_count' => count($results),
                'was_successful' => true,
                'execution_time' => microtime(true) - $startTime,
            ]);

            // Log aggiuntivo per sicurezza
            Log::info('Vertex AI Query eseguita con successo', [
                'user_id' => $user ? $user->id : null,
                'user_email' => $user ? $user->email : 'guest',
                'prompt' => $userPrompt,
                'sql_query' => $sqlQuery,
                'result_count' => count($results),
                'execution_time' => microtime(true) - $startTime,
                'ip_address' => $request->ip(),
            ]);

            // Restituisce il CSV come download
            return response($csvData)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="report_'.date('Y-m-d_H-i-s').'.csv"');

        } catch (Exception $e) {
            $errorMessage = 'Errore durante la generazione del report: '.$e->getMessage();

            // Aggiorna log con errore se esiste
            if ($logId) {
                $this->updateLogEntry($logId, [
                    'error_message' => $e->getMessage(),
                    'execution_time' => microtime(true) - $startTime,
                ]);
            }

            // Log di errore dettagliato
            Log::error('Errore Vertex AI Query', [
                'user_id' => $user ? $user->id : null,
                'user_email' => $user ? $user->email : 'guest',
                'prompt' => $userPrompt,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time' => microtime(true) - $startTime,
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['error' => 'Errore durante la generazione del report.'], 500);
        }
    }

    private function updateLogEntry(int $logId, array $data): void
    {
        try {
            VertexAiQueryLog::where('id', $logId)->update($data);
        } catch (Exception $e) {
            Log::error('Errore aggiornamento log Vertex AI: '.$e->getMessage());
        }
    }

    private function isPromptSuspicious(string $prompt): bool
    {
        // Lista di parole/frasi pericolose per prompt injection (inglese e italiano)
        $dangerousPatterns = [
            // SQL Injection patterns EN
            'drop table', 'delete from', 'truncate', 'alter table', 'create table',
            'insert into', 'update set', 'grant', 'revoke', 'union select',
            // SQL Injection patterns IT
            'elimina tabella', 'cancella tabella', 'trunca', 'modifica tabella', 'crea tabella',
            'inserisci in', 'aggiorna set', 'concedi', 'revoca', 'unisci select',
            // Prompt injection patterns EN
            'ignore previous', 'forget previous', 'new instructions', 'system prompt',
            'override', 'admin mode', 'bypass security', 'execute code',
            // Prompt injection patterns IT
            'ignora istruzioni precedenti', 'dimentica istruzioni precedenti', 'nuove istruzioni', 'prompt di sistema',
            'sovrascrivi', 'modalità admin', 'bypassa sicurezza', 'esegui codice',
            // System commands EN
            'exec(', 'eval(', 'system(', 'shell_exec', 'passthru',
            // System commands IT
            'esegui(', 'valuta(', 'sistema(', 'shell_exec', 'passa_attraverso',
        ];

        $lowerPrompt = strtolower($prompt);

        foreach ($dangerousPatterns as $pattern) {
            if (strpos($lowerPrompt, $pattern) !== false) {
                return true;
            }
        }

        // Controlla caratteri sospetti consecutivi
        if (preg_match('/[\'";]{3,}/', $prompt)) {
            return true;
        }

        return false;
    }

    private function generateSqlFromPrompt(string $userPrompt): ?string
    {
        $allowedSchema = $this->buildEnhancedDatabaseSchema();
        $systemPrompt = $this->generateSqlSystemPrompt($allowedSchema, $userPrompt);

        try {
            $response = $this->excuteRequest($systemPrompt);

            // Salva la risposta dell'AI nel log se disponibile
            $this->lastAiResponse = $response;

            Log::debug('Risposta AI grezza', ['response' => $response]);

            // Estrae solo la query SQL dalla risposta
            $sqlQuery = $this->extractSqlFromResponse($response);

            return $sqlQuery;
        } catch (Exception $e) {
            Log::error('Errore generazione SQL: '.$e->getMessage());

            return null;
        }
    }

    private function buildEnhancedDatabaseSchema(): array
    {
        return [
            // === CORE TABLES ===
            'users' => [
                // Info di base (SAFE - no password, token, etc.)
                'id', 'name', 'surname', 'email', 'phone', 'city', 'zip_code', 'address',
                'is_admin', 'is_company_admin', 'is_deleted', 'company_id', 'created_at', 'updated_at'
            ],
            'companies' => [
                'id', 'name', 'sla', 'note', 'created_at', 'updated_at',
                // SLA info
                'sla_take_low', 'sla_take_medium', 'sla_take_high', 'sla_take_critical',
                'sla_solve_low', 'sla_solve_medium', 'sla_solve_high', 'sla_solve_critical',
                // Data owner info
                'data_owner_name', 'data_owner_surname', 'data_owner_email'
            ],

            // === TICKET SYSTEM ===
            'tickets' => [
                'id', 'user_id', 'company_id', 'status', 'stage_id', 'description', 'priority',
                'due_date', 'created_at', 'updated_at', 'type_id', 'admin_user_id', 'group_id',
                'assigned', 'sla_take', 'sla_solve', 'is_user_error', 'actual_processing_time',
                'is_billable', 'source', 'is_rejected', 'parent_ticket_id'
            ],
            'ticket_types' => [
                'id', 'name', 'ticket_type_category_id', 'company_id', 'default_priority',
                'default_sla_solve', 'default_sla_take', 'is_deleted', 'description',
                'expected_processing_time', 'expected_is_billable', 'created_at', 'updated_at'
            ],
            'ticket_type_categories' => [
                'id', 'name', 'is_problem', 'is_request', 'is_deleted', 'created_at', 'updated_at'
            ],
            'ticket_stages' => [
                'id', 'name', 'description', 'admin_color', 'user_color', 'order',
                'is_sla_pause', 'is_system', 'system_key', 'created_at', 'updated_at'
            ],
            'ticket_messages' => [
                'id', 'ticket_id', 'user_id', 'message', 'created_at', 'updated_at'
            ],
            'ticket_files' => [
                'id', 'ticket_id', 'filename', 'extension', 'mime_type', 'size',
                'is_deleted', 'created_at', 'updated_at'
            ],
            'ticket_status_updates' => [
                'id', 'ticket_id', 'user_id', 'content', 'old_stage_id', 'new_stage_id',
                'type', 'show_to_user', 'created_at', 'updated_at'
            ],


            // === HARDWARE MANAGEMENT ===
            'hardware' => [
                'id', 'make', 'model', 'serial_number', 'company_asset_number', 'support_label',
                'purchase_date', 'company_id', 'hardware_type_id', 'ownership_type',
                'status', 'position', 'is_exclusive_use', 'created_at', 'updated_at'
            ],
            'hardware_types' => [
                'id', 'name', 'created_at', 'updated_at'
            ],
            'hardware_user' => [
                'id', 'hardware_id', 'user_id', 'created_by', 'responsible_user_id',
                'created_at', 'updated_at'
            ],

            // === GROUPS & ORGANIZATION ===
            'groups' => [
                'id', 'name', 'parent_id', 'email', 'created_at', 'updated_at'
            ],
            'custom_user_groups' => [
                'id', 'name', 'company_id', 'created_by', 'created_at', 'updated_at'
            ],

            // === DOCUMENTS & FILES ===
            'documents' => [
                'id', 'name', 'uploaded_name', 'type', 'mime_type', 'company_id',
                'uploaded_by', 'file_size', 'created_at', 'updated_at'
            ],

            // === PROPERTIES ===
            'properties' => [
                'id', 'section', 'sheet', 'parcel', 'users_number', 'energy_class',
                'square_meters', 'thousandths', 'activity_type', 'in_use_by',
                'company_id', 'created_at', 'updated_at'
            ],

            // === SUPPLIER & BRANDS ===
            'suppliers' => [
                'id', 'name', 'logo_url', 'created_at', 'updated_at'
            ],
            'brands' => [
                'id', 'name', 'logo_url', 'description', 'supplier_id', 'created_at', 'updated_at'
            ],

            // === NEWS SYSTEM ===
            'news' => [
                'id', 'news_source_id', 'title', 'url', 'description', 'published_at',
                'created_at', 'updated_at'
            ],
            'news_sources' => [
                'id', 'display_name', 'slug', 'type', 'url', 'description',
                'created_at', 'updated_at'
            ],

            // === OFFICES ===
            'offices' => [
                'id', 'name', 'address', 'number', 'zip_code', 'city', 'province',
                'is_legal', 'is_operative', 'company_id', 'created_at', 'updated_at'
            ],

            // === STATS & REPORTING ===
            'ticket_stats' => [
                'id', 'incident_open', 'incident_in_progress', 'incident_waiting', 'incident_out_of_sla',
                'request_open', 'request_in_progress', 'request_waiting', 'request_out_of_sla',
                'created_at', 'updated_at'
            ],

            // === AUDIT & LOGS ===
            'vertex_ai_query_logs' => [
                'id', 'user_id', 'user_email', 'user_prompt', 'result_count',
                'was_successful', 'execution_time', 'created_at', 'updated_at'
            ],
            'failed_login_attempts' => [
                'id', 'email', 'user_id', 'ip_address', 'attempt_type',
                'created_at', 'updated_at'
            ],
        ];
    }

    private function generateSqlSystemPrompt(array $schema, string $userPrompt): string
    {
        $schemaDescription = "Database schema disponibile:\n";
        foreach ($schema as $table => $columns) {
            $schemaDescription .= "- $table: ".implode(', ', $columns)."\n";
        }

        return "Sei un esperto SQL analyst. Il tuo compito è interpretare le richieste degli utenti e generare query SQL SELECT utili e sicure.

            SCHEMA DATABASE:
            $schemaDescription

            REGOLE OBBLIGATORIE:
            1. Genera SOLO query SELECT - mai INSERT, UPDATE, DELETE, DROP, ALTER
            2. Usa SOLO le tabelle e colonne dello schema sopra
            3. Aggiungi sempre LIMIT (max 1000 righe)
            4. Restituisci SOLO la query SQL, senza commenti o spiegazioni
            5. SICUREZZA: Non includere mai colonne sensibili (password, token, secret_key, etc.)

            APPROCCIO INTELLIGENTE:
            - Se la richiesta è vaga, interpreta nel modo più utile possibile
            - Se mancano dettagli specifici, fai assunzioni ragionevoli
            - Cerca di soddisfare l'intento dell'utente anche se la formulazione non è perfetta
            - Usa JOIN tra tabelle quando ha senso per fornire dati più completi
            - Aggiungi filtri comuni come date recenti o stati attivi quando appropriato
            - Solo se la richiesta è completamente impossibile da realizzare con lo schema disponibile, rispondi 'IMPOSSIBLE'

            ESEMPI DI INTERPRETAZIONE:
            - \"utenti\" → SELECT id, name, email, company_id FROM users LIMIT 1000
            - \"ticket aperti\" → SELECT * FROM tickets WHERE status IN ('open', 'new') LIMIT 1000
            - \"statistiche ticket\" → SELECT status, COUNT(*) as count FROM tickets GROUP BY status LIMIT 1000
            - \"hardware aziendale\" → SELECT h.*, ht.name as type_name FROM hardware h JOIN hardware_types ht ON h.hardware_type_id = ht.id LIMIT 1000
            - \"documenti recenti\" → SELECT * FROM documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 1000
            - \"proprietà immobiliari\" → SELECT * FROM properties WHERE company_id IS NOT NULL LIMIT 1000
            - \"notizie recenti\" → SELECT n.*, ns.display_name as source FROM news n JOIN news_sources ns ON n.news_source_id = ns.id ORDER BY published_at DESC LIMIT 1000

            Richiesta utente: $userPrompt

            Query SQL:";
    }

    private function extractSqlFromResponse(string $response): ?string
    {
        // Log della risposta per debug
        Log::debug('Risposta AI ricevuta', ['response' => $response]);
        
        // Rimuove eventuali wrapper di codice
        $response = trim($response);
        $response = preg_replace('/^```sql\s*\n?/i', '', $response);
        $response = preg_replace('/^```\s*\n?/i', '', $response);
        $response = preg_replace('/\n?\s*```$/i', '', $response);
        $response = trim($response);

        // Verifica se la risposta è "IMPOSSIBLE"
        if (strtoupper($response) === 'IMPOSSIBLE') {
            Log::warning('AI ha risposto IMPOSSIBLE per il prompt');
            return null;
        }

        // Verifica che sia una query SELECT valida (più flessibile con whitespace e newline)
        if (! preg_match('/^\s*SELECT\s+.*?\s+FROM\s+/ims', $response)) {
            Log::warning('Risposta AI non contiene query SQL valida', ['response' => $response]);
            return null;
        }

        $sqlQuery = $response;

        // Rimuove eventuali query multiple (solo la prima)
        $queries = preg_split('/;\s*(?=SELECT|$)/i', $sqlQuery);
        $sqlQuery = trim($queries[0]);

        // Aggiunge LIMIT se non presente
        if (!preg_match('/\bLIMIT\s+\d+/i', $sqlQuery)) {
            $sqlQuery .= ' LIMIT 1000';
        }

        Log::debug('Query SQL estratta con successo', ['query' => $sqlQuery]);
        return $sqlQuery;
    }

    private function executeSecureQuery(string $sqlQuery): array
    {
        // Doppio controllo che sia solo SELECT
        if (! preg_match('/^\s*SELECT\s+/i', $sqlQuery)) {
            throw new Exception('Solo query SELECT sono permesse');
        }

        // Verifica che non contenga operazioni pericolose
        $dangerousOperations = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'REPLACE'];
        foreach ($dangerousOperations as $op) {
            if (preg_match('/\b'.$op.'\s+/i', $sqlQuery)) {
                throw new Exception("Operazione $op non permessa");
            }
        }

        // Verifica che non contenga colonne sensibili
        $sensitiveColumns = [
            'password', 'password_hash', 'encrypted_password', 'pwd',
            'access_token', 'refresh_token', 'api_token', 'remember_token', 'token',
            'microsoft_token', 'oauth_token', 'google_token', 'facebook_token',
            'secret_key', 'private_key', 'secret', 'key',
            '2fa_secret', 'backup_codes', 'two_factor', 'otp',
            'client_secret', 'auth_secret'
        ];
        
        foreach ($sensitiveColumns as $column) {
            if (preg_match('/\b'.$column.'\b/i', $sqlQuery)) {
                throw new Exception("Colonna sensibile '$column' non permessa nelle query");
            }
        }

        try {
            // Esegue la query con timeout
            $results = DB::select($sqlQuery);

            // Converte in array associativo
            return array_map(function ($row) {
                return (array) $row;
            }, $results);

        } catch (Exception $e) {
            Log::error('Errore esecuzione query: '.$e->getMessage().' - Query: '.$sqlQuery);
            throw new Exception("Errore nell'esecuzione della query");
        }
    }

    private function generateCsvFromResults(array $results): string
    {
        if (empty($results)) {
            return '';
        }

        $csv = '';

        // Header CSV
        $headers = array_keys($results[0]);
        $csv .= implode(',', array_map(function ($header) {
            return '"'.str_replace('"', '""', $header).'"';
        }, $headers))."\n";

        // Righe dati
        foreach ($results as $row) {
            $csvRow = array_map(function ($value) {
                // Gestisce valori null e converte in stringa
                if ($value === null) {
                    return '""';
                }

                return '"'.str_replace('"', '""', (string) $value).'"';
            }, $row);

            $csv .= implode(',', $csvRow)."\n";
        }

        return $csv;
    }
}

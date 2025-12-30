<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePdfProjectReport;
use App\Jobs\GeneratePdfReport;
use App\Models\Company;
use App\Models\ProjectReportPdfExport;
use App\Models\Ticket;
use App\Models\TicketReportPdfExport;
use App\Models\VertexAiQueryLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        if (! $this->projectId || ! $this->location) {
            throw new Exception('Configurazione Vertex AI mancante. Controlla VERTEX_PROJECT_ID e VERTEX_LOCATION.');
        }

        $this->accessToken = $this->getAccessToken();
    }

    private function getAccessToken(): string
    {
        Log::info('Tentativo di ottenere access token Vertex AI', [
            'project_id' => $this->projectId,
            'location' => $this->location,
        ]);

        try {
            $serviceAccount = $this->getServiceAccountConfig();

            if (! $serviceAccount) {
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
                'successful' => $response->successful(),
            ]);

            if (! $response->successful()) {
                $errorBody = $response->body();
                Log::error('Errore OAuth response per Vertex AI', [
                    'body' => $errorBody,
                    'status' => $response->status(),
                ]);
                throw new Exception("Errore nell'ottenere l'access token: ".$errorBody);
            }

            $responseData = $response->json();
            if (! isset($responseData['access_token'])) {
                throw new Exception('Access token non presente nella risposta OAuth');
            }

            Log::info('Access token Vertex AI ottenuto con successo');

            return $responseData['access_token'];

        } catch (Exception $e) {
            Log::error('Errore nell\'ottenere access token Vertex AI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('Impossibile ottenere access token Vertex AI: '.$e->getMessage());
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

    private function excuteRequest(string $prompt, string $modelName = 'gemini-2.5-pro'): string
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
                'maxOutputTokens' => 60000,
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
            Log::info('NEWS - Starting Gemini request for news extraction');
            $responseText = $this->excuteRequest($prompt);

            return ['result' => $responseText];
        } catch (Exception $e) {
            Log::error('NEWS - Errore Vertex AI: '.$e->getMessage());

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
                        'Ultime notizie pubblicate',
                    ],
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

    // $isPdfGeneration significa che si sta generando un report PDF normale o per progetto. Quindi la query dov solo isolare i ticket. non usare campi a piacere o selezionare tabelle inutili alla crazione del PDF. 
    private function generateSqlFromPrompt(string $userPrompt, string $pdfGenerationType = 'csv'): ?string
    {
        $allowedSchema = $this->buildEnhancedDatabaseSchema();
        $systemPrompt = $this->generateSqlSystemPrompt($allowedSchema, $userPrompt, $pdfGenerationType);

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

    /**
     * Get explicit foreign key relationships for AI understanding
     */
    private function getDatabaseRelations(): array
    {
        return [
            // USERS
            'users.company_id' => 'companies.id',
            
            // TICKETS (core relations)
            'tickets.user_id' => 'users.id',
            'tickets.company_id' => 'companies.id',
            'tickets.admin_user_id' => 'users.id',
            'tickets.stage_id' => 'ticket_stages.id',
            'tickets.type_id' => 'ticket_types.id',
            'tickets.group_id' => 'groups.id',
            'tickets.ticket_cause_id' => 'ticket_causes.id',
            
            // TICKETS (self-referencing)
            'tickets.project_id' => 'tickets.id (progetto padre)',
            'tickets.master_id' => 'tickets.id (ticket master)',
            'tickets.scheduling_id' => 'tickets.id (scheduling padre)',
            'tickets.parent_ticket_id' => 'tickets.id (ticket padre)',
            
            // TICKET TYPES
            'ticket_types.category_id' => 'ticket_type_categories.id',
            
            // TICKET MESSAGES
            'ticket_messages.ticket_id' => 'tickets.id',
            'ticket_messages.user_id' => 'users.id',
            'ticket_messages.admin_user_id' => 'users.id',
            
            // TICKET FILES
            'ticket_files.ticket_id' => 'tickets.id',
            'ticket_files.user_id' => 'users.id',
            
            // TICKET STATUS UPDATES
            'ticket_status_updates.ticket_id' => 'tickets.id',
            'ticket_status_updates.stage_id' => 'ticket_stages.id',
            'ticket_status_updates.user_id' => 'users.id',
            
            // TICKET REPORT PDF EXPORTS
            'ticket_report_pdf_exports.company_id' => 'companies.id',
            'ticket_report_pdf_exports.user_id' => 'users.id',
            
            // HARDWARE SYSTEM
            'hardware.company_id' => 'companies.id',
            'hardware.supplier_id' => 'suppliers.id',
            'hardware.brand_id' => 'brands.id',
            'hardware.hardware_category_id' => 'hardware_categories.id',
            'hardware.hardware_type_id' => 'hardware_types.id',
            'hardware.operating_system_id' => 'operating_systems.id',
            
            'hardware_assignations.hardware_id' => 'hardware.id',
            'hardware_assignations.user_id' => 'users.id',
            'hardware_assignations.company_id' => 'companies.id',
            
            'hardware_logs.hardware_id' => 'hardware.id',
            'hardware_logs.user_id' => 'users.id',
            
            // SOFTWARE SYSTEM
            'software.company_id' => 'companies.id',
            'software.supplier_id' => 'suppliers.id',
            'software.software_category_id' => 'software_categories.id',
            
            'software_assignations.software_id' => 'software.id',
            'software_assignations.user_id' => 'users.id',
            'software_assignations.company_id' => 'companies.id',
            
            'software_logs.software_id' => 'software.id',
            'software_logs.user_id' => 'users.id',
            
            // GROUPS
            'groups.company_id' => 'companies.id',
            
            // DOCUMENTS
            'documents.company_id' => 'companies.id',
            
            // PROPERTIES
            'properties.company_id' => 'companies.id',
            'properties.property_type_id' => 'property_types.id',
            
            'property_logs.property_id' => 'properties.id',
            'property_logs.user_id' => 'users.id',
            
            // NEWS
            'news.author_id' => 'users.id',
            
            // OFFICES
            'offices.company_id' => 'companies.id',
            
            // STATS
            'stats_monthly_priority.company_id' => 'companies.id',
            'stats_monthly_company.company_id' => 'companies.id',
            'stats_monthly_ticket_types.ticket_type_id' => 'ticket_types.id',
            'stats_monthly_ticket_types.company_id' => 'companies.id',
            'stats_yearly_priority.company_id' => 'companies.id',
            'stats_yearly_company.company_id' => 'companies.id',
            'stats_yearly_ticket_types.ticket_type_id' => 'ticket_types.id',
            'stats_yearly_ticket_types.company_id' => 'companies.id',
        ];
    }

    private function buildEnhancedDatabaseSchema(): array
    {
        return [
            // === CORE TABLES ===
            'users' => [
                // Info di base (SAFE - no password, token, etc.)
                'id', 'name', 'surname', 'email', 'phone', 'city', 'zip_code', 'address',
                'is_admin', 'is_company_admin', 'is_deleted', 'company_id', 'created_at', 'updated_at',
            ],
            'companies' => [
                'id', 'name', 'sla', 'note', 'created_at', 'updated_at',
                // SLA info
                'sla_take_low', 'sla_take_medium', 'sla_take_high', 'sla_take_critical',
                'sla_solve_low', 'sla_solve_medium', 'sla_solve_high', 'sla_solve_critical',
                // Data owner info
                'data_owner_name', 'data_owner_surname', 'data_owner_email',
            ],

            // === TICKET SYSTEM ===
            'tickets' => [
                'id', 'user_id', 'company_id', 'status', 'stage_id', 'description', 'priority',
                'due_date', 'created_at', 'updated_at', 'type_id', 'admin_user_id', 'group_id',
                'assigned', 'sla_take', 'sla_solve', 'is_user_error', 'actual_processing_time',
                'is_billable', 'source', 'is_rejected', 'parent_ticket_id', 'ticket_cause_id',
                'project_id', 'master_id', 'scheduling_id', 'scheduled_duration', 'work_mode',
            ],
            'ticket_types' => [
                'id', 'name', 'ticket_type_category_id', 'company_id', 'default_priority',
                'default_sla_solve', 'default_sla_take', 'is_deleted', 'description',
                'expected_processing_time', 'expected_is_billable', 'is_project', 'is_master',
                'is_scheduling', 'created_at', 'updated_at',
            ],
            'ticket_type_categories' => [
                'id', 'name', 'is_problem', 'is_request', 'is_deleted', 'created_at', 'updated_at',
            ],
            'ticket_stages' => [
                'id', 'name', 'description', 'admin_color', 'user_color', 'order',
                'is_sla_pause', 'is_system', 'system_key', 'created_at', 'updated_at',
            ],
            'ticket_messages' => [
                'id', 'ticket_id', 'user_id', 'message', 'created_at', 'updated_at',
            ],
            'ticket_files' => [
                'id', 'ticket_id', 'filename', 'extension', 'mime_type', 'size',
                'is_deleted', 'created_at', 'updated_at',
            ],
            'ticket_status_updates' => [
                'id', 'ticket_id', 'user_id', 'content', 'old_stage_id', 'new_stage_id',
                'type', 'show_to_user', 'created_at', 'updated_at',
            ],
            'ticket_causes' => [
                'id', 'name', 'created_at', 'updated_at', 'deleted_at',
            ],
            'ticket_report_pdf_exports' => [
                'id', 'file_name', 'file_path', 'start_date', 'end_date', 'company_id',
                'is_generated', 'is_user_generated', 'is_failed', 'error_message', 'user_id',
                'send_email', 'is_ai_generated', 'ai_query', 'ai_prompt',
                'created_at', 'updated_at',
            ],

            // === HARDWARE MANAGEMENT ===
            'hardware' => [
                'id', 'make', 'model', 'serial_number', 'company_asset_number', 'support_label',
                'purchase_date', 'company_id', 'hardware_type_id', 'ownership_type',
                'status', 'position', 'is_exclusive_use', 'is_accessory', 'created_at', 'updated_at',
                'deleted_at',
            ],
            'hardware_types' => [
                'id', 'name', 'created_at', 'updated_at',
            ],
            'hardware_user' => [
                'id', 'hardware_id', 'user_id', 'created_by', 'responsible_user_id',
                'created_at', 'updated_at',
            ],
            'hardware_attachments' => [
                'id', 'hardware_id', 'file_path', 'original_filename', 'display_name',
                'file_extension', 'mime_type', 'file_size', 'uploaded_by',
                'created_at', 'updated_at', 'deleted_at',
            ],
            'hardware_audit_log' => [
                'id', 'hardware_id', 'modified_by', 'old_data', 'new_data', 'log_subject',
                'log_type', 'created_at', 'updated_at',
            ],
            'hardware_ticket' => [
                'id', 'hardware_id', 'ticket_id', 'created_at', 'updated_at',
            ],

            // === SOFTWARE MANAGEMENT ===
            'software' => [
                'id', 'vendor', 'product_name', 'version', 'activation_key', 'company_asset_number',
                'is_exclusive_use', 'license_type', 'max_installations', 'purchase_date',
                'expiration_date', 'support_expiration_date', 'status', 'company_id',
                'software_type_id', 'created_at', 'updated_at', 'deleted_at',
            ],
            'software_types' => [
                'id', 'name', 'created_at', 'updated_at',
            ],
            'software_user' => [
                'id', 'software_id', 'user_id', 'created_by', 'responsible_user_id',
                'created_at', 'updated_at',
            ],
            'software_attachments' => [
                'id', 'software_id', 'file_path', 'original_filename', 'display_name',
                'file_extension', 'mime_type', 'file_size', 'uploaded_by',
                'created_at', 'updated_at', 'deleted_at',
            ],
            'software_audit_log' => [
                'id', 'software_id', 'modified_by', 'old_data', 'new_data', 'log_subject',
                'log_type', 'created_at', 'updated_at',
            ],
            'software_ticket' => [
                'id', 'software_id', 'ticket_id', 'created_at', 'updated_at',
            ],

            // === GROUPS & ORGANIZATION ===
            'groups' => [
                'id', 'name', 'parent_id', 'email', 'created_at', 'updated_at',
            ],
            'custom_user_groups' => [
                'id', 'name', 'company_id', 'created_by', 'created_at', 'updated_at',
            ],

            // === DOCUMENTS & FILES ===
            'documents' => [
                'id', 'name', 'uploaded_name', 'type', 'mime_type', 'company_id',
                'uploaded_by', 'file_size', 'created_at', 'updated_at',
            ],

            // === PROPERTIES ===
            'properties' => [
                'id', 'section', 'sheet', 'parcel', 'users_number', 'energy_class',
                'square_meters', 'thousandths', 'activity_type', 'in_use_by',
                'company_id', 'created_at', 'updated_at',
            ],

            // === SUPPLIER & BRANDS ===
            'suppliers' => [
                'id', 'name', 'logo_url', 'created_at', 'updated_at',
            ],
            'brands' => [
                'id', 'name', 'logo_url', 'description', 'supplier_id', 'created_at', 'updated_at',
            ],

            // === NEWS SYSTEM ===
            'news' => [
                'id', 'news_source_id', 'title', 'url', 'description', 'published_at',
                'created_at', 'updated_at',
            ],
            'news_sources' => [
                'id', 'display_name', 'slug', 'type', 'url', 'description',
                'created_at', 'updated_at',
            ],

            // === OFFICES ===
            'offices' => [
                'id', 'name', 'address', 'number', 'zip_code', 'city', 'province',
                'is_legal', 'is_operative', 'company_id', 'created_at', 'updated_at',
            ],

            // === STATS & REPORTING ===
            'ticket_stats' => [
                'id', 'incident_open', 'incident_in_progress', 'incident_waiting', 'incident_out_of_sla',
                'request_open', 'request_in_progress', 'request_waiting', 'request_out_of_sla',
                'created_at', 'updated_at',
            ],
            'project_report_pdf_exports' => [
                'id', 'file_name', 'file_path', 'start_date', 'end_date', 'company_id',
                'is_generated', 'is_failed', 'error_message', 'user_id', 'send_email',
                'created_at', 'updated_at',
            ],

            // === AUDIT & LOGS ===
            'vertex_ai_query_logs' => [
                'id', 'user_id', 'user_email', 'user_prompt', 'generated_sql', 'ai_response',
                'result_count', 'was_successful', 'execution_time', 'ip_address', 'user_agent',
                'error_message', 'created_at', 'updated_at',
            ],
            'failed_login_attempts' => [
                'id', 'email', 'user_id', 'ip_address', 'attempt_type',
                'created_at', 'updated_at',
            ],
            'users_logs' => [
                'id', 'modified_by', 'user_id', 'old_data', 'new_data', 'log_subject',
                'log_type', 'created_at', 'updated_at',
            ],
            'tickets_logs' => [
                'id', 'modified_by', 'old_data', 'new_data', 'log_subject', 'log_type',
                'created_at', 'updated_at',
            ],
        ];
    }

    // $isPdfGeneration significa che si sta generando un report PDF normale o per progetto. Quindi la query dov solo isolare i ticket. non usare campi a piacere o selezionare tabelle inutili alla crazione del PDF.
    private function generateSqlSystemPrompt(array $schema, string $userPrompt, string $pdfGenerationType = 'csv'): string
    {
        $relations = $this->getDatabaseRelations();

        $schemaDescription = "Database schema disponibile:\n";
        foreach ($schema as $table => $columns) {
            $schemaDescription .= "- $table: ".implode(', ', $columns)."\n";
        }

        // Build relations description
        $relationsDescription = "\nRELAZIONI TRA TABELLE (per JOIN corretti):\n";
        foreach ($relations as $foreignKey => $reference) {
            $relationsDescription .= "- $foreignKey → $reference\n";
        }

        $extraRules = '';
        if($pdfGenerationType == 'project_pdf'){
            $extraRules = "6. Poiché questa query è per generare un report PDF di progetto, assicurati che la query si concentri SOLO sui ticket relativi al progetto specifico (progetto piuù quelli a lui collegati tramite project_id) e che recuperi tutti i campi, senza isolarne nessuno. Non includere dati non necessari da altre tabelle.";
        }
        if($pdfGenerationType == 'normal_pdf'){
            $extraRules = "6. Poiché questa query è per generare un report PDF standard, assicurati che la query si concentri SOLO sui ticket richiesti e che recuperi tutti i campi, senza isolarne nessuno. Non includere dati non necessari da altre tabelle. escludi i ticket di tipo progetto o collegati a un progetto trattraverso project_id.";
        }

        return "Sei un esperto SQL analyst. Il tuo compito è interpretare le richieste degli utenti e generare query SQL SELECT utili e sicure.

            SCHEMA DATABASE:
            $schemaDescription
            $relationsDescription

            REGOLE OBBLIGATORIE:
            1. Genera SOLO query SELECT - mai INSERT, UPDATE, DELETE, DROP, ALTER
            2. Usa SOLO le tabelle e colonne dello schema sopra
            3. Aggiungi sempre LIMIT (max 1000 righe)
            4. Restituisci SOLO la query SQL, senza commenti o spiegazioni
            5. SICUREZZA: Non includere mai colonne sensibili (password, token, secret_key, etc.)
            $extraRules

            APPROCCIO INTELLIGENTE:
            - Se la richiesta è vaga, interpreta nel modo più utile possibile
            - Se mancano dettagli specifici, fai assunzioni ragionevoli
            - Cerca di soddisfare l'intento dell'utente anche se la formulazione non è perfetta
            - Usa JOIN tra tabelle quando ha senso per fornire dati più completi
            - Aggiungi filtri comuni come date recenti o stati attivi quando appropriato
            - Solo se la richiesta è completamente impossibile da realizzare con lo schema disponibile, rispondi 'IMPOSSIBLE'

            INFORMAZIONI UTILI:
            - lo stato dei ticket si trova cercando nella colonna 'stage_id' della tabella 'tickets', che fa riferimento alla tabella 'ticket_stages'
            - le tipologie di ticket speciali (progetti, operazioni strutturate, attività programmate) si trovano nella tabella 'ticket_types', collegata tramite 'type_id' nella tabella 'tickets', e sono identificate dalle colonne 'is_project', 'is_master' e 'is_scheduling'
            - i collegamenti dei ticket a progetti, operazioni strutturate e attività programmate si trovano rispettivamente nelle colonne 'project_id', 'master_id' e 'scheduling_id' della tabella 'tickets'

            ESEMPI DI INTERPRETAZIONE:
            - \"utenti\" → SELECT id, name, email, company_id FROM users LIMIT 1000
            - \"ticket aperti\" → SELECT * FROM tickets WHERE stage_id in (SELECT ticket_stages.id from ticket_stages WHERE system_key = 'closed') LIMIT 1000
            - \"ticket in pausa\" → SELECT * FROM tickets WHERE stage_id in (SELECT ticket_stages.id from ticket_stages WHERE is_sla_pause = 1) LIMIT 1000
            - \"progetti\" → SELECT * FROM tickets JOIN ticket_types ON tickets.type_id = ticket_types.id WHERE ticket_types.is_project = 1 LIMIT 1000
            - \"attività programmate\" → SELECT * FROM tickets JOIN ticket_types ON tickets.type_id = ticket_types.id WHERE ticket_types.is_scheduling = 1 LIMIT 1000
            - \"operazioni strutturate\" → SELECT * FROM tickets JOIN ticket_types ON tickets.type_id = ticket_types.id WHERE ticket_types.is_master = 1 LIMIT 1000
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
        if (! preg_match('/\bLIMIT\s+\d+/i', $sqlQuery)) {
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
            'client_secret', 'auth_secret',
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

    /**
     * Genera un report PDF da un prompt AI.
     * Crea un record in TicketReportPdfExport con la query generata dall'AI,
     * poi il job GeneratePdfReport lo processerà.
     */
    public function generatePdfReportFromPrompt(Request $request)
    {
        $startTime = microtime(true);
        $user = $request->user();
        $userPrompt = '';
        $logId = null;

        try {
            // Solo admin possono generare report PDF
            if (!$user || $user->is_admin != 1) {
                return response()->json(['error' => 'Unauthorized. Solo gli admin possono generare report PDF.'], 401);
            }

            // Validazione - solo il prompt è richiesto
            $validated = $request->validate([
                'prompt' => 'required|string|max:1000',
            ]);

            $userPrompt = $validated['prompt'];

            // Crea log iniziale
            $logData = [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_prompt' => $userPrompt,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'was_successful' => false,
            ];

            // Dato che questa funzione serve a generare un report specifico, qui si potrebbe inserire del testo iniziale per specificare il contesto, 
            // es. come deve usare le date nella query (da - a, non devono essere created_at del ticket e basta, ma created_at del ticket deve essere 
            // inferiore alla data di fine e la data di inizio si deve usare per escludere i ticket che sono stati chiusi prima di quella data). 
            // Poi nel caso gli si può passare anche la query utilizzata dalla funzione normale come riferimento.

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
            $sqlQuery = $this->generateSqlFromPrompt($userPrompt, 'normal_pdf');

            if (!$sqlQuery) {
                $this->updateLogEntry($logId, [
                    'error_message' => 'Impossibile generare query SQL dal prompt fornito',
                    'execution_time' => microtime(true) - $startTime,
                ]);

                return response()->json([
                    'error' => 'Non riesco a interpretare la richiesta per generare un report PDF.',
                    'suggestion' => 'Prova a essere più specifico sui ticket che vuoi nel report.',
                    'examples' => [
                        'Ticket chiusi nell\'ultimo mese',
                        'Ticket aperti con priorità alta',
                        'Tutti i ticket di tipo "Richiesta" chiusi quest\'anno',
                        'Ticket fatturabili non ancora fatturati per l\'azienda Acme',
                    ],
                ], 400);
            }

            // Aggiorna log con la query generata
            $this->updateLogEntry($logId, [
                'generated_sql' => $sqlQuery,
                'ai_response' => $this->lastAiResponse,
            ]);

            // Valida la query (senza eseguirla per ora)
            $this->validateSqlQuery($sqlQuery);

            // Estrae company_id dalla query se presente in modo univoco
            $companyId = $this->extractCompanyIdFromQuery($sqlQuery);

            // Estrae le date dal prompt (OBBLIGATORIE)
            $dates = $this->extractDatesFromPrompt($userPrompt, $sqlQuery);
            
            if (!$dates['start_date'] || !$dates['end_date']) {
                $this->updateLogEntry($logId, [
                    'error_message' => 'Impossibile estrarre le date dal prompt. Le date sono obbligatorie.',
                    'execution_time' => microtime(true) - $startTime,
                ]);

                return response()->json([
                    'error' => 'Non riesco a identificare il periodo temporale richiesto.',
                    'suggestion' => 'Per favore specifica un periodo nel prompt.',
                    'examples' => [
                        'Ticket chiusi dal 01/01/2024 al 31/12/2024',
                        'Ticket aperti nell\'ultimo mese',
                        'Report dal 1 gennaio al 31 marzo 2024',
                        'Ticket dell\'anno 2024',
                        'Report degli ultimi 30 giorni',
                    ],
                ], 400);
            }

            $companyName = null;

            if ($companyId) {
                $company = Company::find($companyId);
                $companyName = $company ? Str::slug($company->name) : 'company_'.$companyId;
            }

            $name = time().'_ai_report_'.(!!$companyName ? $companyName.'_' : '').$dates['start_date'].'_'.$dates['end_date'].'.pdf';

            // Crea il record TicketReportPdfExport
            $pdfExport = TicketReportPdfExport::create([
                'file_name' => $name,
                'file_path' => 'pdf_exports/'.($companyId ? $companyId : 'no_company').'/'.$name, // Verrà impostato dal job
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
                'optional_parameters' => json_encode(['ai_generated' => true]),
                'company_id' => $companyId, // Può essere null
                'is_generated' => false,
                'is_user_generated' => false,
                'is_failed' => false,
                'error_message' => null,
                'user_id' => $user->id,
                'is_approved_billing' => false,
                'approved_billing_identification' => null,
                'send_email' => false, // I report AI non inviano email automaticamente
                'is_ai_generated' => true,
                'ai_query' => $sqlQuery,
                'ai_prompt' => $userPrompt,
            ]);

            // Dispatcha il job per generare il PDF
            GeneratePdfReport::dispatch($pdfExport);

            // Aggiorna log con successo
            $this->updateLogEntry($logId, [
                'was_successful' => true,
                'execution_time' => microtime(true) - $startTime,
            ]);

            Log::info('Report PDF AI schedulato con successo', [
                'user_id' => $user->id,
                'pdf_export_id' => $pdfExport->id,
                'prompt' => $userPrompt,
                'sql_query' => $sqlQuery,
                'company_id' => $companyId,
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report PDF in fase di generazione. Riceverai una notifica quando sarà pronto.',
                'pdf_export_id' => $pdfExport->id,
                'company_id' => $companyId,
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
                'estimated_time' => 'Pochi minuti',
            ], 202); // 202 Accepted

        } catch (Exception $e) {
            $errorMessage = 'Errore durante la creazione del report PDF: ' . $e->getMessage();

            if ($logId) {
                $this->updateLogEntry($logId, [
                    'error_message' => $e->getMessage(),
                    'execution_time' => microtime(true) - $startTime,
                ]);
            }

            Log::error('Errore generazione PDF AI', [
                'user_id' => $user ? $user->id : null,
                'prompt' => $userPrompt,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Errore durante la creazione del report PDF.'], 500);
        }
    }
    
    /**
     * Genera un report PDF da un prompt AI.
     * Crea un record in TicketReportPdfExport con la query generata dall'AI,
     * poi il job GeneratePdfReport lo processerà.
     */
    public function generatePdfProjectReportFromPrompt(Request $request)
    {
        $startTime = microtime(true);
        $user = $request->user();
        $userPrompt = '';
        $logId = null;

        try {
            // Solo admin possono generare report PDF
            if (!$user || $user->is_admin != 1) {
                return response()->json(['error' => 'Unauthorized. Solo gli admin possono generare report PDF.'], 401);
            }

            // Validazione - solo il prompt è richiesto
            $validated = $request->validate([
                'prompt' => 'required|string|max:1000',
            ]);

            $userPrompt = $validated['prompt'];

            // Crea log iniziale
            $logData = [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_prompt' => $userPrompt,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'was_successful' => false,
            ];

            // Dato che questa funzione serve a generare un report specifico, qui si potrebbe inserire del testo iniziale per specificare il contesto, 
            // es. come deve usare le date nella query (da - a, non devono essere created_at del ticket e basta, ma created_at del ticket deve essere 
            // inferiore alla data di fine e la data di inizio si deve usare per escludere i ticket che sono stati chiusi prima di quella data). 
            // Poi nel caso gli si può passare anche la query utilizzata dalla funzione normale come riferimento.

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
            $sqlQuery = $this->generateSqlFromPrompt($userPrompt, 'project_pdf');

            if (!$sqlQuery) {
                $this->updateLogEntry($logId, [
                    'error_message' => 'Impossibile generare query SQL dal prompt fornito',
                    'execution_time' => microtime(true) - $startTime,
                ]);

                return response()->json([
                    'error' => 'Non riesco a interpretare la richiesta per generare un report PDF.',
                    'suggestion' => 'Prova a essere più specifico sui ticket che vuoi nel report.',
                    'examples' => [
                        'Ticket chiusi nell\'ultimo mese',
                        'Ticket aperti con priorità alta',
                        'Tutti i ticket di tipo "Richiesta" chiusi quest\'anno',
                        'Ticket fatturabili non ancora fatturati per l\'azienda Acme',
                    ],
                ], 400);
            }

            // Aggiorna log con la query generata
            $this->updateLogEntry($logId, [
                'generated_sql' => $sqlQuery,
                'ai_response' => $this->lastAiResponse,
            ]);

            // Valida la query (senza eseguirla per ora)
            $this->validateSqlQuery($sqlQuery);
            
            // Estrae project_id dal prompt se presente in modo univoco
            $projectId = $this->extractProjectIdFromPrompt($userPrompt);

            if(!$projectId){
                return response()->json([
                    'error' => 'Non riesco a identificare il progetto richiesto.',
                    'suggestion' => 'Per favore specifica un progetto nel prompt.',
                    'examples' => [
                        'Ticket del progetto ID 1234 chiusi nell\'ultimo mese',
                        'Tutti i ticket aperti per il progetto XYZ',
                        'Report dei ticket per il progetto ABC',
                    ],
                ], 400);
            }
            
            $project = Ticket::find($projectId);

            if(!$project){
                return response()->json([
                    'error' => 'Il progetto specificato non esiste.',
                    'suggestion' => 'Per favore verifica l\'ID o il nome del progetto e riprova.',
                ], 400);
            }


            // Estrae company_id dalla query se presente in modo univoco
            $companyId = $this->extractCompanyIdFromQuery($sqlQuery);

            if(!$companyId){
                // Recupera il company_id dal progetto                
                $companyId = $project->company_id;
            }
            
            // Estrae le date dal prompt (OBBLIGATORIE)
            $dates = $this->extractDatesFromPrompt($userPrompt, $sqlQuery);
            
            if (!$dates['start_date'] || !$dates['end_date']) {
                $this->updateLogEntry($logId, [
                    'error_message' => 'Impossibile estrarre le date dal prompt. Le date sono obbligatorie.',
                    'execution_time' => microtime(true) - $startTime,
                ]);

                return response()->json([
                    'error' => 'Non riesco a identificare il periodo temporale richiesto.',
                    'suggestion' => 'Per favore specifica un periodo nel prompt.',
                    'examples' => [
                        'Ticket chiusi dal 01/01/2024 al 31/12/2024',
                        'Ticket aperti nell\'ultimo mese',
                        'Report dal 1 gennaio al 31 marzo 2024',
                        'Ticket dell\'anno 2024',
                        'Report degli ultimi 30 giorni',
                    ],
                ], 400);
            }

            $companyName = null;

            if ($companyId) {
                $company = Company::find($companyId);
                $companyName = $company ? Str::slug($company->name) : 'company_'.$companyId;
            }

            $name = time().'_ai_report_'.(!!$companyName ? $companyName.'_' : '').$dates['start_date'].'_'.$dates['end_date'].'.pdf';

            // Crea il record ProjectReportPdfExport
            $pdfProjectExport = ProjectReportPdfExport::create([
                'file_name' => $name,
                'file_path' => 'pdf_exports/'.($companyId ? $companyId : 'no_company').'/'.$name, // Verrà impostato dal job
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
                'optional_parameters' => json_encode(['ai_generated' => true]),
                'company_id' => $companyId, // non può essere null
                'project_id' => $projectId,
                'is_user_generated' => false,
                'user_id' => $user->id,
                'send_email' => false, // I report AI non inviano email automaticamente. poi l'invio mail verrà gestito diversamente immagino.
                'is_ai_generated' => true,
                'ai_query' => $sqlQuery,
                'ai_prompt' => $userPrompt,
            ]);

            // Dispatcha il job per generare il PDF
            GeneratePdfProjectReport::dispatch($pdfProjectExport);

            // Aggiorna log con successo
            $this->updateLogEntry($logId, [
                'was_successful' => true,
                'execution_time' => microtime(true) - $startTime,
            ]);

            Log::info('Report PDF AI schedulato con successo', [
                'user_id' => $user->id,
                'pdf_export_id' => $pdfProjectExport->id,
                'prompt' => $userPrompt,
                'sql_query' => $sqlQuery,
                'company_id' => $companyId,
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report PDF in fase di generazione. Riceverai una notifica quando sarà pronto.',
                'pdf_export_id' => $pdfProjectExport->id,
                'company_id' => $companyId,
                'project_id' => $projectId,
                'start_date' => $dates['start_date'],
                'end_date' => $dates['end_date'],
                'estimated_time' => 'Pochi minuti',
            ], 202); // 202 Accepted

        } catch (Exception $e) {
            $errorMessage = 'Errore durante la creazione del report PDF: ' . $e->getMessage();

            if ($logId) {
                $this->updateLogEntry($logId, [
                    'error_message' => $e->getMessage(),
                    'execution_time' => microtime(true) - $startTime,
                ]);
            }

            Log::error('Errore generazione PDF AI', [
                'user_id' => $user ? $user->id : null,
                'prompt' => $userPrompt,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Errore durante la creazione del report PDF.'], 500);
        }
    }

    /**
     * Valida la query SQL senza eseguirla (controlli di sicurezza)
     */
    public function validateSqlQuery(string $sqlQuery): void
    {
        // Usa gli stessi controlli di sicurezza di executeSecureQuery
        if (!preg_match('/^\s*SELECT\s+/i', $sqlQuery)) {
            throw new Exception('Solo query SELECT sono permesse');
        }

        $dangerousOperations = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'REPLACE'];
        foreach ($dangerousOperations as $op) {
            if (preg_match('/\b' . $op . '\s+/i', $sqlQuery)) {
                throw new Exception("Operazione $op non permessa");
            }
        }

        $sensitiveColumns = [
            'password', 'password_hash', 'encrypted_password', 'pwd',
            'access_token', 'refresh_token', 'api_token', 'remember_token', 'token',
            'microsoft_token', 'oauth_token', 'google_token', 'facebook_token',
            'secret_key', 'private_key', 'secret', 'key',
            '2fa_secret', 'backup_codes', 'two_factor', 'otp',
            'client_secret', 'auth_secret',
        ];

        foreach ($sensitiveColumns as $column) {
            if (preg_match('/\b' . $column . '\b/i', $sqlQuery)) {
                throw new Exception("Colonna sensibile '$column' non permessa nelle query");
            }
        }
    }

    /**
     * Estrae company_id dalla query SQL se presente in modo univoco.
     * Ritorna l'ID della company se trovato un solo valore specifico, altrimenti null.
     */
    private function extractCompanyIdFromQuery(string $sqlQuery): ?int
    {
        // Pattern per trovare company_id = valore (case insensitive, gestisce spazi)
        // Esempi: company_id = 5, company_id=5, tickets.company_id = 5
        if (preg_match_all('/(?:tickets\.)?company_id\s*=\s*(\d+)/i', $sqlQuery, $matches)) {
            $companyIds = array_unique($matches[1]);
            
            // Se c'è esattamente un company_id univoco nella query, lo usiamo
            if (count($companyIds) === 1) {
                $companyId = (int) $companyIds[0];
                
                // Verifica che la company esista
                $companyExists = DB::table('companies')->where('id', $companyId)->exists();
                
                if ($companyExists) {
                    Log::info('Company ID estratto dalla query AI', [
                        'company_id' => $companyId,
                        'query' => $sqlQuery
                    ]);
                    return $companyId;
                }
            }
        }

        // Se non troviamo un company_id univoco o valido, ritorniamo null
        Log::info('Nessun company_id univoco trovato nella query AI', [
            'query' => $sqlQuery
        ]);
        
        return null;
    }

    /**
     * Estrae l'ID del progetto dal prompt usando l'AI.
     * L'AI deve identificare se nel prompt è stato specificato l'ID del progetto o il nome del progetto.
     * Ritorna l'ID del progetto se trovato e valido, altrimenti null.
     */
    private function extractProjectIdFromPrompt(string $userPrompt): ?int
    {
        try {
            $systemPrompt = "Trova il progetto nel testo. Rispondi SOLO con uno di questi formati:
            ID:numero_completo (esempio: ID:32 oppure ID:1234)
            NAME:nome_progetto (esempio: NAME:Alpha)
            NONE (se non c'è nessun progetto)

            IMPORTANTE: Copia il numero INTERO del progetto, non solo la prima cifra.

            Testo: {$userPrompt}

            Risposta:";

            $endpoint = "https://{$this->location}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->location}/publishers/google/models/gemini-2.5-pro:generateContent";

            $requestBody = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0,
                    'maxOutputTokens' => 200,
                    'candidateCount' => 1,
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $requestBody);

            if (!$response->successful()) {
                Log::error('Errore nella chiamata a Vertex AI per extractProjectIdFromPrompt', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $responseData = $response->json();
            $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$aiText) {
                Log::warning('Nessuna risposta dall\'AI per extractProjectIdFromPrompt');
                return null;
            }

            // Pulisce la risposta
            $aiText = trim($aiText);
            
            Log::info('Risposta AI per extractProjectIdFromPrompt', [
                'ai_text' => $aiText,
                'prompt' => $userPrompt
            ]);

            // Caso 1: NONE - nessun progetto trovato
            if (strtoupper($aiText) === 'NONE') {
                Log::info('Nessun progetto specificato nel prompt', [
                    'prompt' => $userPrompt
                ]);
                return null;
            }

            // Caso 2: ID:numero (anche se manca il numero, proviamo a estrarre dal prompt originale)
            if (preg_match('/ID:(\d+)/i', $aiText, $matches)) {
                $projectId = (int) $matches[1];
                
                // Verifica che il progetto esista ed è effettivamente un progetto
                $isProject = DB::table('tickets')
                    ->join('ticket_types', 'tickets.type_id', '=', 'ticket_types.id')
                    ->where('tickets.id', $projectId)
                    ->where('ticket_types.is_project', true)
                    ->exists();

                if ($isProject) {
                    Log::info('ID progetto estratto dal prompt tramite AI', [
                        'project_id' => $projectId,
                        'prompt' => $userPrompt
                    ]);
                    return $projectId;
                } else {
                    Log::warning('Il ticket specificato non esiste o non è un progetto', [
                        'ticket_id' => $projectId,
                        'prompt' => $userPrompt
                    ]);
                    return null;
                }
            }
            
            // Caso 2b: Se l'AI ha risposto solo "ID:" senza numero, prova estrazione diretta dal prompt
            if (preg_match('/^ID:\s*$/i', $aiText) && preg_match('/\b(?:id|progetto)\s*(\d+)\b/i', $userPrompt, $matches)) {
                $projectId = (int) $matches[1];
                
                // Verifica che il progetto esista ed è effettivamente un progetto
                $isProject = DB::table('tickets')
                    ->join('ticket_types', 'tickets.type_id', '=', 'ticket_types.id')
                    ->where('tickets.id', $projectId)
                    ->where('ticket_types.is_project', true)
                    ->exists();

                if ($isProject) {
                    Log::info('ID progetto estratto dal prompt tramite fallback regex', [
                        'project_id' => $projectId,
                        'prompt' => $userPrompt,
                        'ai_text' => $aiText
                    ]);
                    return $projectId;
                } else {
                    Log::warning('Il ticket specificato (fallback) non esiste o non è un progetto', [
                        'ticket_id' => $projectId,
                        'prompt' => $userPrompt
                    ]);
                    return null;
                }
            }

            // Caso 3: NAME:nome
            if (preg_match('/^NAME:(.+)$/i', $aiText, $matches)) {
                $projectName = trim($matches[1]);
                
                // Cerca il progetto per nome nella tabella tickets
                $project = DB::table('tickets')
                    ->join('ticket_types', 'tickets.type_id', '=', 'ticket_types.id')
                    ->where('tickets.project_name', $projectName)
                    ->where('ticket_types.is_project', true)
                    ->select('tickets.id')
                    ->first();

                if ($project) {
                    $projectId = (int) $project->id;
                    
                    Log::info('ID progetto estratto dal prompt tramite nome', [
                        'project_id' => $projectId,
                        'project_name' => $projectName,
                        'prompt' => $userPrompt
                    ]);
                    return $projectId;
                } else {
                    Log::warning('Progetto non trovato con il nome specificato', [
                        'project_name' => $projectName,
                        'prompt' => $userPrompt
                    ]);
                    return null;
                }
            }

            // Risposta non riconosciuta
            Log::warning('Risposta AI non riconosciuta per extractProjectIdFromPrompt', [
                'ai_response' => $aiText,
                'prompt' => $userPrompt
            ]);
            return null;

        } catch (Exception $e) {
            Log::error('Errore in extractProjectIdFromPrompt', [
                'error' => $e->getMessage(),
                'prompt' => $userPrompt,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    

    /**
     * Estrae le date di inizio e fine dal prompt o dalla query SQL.
     * Ritorna un array con start_date e end_date in formato Y-m-d, oppure null se non trovate.
     */
    private function extractDatesFromPrompt(string $prompt, string $sqlQuery): array
    {
        $startDate = null;
        $endDate = null;

        // Pattern per date esplicite in vari formati
        // Esempi: dal 01/01/2024 al 31/12/2024, from 2024-01-01 to 2024-12-31
        $patterns = [
            // Formato italiano: dal GG/MM/AAAA al GG/MM/AAAA
            '/dal\s+(?:le\s+date\s+)?(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\s+al\s+(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/i',
            // Formato: da GG/MM/AAAA a GG/MM/AAAA
            '/da\s+(?:le\s+date\s+)?(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\s+a\s+(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/i',
            // Formato inglese: from YYYY-MM-DD to YYYY-MM-DD
            '/from\s+(?:the\s+dates?\s+)?(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\s+to\s+(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/i',
            // Formato: tra GG/MM/AAAA e GG/MM/AAAA (con supporto per "tra le date")
            '/tra\s+(?:le\s+date\s+)?(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\s+e\s+(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/i',
        ];

        $dateFound = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prompt, $matches)) {
                Log::debug('Pattern date matched', [
                    'pattern' => $pattern,
                    'matches' => $matches,
                    'prompt' => $prompt
                ]);
                
                // Determina il formato in base al pattern
                if (strpos($pattern, 'YYYY') !== false) {
                    // Formato YYYY-MM-DD
                    $startDate = \Carbon\Carbon::createFromFormat('Y-m-d', "{$matches[1]}-{$matches[2]}-{$matches[3]}")->format('Y-m-d');
                    $endDate = \Carbon\Carbon::createFromFormat('Y-m-d', "{$matches[4]}-{$matches[5]}-{$matches[6]}")->format('Y-m-d');
                } else {
                    // Formato DD/MM/YYYY
                    $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', "{$matches[1]}/{$matches[2]}/{$matches[3]}")->format('Y-m-d');
                    $endDate = \Carbon\Carbon::createFromFormat('d/m/Y', "{$matches[4]}/{$matches[5]}/{$matches[6]}")->format('Y-m-d');
                }
                $dateFound = true;
                break;
            }
        }
        
        Log::debug('extractDatesFromPrompt result', [
            'dateFound' => $dateFound,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'prompt' => $prompt
        ]);

        // Se non trovate date esplicite, cerca periodi relativi
        if (!$dateFound && (!$startDate || !$endDate)) {
            $now = \Carbon\Carbon::now();

            // Ultimo mese / last month
            if (preg_match('/ultimo\s+mese|last\s+month/i', $prompt)) {
                $startDate = $now->copy()->subMonth()->startOfMonth()->format('Y-m-d');
                $endDate = $now->copy()->subMonth()->endOfMonth()->format('Y-m-d');
            }
            // Ultimi X giorni
            elseif (preg_match('/ultim[oi]\s+(\d+)\s+giorni?|last\s+(\d+)\s+days?/i', $prompt, $matches)) {
                $days = (int)($matches[1] ?? $matches[2]);
                $startDate = $now->copy()->subDays($days)->format('Y-m-d');
                $endDate = $now->format('Y-m-d');
            }
            // Questo mese / this month
            elseif (preg_match('/questo\s+mese|this\s+month/i', $prompt)) {
                $startDate = $now->copy()->startOfMonth()->format('Y-m-d');
                $endDate = $now->format('Y-m-d');
            }
            // Mese specifico: gennaio 2024, gen 2024, january 2024
            elseif (preg_match('/(gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre|gen|feb|mar|apr|mag|giu|lug|ago|set|ott|nov|dic|january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})/i', $prompt, $matches)) {
                $monthMap = [
                    'gennaio' => 1, 'gen' => 1, 'january' => 1,
                    'febbraio' => 2, 'feb' => 2, 'february' => 2,
                    'marzo' => 3, 'mar' => 3, 'march' => 3,
                    'aprile' => 4, 'apr' => 4, 'april' => 4,
                    'maggio' => 5, 'mag' => 5, 'may' => 5,
                    'giugno' => 6, 'giu' => 6, 'june' => 6,
                    'luglio' => 7, 'lug' => 7, 'july' => 7,
                    'agosto' => 8, 'ago' => 8, 'august' => 8,
                    'settembre' => 9, 'set' => 9, 'september' => 9,
                    'ottobre' => 10, 'ott' => 10, 'october' => 10,
                    'novembre' => 11, 'nov' => 11, 'november' => 11,
                    'dicembre' => 12, 'dic' => 12, 'december' => 12,
                ];
                $month = $monthMap[strtolower($matches[1])] ?? null;
                $year = (int)$matches[2];
                if ($month) {
                    $date = \Carbon\Carbon::create($year, $month, 1);
                    $startDate = $date->copy()->startOfMonth()->format('Y-m-d');
                    $endDate = $date->copy()->endOfMonth()->format('Y-m-d');
                }
            }
            // Anno specifico: 2024, anno 2024, year 2024 (solo se non c'è uno slash/dash prima)
            elseif (preg_match('/(?:anno|year)\s+(\d{4})|^(\d{4})$/i', $prompt, $matches)) {
                $year = (int)($matches[1] ?? $matches[2]);
                $startDate = \Carbon\Carbon::create($year, 1, 1)->format('Y-m-d');
                $endDate = \Carbon\Carbon::create($year, 12, 31)->format('Y-m-d');
            }
        }

        // Fallback: cerca date nella query SQL
        if ((!$startDate || !$endDate) && $sqlQuery) {
            // Pattern per created_at >= 'YYYY-MM-DD' AND created_at <= 'YYYY-MM-DD'
            if (preg_match('/created_at\s*>=\s*[\'"](\d{4}-\d{2}-\d{2})[\'"].*created_at\s*<=\s*[\'"](\d{4}-\d{2}-\d{2})[\'\"]/i', $sqlQuery, $matches)) {
                $startDate = $matches[1];
                $endDate = $matches[2];
            }
            // Pattern per BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD'
            elseif (preg_match('/created_at\s+BETWEEN\s+[\'"](\d{4}-\d{2}-\d{2})[\'"].*[\'"](\d{4}-\d{2}-\d{2})[\'\"]/i', $sqlQuery, $matches)) {
                $startDate = $matches[1];
                $endDate = $matches[2];
            }
        }

        Log::info('Estrazione date dal prompt AI', [
            'prompt' => $prompt,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}


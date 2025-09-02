<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class VertexAiController extends Controller
{
    
    private $projectId;
    private $location;
    private $accessToken;

    public function __construct() {
        $this->projectId = config('vertex.project_id');
        $this->location = config('vertex.location');

        $keyFilePath = config('vertex.key_file_path');

        if (!$this->projectId || !$this->location || !$keyFilePath) {
            throw new Exception("Configurazione Vertex AI mancante. Controlla le variabili d'ambiente.");
        }

        $this->accessToken = $this->getAccessToken($keyFilePath);

    }

    private function getAccessToken(string $keyFilePath): string {
        try {
            $serviceAccount = json_decode(file_get_contents($keyFilePath), true);

            // Crea JWT per l'autenticazione
            $now = time();
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600
            ];

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->createJWT($payload, $serviceAccount['private_key'])
            ]);

            if (!$response->successful()) {
                throw new Exception("Errore nell'ottenere l'access token");
            }

            return $response->json()['access_token'];
        } catch (Exception $e) {
            // Fallback: usa gcloud auth
            $command = 'gcloud auth print-access-token';
            $token = trim(shell_exec($command));

            if (empty($token)) {
                throw new Exception("Impossibile ottenere access token. Configura gcloud auth o il service account.");
            }

            return $token;
        }
    }

    private function createJWT(array $payload, string $privateKey): string {
        // Header
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $headerEncoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');

        // Payload
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        // Signature
        $signature = '';
        openssl_sign($headerEncoded . '.' . $payloadEncoded, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    private function generatePromptFromHtml(string $htmlPulito): string {
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
        ' . "{$htmlPulito}"
        ;
    }

    private function excuteRequest(string $prompt, string $modelName = 'gemini-2.5-flash-lite'): string {

        $url = "https://{$this->location}-aiplatform.googleapis.com/v1/projects/{$this->projectId}/locations/{$this->location}/publishers/google/models/{$modelName}:generateContent";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 10000,
                'topP' => 0.8,
                'topK' => 40
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json'
        ])->timeout(500)->post($url, $payload);

        if (!$response->successful()) {
            throw new Exception("Errore chiamata Gemini: " . $response->body());
        }

        $data = $response->json();

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Risposta Gemini non valida: " . json_encode($data));
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
            Log::error("Errore Vertex AI: " . $e->getMessage());
            return response()->json(['error' => 'Errore durante l\'estrazione delle notizie.'], 500);
        }
    }
}

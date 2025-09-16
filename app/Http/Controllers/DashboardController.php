<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //
        $user = auth()->user();
        $dashboard = Dashboard::where('user_id', $user->id)->first();

        if (!$dashboard) {
            // Ottieni la configurazione predefinita in base al tenant
            $defaultConfig = $this->getDefaultConfigForTenant();

            $dashboard = Dashboard::create([
                'user_id' => $user->id,
                'configuration' => json_encode($defaultConfig),
                'enabled_widgets' => json_encode([]),
                'settings' => json_encode([]),
            ]);
        }

        // Ottieni la configurazione delle card
        $cardConfig = json_decode($dashboard->configuration, true);
        
        // Aggiungi i dati statistici per ogni card
        $cardConfig = $this->enrichCardsWithData($cardConfig);

        return response()->json($cardConfig);
    }
    
    /**
     * Ottiene la configurazione predefinita delle card in base al tenant corrente
     */
    private function getDefaultConfigForTenant() {
        $tenant = $this->getCurrentTenant();
        $config = config('dashboard');
        if (isset($config[$tenant])) {
            return $config[$tenant];
        }
        // Configurazione predefinita per altri tenant
        return [
            'leftCards' => [
                [
                    'id' => 'ticket-aperti',
                    'type' => 'open-tickets',
                    'color' => 'primary',
                    'content' => 'Ticket aperti'
                ],
                [
                    'id' => 'ticket-in-corso',
                    'type' => 'in-progress-tickets',
                    'color' => 'secondary',
                    'content' => 'Ticket in corso'
                ]
            ],
            'rightCards' => [
                [
                    'id' => 'ticket-in-attesa',
                    'type' => 'waiting-tickets',
                    'color' => 'primary',
                    'content' => 'Ticket in attesa'
                ],
                [
                    'id' => 'ticket-redirect',
                    'type' => 'tickets-redirect',
                    'color' => 'secondary',
                    'content' => 'Gestione ticket'
                ]
            ]
        ];
    }
    
    /**
     * Ottiene il nome del tenant corrente
     */
    private function getCurrentTenant() {
        // Qui puoi implementare la logica per ottenere il tenant corrente
        // Ad esempio, potresti ottenerlo da una variabile di ambiente, da un header, da un database, ecc.
        
        // Per ora, come esempio, controlliamo se esiste una variabile di ambiente TENANT
        $tenant = env('TENANT', '');
        
        // Se non esiste, possiamo controllare il dominio o altre informazioni
        if (empty($tenant)) {
            // Esempio: controlla il dominio
            $host = request()->getHost();
            if (strpos($host, 'domustart') !== false) {
                return 'domustart';
            }
            
            // Puoi aggiungere altri controlli qui
        }
        
        return $tenant;
    }

    /**
     * Aggiunge i dati statistici alle card
     */
    private function enrichCardsWithData($cardConfig) {
        // Ottieni i dati statistici
        $stats = $this->getStats();
        
        // Arricchisci le card di sinistra
        if (isset($cardConfig['leftCards'])) {
            foreach ($cardConfig['leftCards'] as &$card) {
                $card = $this->addStatsToCard($card, $stats);
            }
        }
        
        // Arricchisci le card di destra
        if (isset($cardConfig['rightCards'])) {
            foreach ($cardConfig['rightCards'] as &$card) {
                $card = $this->addStatsToCard($card, $stats);
            }
        }
        
        return $cardConfig;
    }
    
    /**
     * Aggiunge i dati statistici a una singola card
     */
    private function addStatsToCard($card, $stats) {
        switch ($card['type']) {
            case 'companies-count':
                $card['value'] = $stats['companies_count'];
                break;
            case 'users-count':
                $card['value'] = $stats['users_count'];
                break;
            case 'open-tickets':
                $card['value'] = $stats['open_tickets_count'];
                $card['action'] = [
                    'type' => 'link',
                    'url' => '/support/admin/newticket',
                    'label' => 'Nuovo ticket'
                ];
                break;
            case 'tickets-redirect':
                $card['action'] = [
                    'type' => 'link',
                    'url' => '/support/admin/tickets',
                    'label' => 'Visualizza ticket'
                ];
                break;
            case 'latest-dpo-articles':
                $card['data'] = $this->getLatestDpoArticlesData();
                break;
            case 'integys-articles':
                $card['data'] = $this->getIntegysArticlesData();
                break;
            case 'frequent-tickets':
                $card['data'] = $this->getFrequentTicketsData();
                break;
            case 'quick-access-reports':
                $card['data'] = $this->getQuickAccessReportsData();
                break;
            case 'vendor-news':
                $card['data'] = $this->getVendorNewsData();
                break;
            case 'ticket-master':
                $card['data'] = $this->getTicketMasterData();
                break;
            case 'recent-functions':
            case 'recent-tickets':
                $card['data'] = $this->getRecentTicketsData();
                break;
        }
        return $card;
    }
    
    /**
     * Ottiene le statistiche per la dashboard
     */
    private function getStats() {
        // Conta i condomini (aziende) registrati
        $companiesCount = Company::count();
        
        // Conta gli utenti registrati (condomini)
        $usersCount = User::where('is_admin', false)->count();
        
        // Conta i ticket aperti
        $openTicketsCount = Ticket::whereIn('status', ['open', 'in_progress', 'pending'])->count();
        
        return [
            'companies_count' => $companiesCount,
            'users_count' => $usersCount,
            'open_tickets_count' => $openTicketsCount
        ];
    }

    /**
     * Aggiorna la configurazione delle card
     */
    public function updateCardConfig(Request $request) {
        $user = auth()->user();
        $dashboard = Dashboard::where('user_id', $user->id)->first();
        
        if (!$dashboard) {
            return response()->json(['error' => 'Dashboard non trovata'], 404);
        }
        
        $dashboard->configuration = json_encode([
            'leftCards' => $request->leftCards,
            'rightCards' => $request->rightCards
        ]);
        
        $dashboard->save();
        
        // Restituisci la configurazione aggiornata con i dati statistici
        $cardConfig = json_decode($dashboard->configuration, true);
        $cardConfig = $this->enrichCardsWithData($cardConfig);
        
        return response()->json($cardConfig);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Dashboard $dashboard) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Dashboard $dashboard) {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Dashboard $dashboard) {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dashboard $dashboard) {
        //
    }

    /**
     * Ottiene i dati per la card "Ultimi articoli in DPO del comune"
     */
    private function getLatestDpoArticlesData() {
        // TODO: implementa la logica
        return [];
    }

    /**
     * Ottiene i dati per la card "Articoli di Integys"
     */
    private function getIntegysArticlesData() {
        // TODO: implementa la logica
        return [];
    }

    /**
     * Ottiene i dati per la card "Ticket più frequenti"
     */
    private function getFrequentTicketsData() {
        // TODO: implementa la logica

        $frequentTypes = Ticket::select('type_id', DB::raw('count(*) as total'))
            ->groupBy('type_id')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $result = [];

        foreach ($frequentTypes as $item) {
            $ticketType = TicketType::with('company')->find($item->type_id);

            if ($ticketType) {
                $result[] = [
                    'type' => $ticketType,
                    'count' => $item->total,
                ];
            }
        }

        return $result;
    }

    /**
     * Ottiene i dati per la card "Accesso rapido ai report"
     */
    private function getQuickAccessReportsData() {
        // TODO: implementa la logica
        return [];
    }

    /**
     * Ottiene i dati per la card "News riguardanti vendor diversi"
     */
    private function getVendorNewsData() {
        // TODO: implementa la logica
        return [];
    }

    /**
     * Ottiene i dati per la card "Ultime funzioni utilizzate"
     */
    private function getRecentTicketsData() {
        // TODO: implementa la logica

        $tickets = Ticket::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('type_id')
            ->take(5);

        $types = $tickets->pluck('type_id')->toArray();

        $functions = [];

        foreach($types as $type) {
            $ticketType = TicketType::with('company')->find($type);
            if ($ticketType) {
                $functions[] = $ticketType;
            }
        }

        return $functions;
    }

    public function getTicketMasterData() {

        $ticketStages = config('app.ticket_stages');

        // Recupera ticket i cui tipi hanno is_master = 1 usando whereHas per evitare join e abilitare eager loading
        // Assicurati di includere la foreign key 'user_id' nella selezione, altrimenti la relazione user non viene collegata.
        $tickets = Ticket::with(['ticketType:id,name', 'handler:id,name,surname', 'user:id,name,surname']) // Eager load per ottimizzare le query
            ->where('status', '!=', 5)
            ->whereHas('ticketType', function ($query) {
                $query->where('is_master', 1);
            })
            ->orderBy('created_at', 'desc')
            ->get(['id', 'status', 'type_id', 'admin_user_id', 'user_id']);

        return $tickets->map(function ($ticket) use ($ticketStages) {
            return [
                'id' => $ticket->id,
                'status' => isset($ticketStages[$ticket->status]) ? $ticketStages[$ticket->status] : $ticket->status,
                'type' => $ticket->ticketType ? $ticket->ticketType->name : null,
                'admin' => $ticket->handler ? trim(($ticket->handler->name ?? '') . ' ' . ($ticket->handler->surname ?? '')) : null,
                'opened_by' => $ticket->user ? trim(($ticket->user->name ?? '') . ' ' . ($ticket->user->surname ?? '')) : null,
            ];
        })->values()->toArray();


    }
}

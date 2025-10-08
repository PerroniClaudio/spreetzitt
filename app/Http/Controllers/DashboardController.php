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
        
        // Verifica che l'utente sia un admin
        if (!$user->is_admin) {
            return response()->json(['error' => 'Questa dashboard è riservata agli amministratori'], 403);
        }
        
        $dashboard = Dashboard::where('user_id', $user->id)
            ->where('type', 'admin')
            ->first();

        if (!$dashboard) {
            // Ottieni la configurazione predefinita in base al tenant
            $defaultConfig = $this->getDefaultConfigForTenant();

            $dashboard = Dashboard::create([
                'user_id' => $user->id,
                'type' => 'admin',
                'configuration' => $defaultConfig,
                'enabled_widgets' => [],
                'settings' => [],
            ]);
        }

        // Ottieni la configurazione delle card
        $cardConfig = $dashboard->configuration;
        
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
            case 'activities-open':
                $card['data'] = $this->getNormalTicketData();
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
        $closedStageId = \App\Models\TicketStage::where('system_key', 'closed')->value('id');
        $openTicketsCount = Ticket::where('stage_id', '!=', $closedStageId)->count();
        
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
        
        // Verifica che l'utente sia un admin
        if (!$user->is_admin) {
            return response()->json(['error' => 'Questa dashboard è riservata agli amministratori'], 403);
        }
        
        $dashboard = Dashboard::where('user_id', $user->id)
            ->where('type', 'admin')
            ->first();
        
        if (!$dashboard) {
            return response()->json(['error' => 'Dashboard non trovata'], 404);
        }
        
        $dashboard->configuration = [
            'leftCards' => $request->leftCards,
            'rightCards' => $request->rightCards
        ];
        
        $dashboard->save();
        
        // Restituisci la configurazione aggiornata con i dati statistici
        $cardConfig = $dashboard->configuration;
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
        $closedStageId = \App\Models\TicketStage::where('system_key', 'closed')->value('id');

        // Recupera ticket i cui tipi hanno is_master = 1 usando whereHas per evitare join e abilitare eager loading
        // Assicurati di includere la foreign key 'user_id' nella selezione, altrimenti la relazione user non viene collegata.
        $tickets = Ticket::with(['ticketType:id,name', 'handler:id,name,surname', 'user:id,name,surname', 'stage']) // Eager load per ottimizzare le query
            ->where('stage_id', '!=', $closedStageId)
            ->whereHas('ticketType', function ($query) {
                $query->where('is_master', 1);
            })
            ->orderBy('created_at', 'desc')
            ->get(['id', 'stage_id', 'type_id', 'admin_user_id', 'user_id']);

        return $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'stage' => $ticket->stage,
                'type' => $ticket->ticketType ? $ticket->ticketType->name : null,
                'admin' => $ticket->handler ? trim(($ticket->handler->name ?? '') . ' ' . ($ticket->handler->surname ?? '')) : null,
                'opened_by' => $ticket->user ? trim(($ticket->user->name ?? '') . ' ' . ($ticket->user->surname ?? '')) : null,
            ];
        })->values()->toArray();


    }

    public function getNormalTicketData() {
        $closedStageId = \App\Models\TicketStage::where('system_key', 'closed')->value('id');

        // Recupera ticket i cui tipi hanno is_master = 0 usando whereHas per evitare join e abilitare eager loading
        // Assicurati di includere la foreign key 'user_id' nella selezione, altrimenti la relazione user non viene collegata.
        $tickets = Ticket::with(['ticketType:id,name', 'handler:id,name,surname', 'user:id,name,surname', 'stage']) // Eager load per ottimizzare le query
            ->where('stage_id', '!=', $closedStageId)
            ->whereHas('ticketType', function ($query) {
                $query->where('is_master', 0);
            })
            ->orderBy('created_at', 'desc')
            ->get(['id', 'stage_id', 'type_id', 'admin_user_id', 'user_id'])
            ->take(5);

        return $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'stage' => $ticket->stage,
                'type' => $ticket->ticketType ? $ticket->ticketType->name : null,
                'admin' => $ticket->handler ? trim(($ticket->handler->name ?? '') . ' ' . ($ticket->handler->surname ?? '')) : null,
                'opened_by' => $ticket->user ? trim(($ticket->user->name ?? '') . ' ' . ($ticket->user->surname ?? '')) : null,
            ];
        })->values()->toArray();
    }

    /**
     * Display a listing of the resource for standard users.
     */
    public function userIndex() {
        $user = auth()->user();
        
        // Verifica che l'utente sia un utente standard (non admin)
        if ($user->is_admin) {
            return response()->json(['error' => 'Questa dashboard è riservata agli utenti standard'], 403);
        }
        
        $dashboard = Dashboard::where('user_id', $user->id)
            ->where('type', 'user')
            ->first();

        if (!$dashboard) {
            // Ottieni la configurazione predefinita per gli utenti standard
            $defaultConfig = $this->getDefaultConfigForUser();

            $dashboard = Dashboard::create([
                'user_id' => $user->id,
                'type' => 'user',
                'configuration' => $defaultConfig,
                'enabled_widgets' => [],
                'settings' => [],
               
            ]);
        }

        // Ottieni la configurazione delle card
        $cardConfig = $dashboard->configuration;
        
        
        // Aggiungi i dati statistici per ogni card
        $cardConfig = $this->enrichUserCardsWithData($cardConfig);


        return response()->json($cardConfig);
    }
    
    /**
     * Ottiene la configurazione predefinita delle card per gli utenti standard
     */
    private function getDefaultConfigForUser() {
        $tenant = $this->getCurrentTenant();
        $user = auth()->user();
        
        $rightCards = [
            [
                'id' => 'user-ticket-redirect',
                'type' => 'user-tickets-redirect',
                'color' => 'primary',
                'content' => 'Gestione ticket',
                'icon' => 'mdi-view-list',
                'description' => 'Visualizza tutti i tuoi ticket'
            ]
        ];
        
        // Se il tenant è spreetzit, mostriamo la card hardware-stats
        if ($tenant === 'spreetzit') {
            $rightCards[] = [
                'id' => 'user-hardware-stats',
                'type' => 'user-hardware-stats',
                'color' => 'secondary',
                'content' => $user->is_company_admin ? 'Statistiche hardware' : 'Il mio hardware',
                'icon' => 'mdi-laptop',
                'description' => $user->is_company_admin ? 'Stato hardware aziendale' : 'Hardware assegnato'
            ];
        } else {
            // Per gli altri tenant, mostriamo la card new-ticket standard
            $rightCards[] = [
                'id' => 'user-new-ticket',
                'type' => 'user-new-ticket',
                'color' => 'secondary',
                'content' => 'Nuovo ticket',
                'icon' => 'mdi-plus-circle',
                'description' => 'Crea una nuova richiesta di assistenza'
            ];
        }
        
        return [
            'leftCards' => [
                [
                    'id' => 'user-ticket-aperti',
                    'type' => 'user-open-tickets',
                    'color' => 'primary',
                    'content' => 'I miei ticket aperti',
                    'icon' => 'mdi-ticket-outline',
                    'description' => 'Visualizza i tuoi ticket aperti'
                ],
                [
                    'id' => 'user-ticket-recenti',
                    'type' => 'user-recent-tickets',
                    'color' => 'secondary',
                    'content' => 'I miei ticket recenti',
                    'icon' => 'mdi-history',
                    'description' => 'Attività recenti'
                ]
            ],
            'rightCards' => $rightCards
        ];
    }

    /**
     * Aggiunge i dati statistici alle card per gli utenti standard
     */
    private function enrichUserCardsWithData($cardConfig) {
        // Ottieni i dati statistici per l'utente
        $stats = $this->getUserStats();
        
        // Arricchisci le card di sinistra
        if (isset($cardConfig['leftCards'])) {
            foreach ($cardConfig['leftCards'] as &$card) {
                $card = $this->addUserStatsToCard($card, $stats);
            }
        }
        
        // Arricchisci le card di destra
        if (isset($cardConfig['rightCards'])) {
            foreach ($cardConfig['rightCards'] as &$card) {
                $card = $this->addUserStatsToCard($card, $stats);
            }
        }
        
        return $cardConfig;
    }

    /**
     * Ottiene le statistiche per la dashboard dell'utente standard
     */
    private function getUserStats() {
        $user = auth()->user();
        $closedStageId = \App\Models\TicketStage::where('system_key', 'closed')->value('id');
        
        // Conta i ticket aperti dell'utente
        $openTicketsCount = Ticket::where('user_id', $user->id)
            ->where('stage_id', '!=', $closedStageId)
            ->count();
        
        // Conta i ticket chiusi recenti (ultimo mese)
        $recentClosedCount = Ticket::where('user_id', $user->id)
            ->where('stage_id', $closedStageId)
            ->where('updated_at', '>=', now()->subMonth())
            ->count();
            
        // Conta i ticket totali
        $totalTicketsCount = Ticket::where('user_id', $user->id)->count();
        
        // Trova il tipo di ticket più utilizzato
        $mostUsedTicketType = Ticket::where('user_id', $user->id)
            ->select('type_id', DB::raw('count(*) as total'))
            ->groupBy('type_id')
            ->orderByDesc('total')
            ->first();
            
        $mostUsedTicketTypeName = null;
        if ($mostUsedTicketType) {
            $ticketType = TicketType::find($mostUsedTicketType->type_id);
            $mostUsedTicketTypeName = $ticketType ? $ticketType->name : null;
        }
        
        return [
            'open_tickets_count' => $openTicketsCount,
            'recent_closed_count' => $recentClosedCount,
            'total_tickets_count' => $totalTicketsCount,
            'most_used_ticket_type' => $mostUsedTicketTypeName
        ];
    }

    /**
     * Aggiunge i dati statistici a una singola card per gli utenti standard
     */
    private function addUserStatsToCard($card, $stats) {
        switch ($card['type']) {
            case 'user-open-tickets':
                $card['value'] = $stats['open_tickets_count'];
                $card['data'] = $this->getUserOpenTicketsData();
                break;
            case 'user-tickets-redirect':
                $card['action'] = [
                    'type' => 'link',
                    'url' => '/support/user/tickets',
                    'label' => 'Visualizza ticket'
                ];
                $card['data'] = $this->getUserTicketsStats();
                break;
            case 'user-new-ticket':
                $card['action'] = [
                    'type' => 'link',
                    'url' => '/support/user/newticket',
                    'label' => 'Apri nuovo ticket'
                ];
                $card['data'] = $this->getUserFrequentTicketTypes();
                break;
            case 'user-hardware-stats':
                $user = auth()->user();
                $card['action'] = [
                    'type' => 'link',
                    'url' => $user->is_company_admin ? '/support/user/hardware' : '/support/user/profile',
                    'label' => $user->is_company_admin ? 'Gestione hardware' : 'Visualizza hardware'
                ];
                $card['data'] = $this->getUserHardwareStats();
                break;
            case 'user-recent-tickets':
                $card['data'] = $this->getUserRecentTicketsData();
                break;
        }
        return $card;
    }

    /**
     * Ottiene i dati per la card "Ticket recenti" dell'utente
     */
    private function getUserRecentTicketsData() {
        $user = auth()->user();
        
        $tickets = Ticket::with(['ticketType:id,name', 'handler:id,name,surname', 'stage'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'stage_id', 'type_id', 'admin_user_id', 'created_at']);

        return $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'stage' => $ticket->stage,
                'type' => $ticket->ticketType ? $ticket->ticketType->name : null,
                'admin' => $ticket->handler ? trim(($ticket->handler->name ?? '') . ' ' . ($ticket->handler->surname ?? '')) : null,
                'created_at' => $ticket->created_at->format('d/m/Y H:i'),
            ];
        })->values()->toArray();
    }

    /**
     * Aggiorna la configurazione delle card per gli utenti standard
     */
    public function updateUserCardConfig(Request $request) {
        $user = auth()->user();
        
        // Verifica che l'utente sia un utente standard (non admin)
        if ($user->is_admin) {
            return response()->json(['error' => 'Questa dashboard è riservata agli utenti standard'], 403);
        }
        
        $dashboard = Dashboard::where('user_id', $user->id)
            ->where('type', 'user')
            ->first();
        
        if (!$dashboard) {
            return response()->json(['error' => 'Dashboard non trovata'], 404);
        }
        
        $dashboard->configuration = [
            'leftCards' => $request->leftCards,
            'rightCards' => $request->rightCards
        ];
        
        $dashboard->save();
        
        // Restituisci la configurazione aggiornata con i dati statistici
        $cardConfig = $dashboard->configuration;
        $cardConfig = $this->enrichUserCardsWithData($cardConfig);
        
        return response()->json($cardConfig);
    }

    /**
     * Ottiene i dati per la card "Ticket aperti" dell'utente
     */
    private function getUserOpenTicketsData() {
        $user = auth()->user();
        $closedStageId = \App\Models\TicketStage::where('system_key', 'closed')->value('id');
        
        $tickets = Ticket::with(['ticketType:id,name', 'stage', 'handler:id,name,surname'])
            ->where('user_id', $user->id)
            ->where('stage_id', '!=', $closedStageId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get(['id', 'stage_id', 'type_id', 'admin_user_id', 'created_at']);

        return $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'stage' => $ticket->stage,
                'type' => $ticket->ticketType ? $ticket->ticketType->name : null,
                'admin' => $ticket->handler ? trim(($ticket->handler->name ?? '') . ' ' . ($ticket->handler->surname ?? '')) : null,
                'created_at' => $ticket->created_at->format('d/m/Y H:i'),
            ];
        })->values()->toArray();
    }
    
    /**
     * Ottiene le statistiche dei ticket per l'utente
     */
    private function getUserTicketsStats() {
        $user = auth()->user();
        $closedStageId = \App\Models\TicketStage::where('system_key', 'closed')->value('id');
        
        // Ticket aperti
        $openTickets = Ticket::where('user_id', $user->id)
            ->where('stage_id', '!=', $closedStageId)
            ->count();
        
        // Ticket chiusi nell'ultimo mese
        $closedLastMonth = Ticket::where('user_id', $user->id)
            ->where('stage_id', $closedStageId)
            ->where('updated_at', '>=', now()->subMonth())
            ->count();
            
        // Ticket totali
        $totalTickets = Ticket::where('user_id', $user->id)->count();
        
        return [
            'open' => $openTickets,
            'closed_last_month' => $closedLastMonth,
            'total' => $totalTickets,
        ];
    }
    
    /**
     * Ottiene i tipi di ticket più frequenti per l'utente
     */
    private function getUserFrequentTicketTypes() {
        $user = auth()->user();
        
        // Trova i tipi di ticket più utilizzati da questo utente
        $frequentTypes = Ticket::where('user_id', $user->id)
            ->select('type_id', DB::raw('count(*) as total'))
            ->groupBy('type_id')
            ->orderByDesc('total')
            ->take(3)
            ->get();
            
        $result = [];
        
        foreach ($frequentTypes as $item) {
            $ticketType = TicketType::find($item->type_id);
            
            if ($ticketType) {
                $result[] = [
                    'id' => $ticketType->id,
                    'name' => $ticketType->name,
                    'count' => $item->total,
                    'url' => "/support/newticket?type={$ticketType->id}"
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Ottiene le statistiche dell'hardware per l'utente
     */
    private function getUserHardwareStats() {
        $user = auth()->user();
        $selectedCompany = $user->selectedCompany();
        
        if (!$selectedCompany) {
            return [
                'error' => 'Nessuna azienda selezionata',
            ];
        }

        // Aggiungi sempre l'informazione is_company_admin nella risposta
        $result = [
            'is_company_admin' => $user->is_company_admin
        ];

        // Se l'utente è un amministratore dell'azienda, mostra statistiche aggregate
        if ($user->is_company_admin) {
            // Conta l'hardware totale dell'azienda
            $totalHardware = \App\Models\Hardware::where('company_id', $selectedCompany->id)->count();
            
            // Conta l'hardware assegnato
            $assignedHardware = \App\Models\Hardware::where('company_id', $selectedCompany->id)
                ->whereHas('users')
                ->count();
            
            // Calcola l'hardware in magazzino
            $unassignedHardware = $totalHardware - $assignedHardware;
            
            // Statistiche per tipo di hardware
            $hardwareByType = \App\Models\Hardware::where('company_id', $selectedCompany->id)
                ->with('hardwareType')
                ->get()
                ->groupBy('hardware_type_id')
                ->map(function($items, $key) {
                    $type = $items->first()->hardwareType ? $items->first()->hardwareType->name : 'Non specificato';
                    $total = $items->count();
                    $assigned = $items->filter(function($item) {
                        return $item->users->count() > 0;
                    })->count();
                    
                    return [
                        'type' => $type,
                        'total' => $total,
                        'assigned' => $assigned,
                        'unassigned' => $total - $assigned,
                        'percent_assigned' => $total > 0 ? round(($assigned / $total) * 100) : 0
                    ];
                })
                ->sortByDesc('total')
                ->take(5)
                ->values()
                ->toArray();
            
            $result['total'] = $totalHardware;
            $result['assigned'] = $assignedHardware;
            $result['unassigned'] = $unassignedHardware;
            $result['percent_assigned'] = $totalHardware > 0 ? round(($assignedHardware / $totalHardware) * 100) : 0;
            $result['by_type'] = $hardwareByType;
            
            return $result;
        } 
        // Altrimenti, mostra solo l'hardware assegnato all'utente
        else {
            $userHardware = $user->hardware()
                ->where('company_id', $selectedCompany->id)
                ->with(['hardwareType'])
                ->get();
            
            $hardwareByType = $userHardware
                ->groupBy(function($item) {
                    return $item->hardwareType ? $item->hardwareType->name : 'Non specificato';
                })
                ->map(function($items, $key) {
                    return [
                        'type' => $key,
                        'count' => $items->count(),
                        'items' => $items->map(function($item) {
                            return [
                                'id' => $item->id,
                                'make' => $item->make,
                                'model' => $item->model,
                                'serial_number' => $item->serial_number,
                                'support_label' => $item->support_label,
                                'company_asset_number' => $item->company_asset_number,
                            ];
                        })->values()->toArray()
                    ];
                })
                ->values()
                ->toArray();
            
            $result['total_assigned'] = $userHardware->count();
            $result['by_type'] = $hardwareByType;
            
            return $result;
        }
    }
}

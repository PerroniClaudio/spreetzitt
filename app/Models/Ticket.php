<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;

class Ticket extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'company_id',
        'user_id',
        'status', // Vecchio status, da mantenere finchè non si migra definitivamente al nuovo sistema con stage
        'stage_id', // New TicketStage relationship
        'description',
        'file',
        'duration',
        'admin_user_id',
        'group_id',
        'due_date',
        'type_id',
        'ticket_cause_id',
        'sla_take',
        'sla_solve',
        'priority',
        'wait_end',
        'is_user_error',
        'unread_mess_for_adm',
        'unread_mess_for_usr',
        'actual_processing_time',
        'is_form_correct',
        'was_user_self_sufficient',
        'is_user_error_problem',
        'work_mode',
        'source',
        'is_rejected',
        'parent_ticket_id',
        'is_billable',
        'is_billed',
        'bill_identification',
        'bill_date',
        'is_billing_validated',
        'master_id',
        'scheduling_id',
        'grouping_id',
        'reopen_parent_id',
        'no_user_response',
        'referer_id',
        'referer_it_id',
        'assigned',
        'last_assignment_id',
        'scheduled_duration',
        'is_scheduling_time_approved', // indica se è stato approvato il tempo previsto attività programmata e ticket collegati (controllo umano se i tempi non coincidono)
        'project_name',
        'project_start',
        'project_end',
        'project_expected_duration',
        'project_id',
    ];

    protected $casts = [
        'assigned' => 'boolean',
        'bill_date' => 'date:Y-m-d',
        'project_start' => 'date:Y-m-d',
        'project_end' => 'date:Y-m-d',
    ];

    public function toSearchableArray()
    {

        
            return [
                'description' => $this->description,
                // 'status' => $this->status,
                'stage_id' => $this->stage_id,
                'user_name' => $this->user->name,
                'user_surname' => $this->user->surname,
                'company' => $this->company->name,
            ];
        
    }

    protected static function booted()
    {
        static::updating(function ($ticket) {
            // Se is_billing_validated viene impostato a 1, controlla is_billable
            if (
                $ticket->isDirty('is_billing_validated') &&
                $ticket->is_billing_validated == 1 &&
                is_null($ticket->is_billable)
            ) {
                throw new \Exception('Non puoi validare la fatturabilità se non hai prima impostato se il ticket è fatturabile o meno (is_billable).');
            }
        });
    }

    /* get the owner */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* get the group */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function ticketCause()
    {
        return $this->belongsTo(TicketCause::class);
    }

    /* get the handler */

    public function handler()
    {
        // return User::find($this->admin_user_id);
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /* get the referer (utente interessato) */

    // public function referer() {
    //     // Si usa newQueryWithoutRelationships per evitare di caricare i messaggi, che non servono
    //     $ticketWithoutMessages = $this->newQueryWithoutRelationships()->find($this->id);
    //     if (!!$ticketWithoutMessages) {
    //         $messages = $ticketWithoutMessages->messages;
    //         if (count($messages) > 0) {
    //             $message_obj = json_decode($messages[0]->message);
    //             // Controllo se esiste la proprietà, perchè nei ticket vecchi non c'è e può dare errore.
    //             if (isset($message_obj->referer)) {
    //                 return User::find($message_obj->referer);
    //             }
    //         }
    //     }
    //     return User::find(0);
    // }

    public function referer()
    {
        return $this->belongsTo(User::class, 'referer_id');
    }

    /* get the IT referer (referente IT) */
    public function refererIt()
    {
        return $this->belongsTo(User::class, 'referer_it_id');
    }

    public function hardware()
    {
        return $this->belongsToMany(Hardware::class);
    }

    /** get  messages  */
    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    /** get  status updates  */
    public function statusUpdates()
    {
        return $this->hasMany(TicketStatusUpdate::class);
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class, 'type_id');
    }

    /**
     * Get the ticket stage (new system)
     */
    public function stage()
    {
        return $this->belongsTo(TicketStage::class, 'stage_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function files()
    {
        return $this->hasMany(TicketFile::class);
    }

    /**
     * Assignment history records for this ticket.
     */
    public function assignmentHistoryRecords()
    {
        return $this->hasMany(TicketAssignmentHistoryRecord::class, 'ticket_id');
    }

    /**
     * Last assignment record reference.
     */
    public function lastAssignment()
    {
        return $this->belongsTo(TicketAssignmentHistoryRecord::class, 'last_assignment_id');
    }

    public function brandUrl()
    {
        $brand_id = $this->ticketType->brand->id;

        return env('APP_URL').'/api/brand/'.$brand_id.'/logo';
    }

    // Invalida la cache per chi ha creato il ticket e per i referenti
    public function invalidateCache()
    {
        // $cacheKey = 'user_' . $user->id . '_tickets';
        $ticketUser = $this->user;
        $referer = $this->referer;
        $refererIT = $this->refererIt;
        if ($ticketUser) {
            Cache::forget('user_'.$ticketUser->id.'_tickets');
            Cache::forget('user_'.$ticketUser->id.'_tickets_with_closed');
        }
        if ($referer) {
            Cache::forget('user_'.$referer->id.'_tickets');
            Cache::forget('user_'.$referer->id.'_tickets_with_closed');
        }
        if ($refererIT) {
            Cache::forget('user_'.$refererIT->id.'_tickets');
            Cache::forget('user_'.$refererIT->id.'_tickets_with_closed');
        }
    }

    // In base al tipo di ticket si dovranno includere o meno i sabati, le domeniche, tutte le ore del giorno o anche le festività
    public function waitingHours($includeSaturday = false, $includeSunday = false, $IncludeAllDayHours = false, $includeHolidays = false)
    {
        $waitingHours = 0;

        // Array delle festività italiane
        $holidays = [
            '01-01', // Capodanno
            '06-01', // Epifania
            '25-04', // Festa della Liberazione
            '01-05', // Festa dei Lavoratori
            '02-06', // Festa della Repubblica
            '15-08', // Ferragosto
            '01-11', // Ognissanti
            '08-12', // Immacolata Concezione
            '25-12', // Natale
            '26-12', // Santo Stefano
        ];

        /*
            Se il ticket è stato in attesa almeno una volta bisogna calcolare il tempo totale in cui è rimasto in attesa.
        */

        // prendere prima lo status update di chiusura.
        // Se l'update di chiudura ha una creazione antecedente a quella del ticket exception
        // Selezionare tutti gli status update con data di creazione successivi alla data di creazione del ticket e precedenti alla data di creazione dell'update di chiusura

        // $statusUpdates = $this->statusUpdates()->whereIn('type', ['status', 'closing'])->get();
        
        // Implementazione della logica descritta nei commenti
        // 1. Trova l'update di chiusura più recente
        $closingUpdate = $this->statusUpdates()
            ->where('type', 'closing')
            ->orderBy('created_at', 'desc')
            ->first();
        
        // 2. Se esiste un update di chiusura, verifica che non sia antecedente alla creazione del ticket
        if ($closingUpdate && $closingUpdate->created_at->lessThan($this->created_at)) {
            // Update di chiusura antecedente al ticket - situazione anomala, ignora
            $closingUpdate = null;
        }
        
        // 3. Seleziona tutti gli status update tra la creazione del ticket e la chiusura
        $statusUpdatesQuery = $this->statusUpdates()
            ->whereIn('type', ['status', 'closing'])
            ->where('created_at', '>', $this->created_at);
            
        if ($closingUpdate) {
            // Se c'è una chiusura valida, limita fino a quella data
            $statusUpdatesQuery->where('created_at', '<=', $closingUpdate->created_at);
        }
        
        $statusUpdates = $statusUpdatesQuery->orderBy('created_at', 'asc')->get();


        // Visto che si deve calcolare l'attesa, prendo solo gli stati in cui è cambiato lo stato di is_sla_pause
        $filteredStatusUpdates = $statusUpdates->filter(function ($update) {
            return TicketStage::find($update->new_stage_id)?->is_sla_pause != TicketStage::find($update->old_stage_id)?->is_sla_pause;
        })->values(); // Reindicizza la collection per evitare errori di accesso agli indici

        $hasBeenWaiting = false;
        $waitingRecords = [];
        $waitingEndingRecords = [];
        $waitingMinutes = 0;

        // Verifica che ci siano aggiornamenti di stato da processare
        if ($filteredStatusUpdates->isEmpty()) {
            return 0;
        }

        for ($i = 0; $i < count($filteredStatusUpdates); $i++) {
            if (
                // (strpos(strtolower($statusUpdates[$i]->content), 'in attesa') !== false) || (strpos(strtolower($statusUpdates[$i]->content), 'risolto') !== false)
                TicketStage::find($filteredStatusUpdates[$i]->new_stage_id)?->is_sla_pause
            ) {
                $hasBeenWaiting = true;
                $waitingRecords[] = $filteredStatusUpdates[$i];
                // $waitingEndingRecords[] = $statusUpdates[$i + 1] ?? null;
                $waitingEndingRecords[] = $filteredStatusUpdates[$i + 1] ?? null;
            }
        }

        if ($hasBeenWaiting === false) {
            return 0;
        }

        for ($i = 0; $i < count($waitingRecords); $i++) {
            $start = $waitingRecords[$i]->created_at;
            $end = $waitingEndingRecords[$i] != null ? $waitingEndingRecords[$i]->created_at : now();
            $totalMinutes = $start->diffInMinutes($end);

            $excludedMinutes = 0;
            $current = $start->copy();

            while ($current->lessThan($end)) {
                $isExcludedDay = (! $includeSunday && $current->isSunday())
                    || (! $includeSaturday && $current->isSaturday())
                    || (! $includeHolidays && in_array($current->format('m-d'), $holidays));
                $isExcludedHour = ! $IncludeAllDayHours && ($current->hour >= 20 || $current->hour < 8);
                if ($isExcludedHour || $isExcludedDay) {
                    $excludedMinutes++;
                }
                $current->addMinute();
            }

            $waitingMinutes += ($totalMinutes - $excludedMinutes);
        }

        $waitingHours = $waitingMinutes / 60;

        return $waitingHours;
    }

    public function waitingTimes()
    {
        $statusUpdates = $this->statusUpdates()->where('type', 'status')->get();

        // Visto che si deve calcolare l'attesa, prendo solo gli stati in cui è cambiato lo stato di is_sla_pause
        $filteredStatusUpdates = $statusUpdates->filter(function ($update) {
            return TicketStage::find($update->new_stage_id)?->is_sla_pause != TicketStage::find($update->old_stage_id)?->is_sla_pause;
        })->values(); // Reindicizza la collection per evitare errori di accesso agli indici

        $hasBeenWaiting = false;
        $waitingRecords = [];
        $waitingEndingRecords = [];

        // Verifica che ci siano aggiornamenti di stato da processare
        if ($filteredStatusUpdates->isEmpty()) {
            return 0;
        }

        for ($i = 0; $i < count($filteredStatusUpdates); $i++) {
            if (
                // (strpos(strtolower($statusUpdates[$i]->content), 'in attesa') !== false) || (strpos(strtolower($statusUpdates[$i]->content), 'risolto') !== false)
                TicketStage::find($filteredStatusUpdates[$i]->new_stage_id)?->is_sla_pause
            ) {
                $hasBeenWaiting = true;
                // $waitingRecords[] = $statusUpdates[$i];
                // if (count($statusUpdates) > ($i + 1)) {
                //     $waitingEndingRecords[] = $statusUpdates[$i + 1];
                $waitingRecords[] = $filteredStatusUpdates[$i];
                if (count($filteredStatusUpdates) > ($i + 1)) {
                    $waitingEndingRecords[] = $filteredStatusUpdates[$i + 1];
                }
            }
        }

        if ($hasBeenWaiting === false) {
            return 0;
        }

        return count($waitingRecords);
    }

    public function parent()
    {
        return $this->belongsTo(Ticket::class, 'parent_ticket_id');
    }

    // public function children() {
    //     return $this->hasMany(Ticket::class, 'parent_ticket_id');
    // }

    // Dato che c'è già parent (e child/children non c'è ma sarebbe il suo corrispettivo),
    // per il collegamento tra ticket on site e ticket normali uso master (operazione strutturata) e slave (collegato ad operazione strutturata) (un ticket on site può avere da 0 a n ticket normali a lui collegati e questi due vengono trattati diversamente nel report).
    public function master()
    {
        return $this->belongsTo(Ticket::class, 'master_id');
    }

    public function slaves()
    {
        return $this->hasMany(Ticket::class, 'master_id');
    }

    public function schedulingMaster()
    {
        return $this->belongsTo(Ticket::class, 'scheduling_id');
    }
    public function schedulingSlaves()
    {
        return $this->hasMany(Ticket::class, 'scheduling_id');
    }

    public function reopenedParent()
    {
        return $this->belongsTo(Ticket::class, 'reopen_parent_id');
    }

    public function reopenedChild()
    {
        return $this->hasOne(Ticket::class, 'reopen_parent_id');
    }

    /**
     * Get all ticket reminders for this ticket.
     */
    public function ticketReminders()
    {
        return $this->hasMany(TicketReminder::class);
    }

    /**
     * Get deadline reminders for this ticket.
     */
    public function deadlineReminders()
    {
        return $this->hasMany(TicketReminder::class)->where('is_ticket_deadline', true);
    }

    /**
     * Get all ticket logs that involve this ticket.
     */
    public function ticketLogs()
    {
        return $this->belongsToMany(TicketLog::class, 'ticket_log_ticket', 'ticket_id', 'ticket_log_id')
                    ->withTimestamps()
                    ->orderBy('created_at', 'desc');
    }

    /**
     * Get the project that this ticket belongs to.
     */
    public function project()
    {
        return $this->belongsTo(Ticket::class, 'project_id');
    }

    /**
     * Get all tickets that belong to this project.
     */
    public function projectTickets()
    {
        return $this->hasMany(Ticket::class, 'project_id');
    }

    /**
     * Check if this ticket is a project.
     */
    public function isProject(): bool
    {
        return $this->ticketType && $this->ticketType->is_project;
    }

    /**
     * Check if this ticket belongs to a project.
     */
    public function belongsToProject(): bool
    {
        return !is_null($this->project_id);
    }

    public function timeToTake(): int
    {
        $createdAt = $this->created_at;
        $assignedAt = $this->statusUpdates()->where('type', 'assign')->first()?->created_at;
        
        // Se non c'è data di assegnazione, non possiamo calcolare il tempo
        if (!$assignedAt) {
            return 0;
        }
        
        $weeklyTimes = $this->company->weeklyTimes;
        
        // Se non ci sono orari definiti o tutti i giorni sono 00:00-00:00, usa il fallback
        if (!$weeklyTimes || $this->areAllDaysEmpty($weeklyTimes)) {
            $weeklyTimes = $this->getDefaultWeeklyTimes();
        }
        
        // Array delle festività italiane (formato DD-MM)
        $holidays = [
            '01-01', // 1 gennaio - Capodanno
            '06-01', // 6 gennaio - Epifania
            '25-04', // 25 aprile - Festa della Liberazione
            '01-05', // 1 maggio - Festa dei Lavoratori
            '02-06', // 2 giugno - Festa della Repubblica
            '15-08', // 15 agosto - Ferragosto
            '01-11', // 1 novembre - Ognissanti
            '08-12', // 8 dicembre - Immacolata Concezione
            '25-12', // 25 dicembre - Natale
            '26-12', // 26 dicembre - Santo Stefano
        ];
        
        $totalMinutes = 0;
        $current = $createdAt->copy();
        
        // Itera giorno per giorno fino alla data di assegnazione
        while ($current->format('Y-m-d') <= $assignedAt->format('Y-m-d')) {
            $dayOfWeek = $current->dayOfWeek; // 0 = domenica, 1 = lunedì, ..., 6 = sabato
            $dayKey = $this->getDayKeyFromDayOfWeek($dayOfWeek);
            
            // Verifica se è una festività (usa formato DD-MM per l'Italia)
            $isHoliday = in_array($current->format('d-m'), $holidays);
            
            if ($isHoliday) {
                $current->addDay()->startOfDay();
                continue;
            }
            
            // Verifica se ci sono orari validi per questo giorno
            $dayTimes = $weeklyTimes[$dayKey] ?? null;
            if (!$dayTimes) {
                $current->addDay()->startOfDay();
                continue;
            }
            
            $startTime = $dayTimes['start'] ?? null;
            $endTime = $dayTimes['end'] ?? null;
            
            // Se entrambi gli orari sono 00:00, il giorno non conta
            if ($startTime === '00:00' && $endTime === '00:00') {
                $current->addDay()->startOfDay();
                continue;
            }
            
            // Calcola l'inizio e la fine effettivi per questo giorno
            $dayStart = $current->copy()->startOfDay()->addHours((int)substr($startTime, 0, 2))->addMinutes((int)substr($startTime, 3, 2));
            $dayEnd = $current->copy()->startOfDay()->addHours((int)substr($endTime, 0, 2))->addMinutes((int)substr($endTime, 3, 2));
            
            // Determina il periodo effettivo da conteggiare per questo giorno
            $periodStart = $dayStart;
            $periodEnd = $dayEnd;
            
            // Se è il primo giorno, regola l'inizio in base a createdAt
            if ($current->format('Y-m-d') === $createdAt->format('Y-m-d')) {
                if ($createdAt->greaterThan($dayStart)) {
                    $periodStart = $createdAt->copy();
                } elseif ($createdAt->lessThan($dayStart)) {
                    // Se createdAt è prima dell'orario lavorativo, inizia dall'orario lavorativo
                    $periodStart = $dayStart;
                }
                
                // Se createdAt è dopo l'orario lavorativo, non conta niente per questo giorno
                if ($createdAt->greaterThan($dayEnd)) {
                    $current->addDay()->startOfDay();
                    continue;
                }
            }
            
            // Se è l'ultimo giorno, regola la fine in base ad assignedAt
            if ($current->format('Y-m-d') === $assignedAt->format('Y-m-d')) {
                if ($assignedAt->lessThan($dayEnd) && $assignedAt->greaterThan($dayStart)) {
                    $periodEnd = $assignedAt->copy();
                } elseif ($assignedAt->lessThan($dayStart)) {
                    // Se assignedAt è prima dell'orario lavorativo, non conta niente per questo giorno
                    $current->addDay()->startOfDay();
                    continue;
                }
                // Se assignedAt è dopo l'orario lavorativo, usa tutto l'orario lavorativo
            }
            
            // Calcola i minuti per questo periodo
            if ($periodStart->lessThan($periodEnd)) {
                $totalMinutes += $periodStart->diffInMinutes($periodEnd);
            }
            
            $current->addDay()->startOfDay();
        }
        
        // Restituisce i minuti totali
        return $totalMinutes;
    }
    
    /**
     * Converte il numero del giorno della settimana nella chiave utilizzata in weeklyTimes
     */
    private function getDayKeyFromDayOfWeek(int $dayOfWeek): string
    {
        $dayMap = [
            0 => 'sunday',
            1 => 'monday', 
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
        ];
        
        return $dayMap[$dayOfWeek];
    }
    
    /**
     * Verifica se tutti i giorni in weeklyTimes hanno orari 00:00-00:00
     */
    private function areAllDaysEmpty($weeklyTimes): bool
    {
        if (!$weeklyTimes || !is_array($weeklyTimes)) {
            return true;
        }
        
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            $dayTimes = $weeklyTimes[$day] ?? null;
            if ($dayTimes && 
                isset($dayTimes['start']) && isset($dayTimes['end']) &&
                !($dayTimes['start'] === '00:00' && $dayTimes['end'] === '00:00')) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Restituisce gli orari di fallback: lun-ven 09:00-18:00, sab-dom 00:00-00:00
     */
    private function getDefaultWeeklyTimes(): array
    {
        return [
            'monday' => ['start' => '09:00', 'end' => '18:00'],
            'tuesday' => ['start' => '09:00', 'end' => '18:00'],
            'wednesday' => ['start' => '09:00', 'end' => '18:00'],
            'thursday' => ['start' => '09:00', 'end' => '18:00'],
            'friday' => ['start' => '09:00', 'end' => '18:00'],
            'saturday' => ['start' => '00:00', 'end' => '00:00'],
            'sunday' => ['start' => '00:00', 'end' => '00:00'],
        ];
    }

}

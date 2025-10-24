<?php

// MAI PROVATO. DA MODIFICARE E TESTARE.

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketStage;
use App\Models\TicketStatusUpdate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateTicketSLA extends Command
{
    /**
     * Il nome e la firma del comando.
     *
     * @var string
     */
    protected $signature = 'tickets:calculate-sla 
                            {--start-hour=9 : Ora di inizio giorno lavorativo}
                            {--end-hour=18 : Ora di fine giorno lavorativo}
                            {--include-weekends=false : Considerare weekend come giorni lavorativi per tutti i ticket}
                            {--include-holidays=false : Considerare festività come giorni lavorativi per tutti i ticket}';

    /**
     * La descrizione del comando.
     *
     * @var string
     */
    protected $description = 'Calcola la conformità SLA per i ticket attivi basandosi sulle ore lavorative e periodi di attesa';

    /**
     * Festività italiane (formato MM-DD)
     */
    protected $holidays = [
        '01-01', // Capodanno
        '01-06', // Epifania
        '04-25', // Festa della Liberazione
        '05-01', // Festa del Lavoro
        '06-02', // Festa della Repubblica
        '08-15', // Ferragosto
        '11-01', // Tutti i Santi
        '12-08', // Immacolata Concezione
        '12-25', // Natale
        '12-26', // Santo Stefano
    ];

    /**
     * Aggiungi festività pasquali dinamiche per l'anno corrente e successivo
     */
    protected function getEasterHolidays(): array
    {
        $currentYear = Carbon::now()->year;
        $easterDates = [];

        // Calcola Pasqua e Pasquetta per anno corrente
        $easter = Carbon::createFromTimestamp(easter_date($currentYear));
        $easterMonday = (clone $easter)->addDay();
        $easterDates[] = $easter->format('m-d');
        $easterDates[] = $easterMonday->format('m-d');

        // Calcola per l'anno successivo
        $nextYear = $currentYear + 1;
        $nextEaster = Carbon::createFromTimestamp(easter_date($nextYear));
        $nextEasterMonday = (clone $nextEaster)->addDay();
        $easterDates[] = $nextEaster->format('m-d');
        $easterDates[] = $nextEasterMonday->format('m-d');

        return $easterDates;
    }

    /**
     * Esegui il comando.
     */
    public function handle()
    {
        $startHour = (int) $this->option('start-hour');
        $endHour = (int) $this->option('end-hour');
        $includeWeekends = $this->option('include-weekends') === 'true';
        $includeHolidays = $this->option('include-holidays') === 'true';

        // Unisci le festività fisse con quelle pasquali
        $allHolidays = array_merge($this->holidays, $this->getEasterHolidays());

        $this->info('Avvio calcolo SLA per i ticket...');
        $closedStageId = TicketStage::where('system_key', 'closed')->first()?->id;
        // Recupera tutti i ticket attivi che devono essere valutati
        // $tickets = Ticket::whereNotIn('status', ['closed', 'cancelled']) // Cancelled?
        $tickets = Ticket::whereNotIn('stage_id', [$closedStageId])
            ->whereNotNull('sla_take')
            ->whereNotNull('sla_solve')
            ->get();

        $this->info("Trovati {$tickets->count()} ticket da valutare.");

        $progressBar = $this->output->createProgressBar($tickets->count());
        $progressBar->start();

        foreach ($tickets as $ticket) {
            // Determina se è un ticket critico che richiede calcolo SLA 24/7
            $isCritical = $ticket->priority === 'critical';

            // Se il ticket è critico, ignora la configurazione di default
            $considerWeekends = $isCritical ? true : $includeWeekends;
            $considerHolidays = $isCritical ? true : $includeHolidays;

            // Calcola il tempo di presa in carico
            $createdAt = Carbon::parse($ticket->created_at);
            $takenAt = $this->findTicketTakenTime($ticket);

            if ($takenAt) {
                // Calcola tempo effettivo considerando orario lavorativo
                $takenTime = $this->calculateWorkingTime(
                    $createdAt,
                    $takenAt,
                    $startHour,
                    $endHour,
                    $considerWeekends,
                    $considerHolidays,
                    $allHolidays
                );

                // Verifica SLA per presa in carico (sla_take è in ore)
                $slaMinutesTake = $ticket->sla_take * 60;
                $isTakeInSla = $takenTime <= $slaMinutesTake;

                // Aggiorna lo stato SLA del ticket
                $ticket->is_take_in_sla = $isTakeInSla;
            } else {
                // Ticket non ancora preso in carico
                $now = Carbon::now();

                // Calcola tempo effettivo considerando orario lavorativo
                $takenTime = $this->calculateWorkingTime(
                    $createdAt,
                    $now,
                    $startHour,
                    $endHour,
                    $considerWeekends,
                    $considerHolidays,
                    $allHolidays
                );

                // Verifica SLA per presa in carico (sla_take è in ore)
                $slaMinutesTake = $ticket->sla_take * 60;
                $isTakeInSla = $takenTime <= $slaMinutesTake;

                // Aggiorna lo stato SLA del ticket
                $ticket->is_take_in_sla = $isTakeInSla;
            }

            // Ora calcoliamo la SLA per la risoluzione
            $resolvedAt = $this->findTicketResolvedTime($ticket);

            // Ottieni tutti i periodi speciali (in attesa e risolto)
            $specialPeriods = $this->getSpecialPeriods($ticket);

            if ($resolvedAt) {
                // Calcola tempo effettivo escludendo periodi speciali e considerando orario lavorativo
                $resolutionTime = $this->calculateResolutionTime(
                    $createdAt,
                    $resolvedAt,
                    $specialPeriods,
                    $startHour,
                    $endHour,
                    $considerWeekends,
                    $considerHolidays,
                    $allHolidays
                );

                // Verifica SLA per risoluzione (sla_solve è in ore)
                $slaMinutesSolve = $ticket->sla_solve * 60;
                $isSolveInSla = $resolutionTime <= $slaMinutesSolve;

                // Aggiorna lo stato SLA del ticket
                $ticket->is_solve_in_sla = $isSolveInSla;
            } else {
                // Ticket non ancora risolto
                $now = Carbon::now();

                // Calcola tempo effettivo escludendo periodi speciali e considerando orario lavorativo
                $resolutionTime = $this->calculateResolutionTime(
                    $createdAt,
                    $now,
                    $specialPeriods,
                    $startHour,
                    $endHour,
                    $considerWeekends,
                    $considerHolidays,
                    $allHolidays
                );

                // Verifica SLA per risoluzione (sla_solve è in ore)
                $slaMinutesSolve = $ticket->sla_solve * 60;
                $isSolveInSla = $resolutionTime <= $slaMinutesSolve;

                // Aggiorna lo stato SLA del ticket
                $ticket->is_solve_in_sla = $isSolveInSla;
            }

            // Salva i risultati del ticket
            $ticket->last_sla_check = Carbon::now();
            $ticket->save();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info('Calcolo SLA completato con successo.');
    }

    /**
     * Trova il momento in cui il ticket è stato preso in carico.
     */
    protected function findTicketTakenTime(Ticket $ticket): ?Carbon
    {
        // Questo dipende dalla logica specifica del tuo sistema
        // Ad esempio, potrebbe essere il primo aggiornamento di stato diverso da "new"
        $firstStatusUpdate = TicketStatusUpdate::where('ticket_id', $ticket->id)
            ->whereHas('newStage', function ($query) {
                $query->where('system_key', '!=', 'new');
            })
            ->orderBy('created_at')
            ->first();

        return $firstStatusUpdate ? Carbon::parse($firstStatusUpdate->created_at) : null;
    }

    /**
     * Trova il momento in cui il ticket è stato risolto.
     */
    protected function findTicketResolvedTime(Ticket $ticket): ?Carbon
    {
        $resolvedStatusUpdate = TicketStatusUpdate::where('ticket_id', $ticket->id)
            ->whereRaw("content LIKE '%Risolto%'")
            ->orderBy('created_at')
            ->first();

        return $resolvedStatusUpdate ? Carbon::parse($resolvedStatusUpdate->created_at) : null;
    }

    /**
     * Ottiene tutti i periodi speciali (in attesa e risolto) del ticket.
     * Ritorna un array di array [inizio, fine, tipo] dove inizio e fine sono oggetti Carbon
     * e tipo può essere 'waiting' o 'resolved'.
     */
    protected function getSpecialPeriods(Ticket $ticket): array
    {
        $specialPeriods = [];
        $statusUpdates = TicketStatusUpdate::where('ticket_id', $ticket->id)
            ->orderBy('created_at')
            ->get();

        $currentPeriod = null;
        $currentType = null;

        // Se non ci sono aggiornamenti di stato, restituisci un array vuoto
        if ($statusUpdates->isEmpty()) {
            return [];
        }

        for ($i = 0; $i < $statusUpdates->count(); $i++) {
            $update = $statusUpdates[$i];
            $content = $update->content;

            // Controlla se l'aggiornamento contiene "In attesa"
            $isWaiting = strpos($content, 'In attesa') !== false;

            // Controlla se l'aggiornamento contiene "Risolto"
            $isResolved = strpos($content, 'Risolto') !== false;

            // Se stiamo iniziando un nuovo periodo speciale
            if (($isWaiting || $isResolved) && $currentPeriod === null) {
                $currentPeriod = Carbon::parse($update->created_at);
                $currentType = $isWaiting ? 'waiting' : 'resolved';
            }
            // Se stiamo già in un periodo speciale e troviamo un altro aggiornamento
            elseif ($currentPeriod !== null && $i < $statusUpdates->count() - 1) {
                // Se passiamo da "In attesa" a "Risolto", manteniamo il periodo di attesa
                // altrimenti chiudiamo il periodo corrente
                $nextUpdate = $statusUpdates[$i + 1];
                $nextContent = $nextUpdate->content;
                $nextIsWaiting = strpos($nextContent, 'In attesa') !== false;
                $nextIsResolved = strpos($nextContent, 'Risolto') !== false;

                // Caso speciale: da "In attesa" a "Risolto", continuiamo il periodo di attesa
                if ($currentType === 'waiting' && $isResolved) {
                    $currentType = 'waiting'; // Manteniamo il tipo come 'waiting'

                    continue; // Non chiudiamo il periodo, continuiamo a cercarne la fine
                }

                // Se l'aggiornamento successivo non è speciale, chiudiamo il periodo corrente
                if (! $nextIsWaiting && ! $nextIsResolved) {
                    $periodEnd = Carbon::parse($nextUpdate->created_at);
                    $specialPeriods[] = [$currentPeriod, $periodEnd, $currentType];
                    $currentPeriod = null;
                    $currentType = null;
                }
            }
        }

        // Se siamo ancora in un periodo speciale alla fine, lo chiudiamo con l'ora corrente
        if ($currentPeriod !== null) {
            $specialPeriods[] = [$currentPeriod, Carbon::now(), $currentType];
        }

        return $specialPeriods;
    }

    /**
     * Calcola il tempo lavorativo tra due date in minuti.
     */
    protected function calculateWorkingTime(
        Carbon $start,
        Carbon $end,
        int $startHour,
        int $endHour,
        bool $includeWeekends,
        bool $includeHolidays,
        array $holidays
    ): int {
        // Se le date sono uguali o invertite, restituisci 0
        if ($start->greaterThanOrEqualTo($end)) {
            return 0;
        }

        $totalMinutes = 0;
        $current = clone $start;

        while ($current->lessThan($end)) {
            // Controlla se oggi è un giorno lavorativo
            $isWeekend = in_array($current->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);
            $isHoliday = in_array($current->format('m-d'), $holidays);

            $isWorkingDay = true;

            if (! $includeWeekends && $isWeekend) {
                $isWorkingDay = false;
            }

            if (! $includeHolidays && $isHoliday) {
                $isWorkingDay = false;
            }

            if ($isWorkingDay) {
                // Calcola inizio e fine della giornata lavorativa
                $dayStart = $current->copy()->setHour($startHour)->setMinute(0)->setSecond(0);
                $dayEnd = $current->copy()->setHour($endHour)->setMinute(0)->setSecond(0);

                // Aggiusta l'inizio se necessario
                if ($current->greaterThan($dayStart) && $current->lessThan($dayEnd)) {
                    $dayStart = $current;
                } elseif ($current->greaterThanOrEqualTo($dayEnd)) {
                    // Siamo oltre l'orario lavorativo, passa al giorno successivo
                    $current->addDay()->setHour(0)->setMinute(0)->setSecond(0);

                    continue;
                }

                // Aggiusta la fine se necessario
                if ($end->lessThan($dayEnd) && $end->greaterThan($dayStart) && $end->isSameDay($current)) {
                    $dayEnd = $end;
                }

                // Se stiamo lavorando oggi, aggiungi i minuti
                if ($dayStart->lessThan($dayEnd)) {
                    $totalMinutes += $dayStart->diffInMinutes($dayEnd);
                }
            }

            // Passa al giorno successivo
            $current->addDay()->setHour(0)->setMinute(0)->setSecond(0);
        }

        return $totalMinutes;
    }

    /**
     * Calcola il tempo di risoluzione escludendo i periodi speciali.
     */
    protected function calculateResolutionTime(
        Carbon $start,
        Carbon $end,
        array $specialPeriods,
        int $startHour,
        int $endHour,
        bool $includeWeekends,
        bool $includeHolidays,
        array $holidays
    ): int {
        // Calcola il tempo lavorativo totale
        $totalWorkingTime = $this->calculateWorkingTime(
            $start,
            $end,
            $startHour,
            $endHour,
            $includeWeekends,
            $includeHolidays,
            $holidays
        );

        // Sottrai il tempo dei periodi speciali (solo le parti che cadono in orario lavorativo)
        $totalSpecialTime = 0;

        foreach ($specialPeriods as [$periodStart, $periodEnd, $periodType]) {
            // Solo se il periodo è entro l'intervallo start-end
            if ($periodStart->lessThan($end) && $periodEnd->greaterThan($start)) {
                // Aggiusta l'inizio e la fine del periodo se necessario
                $adjustedStart = $periodStart->lessThan($start) ? $start : $periodStart;
                $adjustedEnd = $periodEnd->greaterThan($end) ? $end : $periodEnd;

                $specialWorkingTime = $this->calculateWorkingTime(
                    $adjustedStart,
                    $adjustedEnd,
                    $startHour,
                    $endHour,
                    $includeWeekends,
                    $includeHolidays,
                    $holidays
                );

                $totalSpecialTime += $specialWorkingTime;
            }
        }

        return max(0, $totalWorkingTime - $totalSpecialTime);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\TicketStatusUpdate;
use App\Services\TicketStatusBridge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Comando Artisan per migrare SOLO I DATI dei TicketStatusUpdate dallo status testuale legacy al nuovo sistema con stage_id
// Migra i campi old_stage_id e new_stage_id basandosi sulla sequenza cronologica dei cambi di stato per ticket
//
// SEQUENZA COMPLETA:
// 1. php artisan migrate                                     # Crea tabelle e colonne
// 2. php artisan db:seed --class=TicketStageSeeder          # Crea i 7 stage predefiniti
// 3. php artisan tickets:migrate-stages --force             # Migra i ticket
// 4. php artisan tickets:migrate-status-updates --dry-run   # Test migrazione status updates
// 5. php artisan tickets:migrate-status-updates --force     # Migrazione status updates effettiva
//
// COMANDI SINGOLI:
// TEST: php artisan tickets:migrate-status-updates --dry-run
// MIGRAZIONE EFFETTIVA: php artisan tickets:migrate-status-updates --chunk=500
// MIGRAZIONE CON CONFERMA AUTOMATICA: php artisan tickets:migrate-status-updates --force --chunk=1000

class MigrateTicketStatusUpdateValues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:migrate-status-updates 
                            {--chunk=1000 : Number of status updates to process per batch}
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate TicketStatusUpdate records from legacy status text to new stage_id system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting TicketStatusUpdate Migration...');
        
        // Verifica prerequisiti
        if (!$this->verifyPrerequisites()) {
            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Statistiche iniziali
        $stats = $this->getInitialStats();
        $this->displayInitialStats($stats);

        // Conferma utente (se non in dry-run o force)
        if (!$isDryRun && !$force && !$this->confirm('Proceed with migration?')) {
            $this->info('Migration cancelled.');
            return self::SUCCESS;
        }

        // Esegui migrazione
        $result = $this->performMigration($chunkSize, $isDryRun);

        // Statistiche finali
        $this->displayFinalStats($result);

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Verifica che tutti i prerequisiti siano soddisfatti
     */
    private function verifyPrerequisites(): bool
    {
        $this->info('🔍 Verifying prerequisites...');

        // Verifica esistenza colonne stage_id
        if (!$this->columnExists('ticket_status_updates', 'old_stage_id')) {
            $this->error('❌ Column `old_stage_id` not found in ticket_status_updates table. Run migration first.');
            return false;
        }

        if (!$this->columnExists('ticket_status_updates', 'new_stage_id')) {
            $this->error('❌ Column `new_stage_id` not found in ticket_status_updates table. Run migration first.');
            return false;
        }

        // Verifica mapping completo
        $bridgeStats = TicketStatusBridge::getMigrationStats();
        if (!$bridgeStats['mapping_complete']) {
            $this->error('❌ Incomplete status mapping. Check TicketStatusBridge configuration.');
            $this->line('Missing mappings: ' . $bridgeStats['unmapped_statuses']);
            $this->newLine();
            $this->warn('💡 If stages are missing, run: php artisan db:seed --class=TicketStageSeeder');
            return false;
        }

        $this->info('✅ All prerequisites satisfied.');
        return true;
    }

    /**
     * Ottiene statistiche iniziali
     */
    private function getInitialStats(): array
    {
        $targetTypes = ['status', 'closing'];
        
        return [
            'total_status_updates' => TicketStatusUpdate::whereIn('type', $targetTypes)->count(),
            'already_migrated' => TicketStatusUpdate::whereIn('type', $targetTypes)
                ->whereNotNull('new_stage_id')->count(),
            'need_migration' => TicketStatusUpdate::whereIn('type', $targetTypes)
                ->whereNull('new_stage_id')->count(),
            'bridge_stats' => TicketStatusBridge::getMigrationStats(),
            'affected_tickets' => TicketStatusUpdate::whereIn('type', $targetTypes)
                ->distinct('ticket_id')->count('ticket_id')
        ];
    }

    /**
     * Mostra statistiche iniziali
     */
    private function displayInitialStats(array $stats): void
    {
        $this->info('📊 Current State:');
        $this->line("Status updates (status/closing): {$stats['total_status_updates']}");
        $this->line("Already migrated: {$stats['already_migrated']}");
        $this->line("Need migration: {$stats['need_migration']}");
        $this->line("Affected tickets: {$stats['affected_tickets']}");
        $this->line("Bridge mappings: {$stats['bridge_stats']['mapped_statuses']}/{$stats['bridge_stats']['total_legacy_statuses']}");
        $this->newLine();
    }

    /**
     * Esegue la migrazione effettiva
     */
    private function performMigration(int $chunkSize, bool $isDryRun): array
    {
        $totalProcessed = 0;
        $totalUpdated = 0;
        $errors = [];
        $warnings = [];

        $this->info($isDryRun ? '🔍 DRY RUN - No changes will be made' : '⚡ Starting migration...');

        // Ottieni tutti i ticket che hanno almeno un status update incompleto
        $ticketIds = TicketStatusUpdate::whereIn('type', ['status', 'closing'])
            ->where(function ($query) {
                $query->whereNull('old_stage_id')
                      ->orWhereNull('new_stage_id');
            })
            ->distinct('ticket_id')
            ->pluck('ticket_id');

        if ($ticketIds->isEmpty()) {
            $this->info('✅ No ticket status updates need migration.');
            return ['success' => true, 'processed' => 0, 'updated' => 0, 'errors' => [], 'warnings' => []];
        }

        $progressBar = $this->output->createProgressBar($ticketIds->count());
        $progressBar->start();

        // Processa ticket per ticket
        foreach ($ticketIds->chunk($chunkSize) as $ticketChunk) {
            foreach ($ticketChunk as $ticketId) {
                $result = $this->migrateTicketStatusUpdates($ticketId, $isDryRun);
                
                $totalProcessed += $result['processed'];
                $totalUpdated += $result['updated'];
                $errors = array_merge($errors, $result['errors']);
                $warnings = array_merge($warnings, $result['warnings']);
                
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        return [
            'success' => count($errors) === 0,
            'processed' => $totalProcessed,
            'updated' => $totalUpdated,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Migra gli status updates per un singolo ticket
     */
    private function migrateTicketStatusUpdates(int $ticketId, bool $isDryRun): array
    {
        $processed = 0;
        $updated = 0;
        $errors = [];
        $warnings = [];

        // Ottieni TUTTI gli status updates del ticket di tipo status/closing in ordine cronologica
        // Non filtriamo per NULL perché dobbiamo ricostruire l'intera sequenza
        $statusUpdates = TicketStatusUpdate::where('ticket_id', $ticketId)
            ->whereIn('type', ['status', 'closing'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($statusUpdates->isEmpty()) {
            return ['processed' => 0, 'updated' => 0, 'errors' => [], 'warnings' => []];
        }

        $previousStageId = $this->getInitialStageId(); // Stage "new" come punto di partenza

        foreach ($statusUpdates as $statusUpdate) {
            $processed++;
            
            try {
                // Determina il nuovo stage_id dal valore corrente
                $newStageId = $this->determineNewStageId($statusUpdate);
                
                if ($newStageId === null) {
                    $errors[] = "TicketStatusUpdate ID {$statusUpdate->id} (Ticket {$ticketId}): Cannot determine stage for content '{$statusUpdate->content}'";
                    continue;
                }

                $oldStageId = $previousStageId;

                // Controlla se i valori sono già corretti
                $needsUpdate = $statusUpdate->old_stage_id !== $oldStageId || 
                              $statusUpdate->new_stage_id !== $newStageId;

                if ($needsUpdate && !$isDryRun) {
                    // Aggiorna il record solo se necessario
                    $affected = DB::table('ticket_status_updates')
                        ->where('id', $statusUpdate->id)
                        ->update([
                            'old_stage_id' => $oldStageId,
                            'new_stage_id' => $newStageId
                        ]);

                    if ($affected > 0) {
                        $updated++;
                    } else {
                        $warnings[] = "TicketStatusUpdate ID {$statusUpdate->id}: Update failed - record may have been modified";
                    }
                } elseif ($needsUpdate && $isDryRun) {
                    $updated++; // Simula per dry run
                } else {
                    // Valori già corretti, non serve aggiornare
                    if (!$needsUpdate) {
                        $warnings[] = "TicketStatusUpdate ID {$statusUpdate->id}: Values already correct - skipped";
                    }
                }

                // Il nuovo stage diventa il precedente per il prossimo update
                $previousStageId = $newStageId;

            } catch (\Exception $e) {
                $errors[] = "TicketStatusUpdate ID {$statusUpdate->id} (Ticket {$ticketId}): " . $e->getMessage();
            }
        }

        return [
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Ottiene lo stage_id iniziale (nuovo ticket)
     */
    private function getInitialStageId(): int
    {
        // Assume che lo stage "new" abbia ID 1, oppure usa il bridge per trovarlo
        return TicketStatusBridge::getStageIdFromLegacyStatus(0) ?? 1; // 0 = new status
    }

    /**
     * Determina il new_stage_id dal valore del TicketStatusUpdate
     */
    private function determineNewStageId(TicketStatusUpdate $statusUpdate): ?int
    {
        if ($statusUpdate->type === 'closing') {
            // Status update di tipo "closing" -> stage "closed"
            return TicketStatusBridge::getStageIdFromLegacyStatus(5) ?? null; // 5 = closed
        }

        // Per type "status", estrai il nome dello status dal content
        if ($statusUpdate->type === 'status') {
            $statusName = $this->extractStatusNameFromContent($statusUpdate->content);
            if ($statusName) {
                $legacyStatus = $this->getStatusIdFromName($statusName);
                if ($legacyStatus !== null) {
                    return TicketStatusBridge::getStageIdFromLegacyStatus($legacyStatus);
                }
            }
        }

        return null;
    }

    /**
     * Estrae il nome dello status dal contenuto del TicketStatusUpdate
     */
    private function extractStatusNameFromContent(?string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        // Lista dei possibili nomi di status da cercare nel content
        $statusNames = [
            'nuovo',
            'assegnato', 
            'in corso',
            'in attesa',
            'risolto',
            'chiuso',
            'attesa feedback cliente'
        ];

        $contentLower = strtolower($content);

        // Cerca ogni nome di status nel content
        foreach ($statusNames as $statusName) {
            if (str_contains($contentLower, $statusName)) {
                return $statusName;
            }
        }

        return null;
    }

    /**
     * Ottiene l'ID numerico legacy dal nome dello status
     */
    private function getStatusIdFromName(string $statusName): ?int
    {
        $statusMap = [
            'nuovo' => 0,
            'assegnato' => 1,
            'in corso' => 2,
            'in attesa' => 3,
            'risolto' => 4,
            'chiuso' => 5,
            'attesa feedback cliente' => 6,
        ];

        return $statusMap[strtolower(trim($statusName))] ?? null;
    }

    /**
     * Mostra statistiche finali
     */
    private function displayFinalStats(array $result): void
    {
        $this->newLine();
        $this->info('📈 Migration Results:');
        $this->line("Status updates processed: {$result['processed']}");
        $this->line("Status updates updated: {$result['updated']}");
        $this->line("Errors: " . count($result['errors']));
        $this->line("Warnings: " . count($result['warnings'] ?? []));

        // Mostra warnings (non bloccanti)
        if (!empty($result['warnings'])) {
            $this->newLine();
            $this->warn('⚠️  Warnings (non-blocking):');
            foreach (array_slice($result['warnings'], 0, 5) as $warning) {
                $this->line("  • $warning");
            }
            if (count($result['warnings']) > 5) {
                $this->line("  • ... and " . (count($result['warnings']) - 5) . " more warnings");
            }
        }

        // Mostra errori (bloccanti)
        if (!empty($result['errors'])) {
            $this->newLine();
            $this->error('❌ Errors occurred:');
            foreach (array_slice($result['errors'], 0, 5) as $error) {
                $this->line("  • $error");
            }
            if (count($result['errors']) > 5) {
                $this->line("  • ... and " . (count($result['errors']) - 5) . " more errors");
            }
        }

        if ($result['success']) {
            $this->newLine();
            $this->info('✅ Migration completed successfully!');
            if (!empty($result['warnings'])) {
                $this->warn('⚠️  Some warnings occurred but migration was successful.');
            }
            $this->newLine();
            $this->info('🎯 Next steps:');
            $this->line('   • Verify data integrity: Check some status updates manually');
            $this->line('   • If all looks good, modify DB structure (NOT NULL, indices)');
            $this->line('   • Update application code to use new stage system');
        } else {
            $this->newLine();
            $this->error('❌ Migration completed with errors.');
            $this->line('   • Review errors above and fix data issues');
            $this->line('   • Re-run migration after fixing issues');
        }
    }

    /**
     * Verifica se una colonna esiste nella tabella
     */
    private function columnExists(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
}

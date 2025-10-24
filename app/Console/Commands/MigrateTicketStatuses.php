<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\TicketStatusBridge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Comando Artisan per migrare SOLO I DATI dei ticket dallo status numerico legacy al nuovo sistema con TicketStage
// La struttura del database (NOT NULL, indici) va modificata separatamente dopo aver verificato i dati. Stessa cosa per il campo status del ticket (da eliminare)
//
// SEQUENZA COMPLETA:
// 1. php artisan migrate                                     # Crea tabelle e colonne
// 2. php artisan db:seed --class=TicketStageSeeder          # Crea i 7 stage predefiniti
// 3. php artisan tickets:migrate-stages --dry-run           # Test migrazione dati
// 4. php artisan tickets:migrate-stages --force --chunk=1000 # Migrazione dati effettiva
//
// COMANDI SINGOLI:
// TEST: <Prefisso per avviare il comando in docker o quello che è> php artisan tickets:migrate-stages --dry-run
// MIGRAZIONE EFFETTIVA: <Prefisso per avviare il comando in docker o quello che è> php artisan tickets:migrate-stages --chunk=500
// MIGRAZIONE EFFETTIVA, CON CONFERMA AUTOMATICA: <Prefisso per avviare il comando in docker o quello che è> php artisan tickets:migrate-stages --force --chunk=1000

class MigrateTicketStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:migrate-stages 
                            {--chunk=1000 : Number of tickets to process per batch}
                            {--dry-run : Show what would be migrated without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate tickets from legacy status numbers to new TicketStage system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting Ticket Status Migration...');

        // Verifica prerequisiti
        if (! $this->verifyPrerequisites()) {
            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Statistiche iniziali
        $stats = $this->getInitialStats();
        $this->displayInitialStats($stats);

        // Conferma utente (se non in dry-run o force)
        if (! $isDryRun && ! $force && ! $this->confirm('Proceed with migration?')) {
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

        // Verifica esistenza colonna stage_id
        if (! $this->columnExists('tickets', 'stage_id')) {
            $this->error('❌ Column `stage_id` not found in tickets table. Run migration first.');

            return false;
        }

        // Verifica mapping completo
        $bridgeStats = TicketStatusBridge::getMigrationStats();
        if (! $bridgeStats['mapping_complete']) {
            $this->error('❌ Incomplete status mapping. Check TicketStatusBridge configuration.');
            $this->line('Missing mappings: '.$bridgeStats['unmapped_statuses']);
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
        return [
            'total_tickets' => Ticket::count(),
            'tickets_with_stage_id' => Ticket::whereNotNull('stage_id')->count(),
            'tickets_without_stage_id' => Ticket::whereNull('stage_id')->count(),
            'bridge_stats' => TicketStatusBridge::getMigrationStats(),
        ];
    }

    /**
     * Mostra statistiche iniziali
     */
    private function displayInitialStats(array $stats): void
    {
        $this->info('📊 Current State:');
        $this->line("Total tickets: {$stats['total_tickets']}");
        $this->line("Already migrated: {$stats['tickets_with_stage_id']}");
        $this->line("Need migration: {$stats['tickets_without_stage_id']}");
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

        // Query per ticket che necessitano migrazione
        $query = Ticket::whereNull('stage_id')->whereNotNull('status');
        $totalToProcess = $query->count();

        if ($totalToProcess === 0) {
            $this->info('✅ No tickets need migration.');

            return ['success' => true, 'processed' => 0, 'updated' => 0, 'errors' => [], 'warnings' => []];
        }

        $progressBar = $this->output->createProgressBar($totalToProcess);
        $progressBar->start();

        // Processa in chunk
        $query->chunk($chunkSize, function ($tickets) use (&$totalProcessed, &$totalUpdated, &$errors, &$warnings, $isDryRun, $progressBar) {
            foreach ($tickets as $ticket) {
                $totalProcessed++;

                // Verifica validità del status
                if (! is_numeric($ticket->status)) {
                    $warnings[] = "Ticket ID {$ticket->id}: Non-numeric status '{$ticket->status}' - skipping";
                    $progressBar->advance();

                    continue;
                }

                $legacyStatus = (int) $ticket->status;
                $stageId = TicketStatusBridge::getStageIdFromLegacyStatus($legacyStatus);

                if ($stageId) {
                    if (! $isDryRun) {
                        try {
                            // Usa query builder diretto per evitare problemi con model events
                            $affected = DB::table('tickets')
                                ->where('id', $ticket->id)
                                ->whereNull('stage_id') // Extra safety: aggiorna solo se ancora NULL
                                ->update(['stage_id' => $stageId]);

                            if ($affected > 0) {
                                $totalUpdated++;
                            } else {
                                $warnings[] = "Ticket ID {$ticket->id}: Already had stage_id set - skipped";
                            }
                        } catch (\Exception $e) {
                            $errors[] = "Ticket ID {$ticket->id}: ".$e->getMessage();
                        }
                    } else {
                        $totalUpdated++; // Simula per dry run
                    }
                } else {
                    $errors[] = "Ticket ID {$ticket->id}: No mapping found for status {$legacyStatus}";
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        return [
            'success' => count($errors) === 0,
            'processed' => $totalProcessed,
            'updated' => $totalUpdated,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Mostra statistiche finali
     */
    private function displayFinalStats(array $result): void
    {
        $this->newLine();
        $this->info('📈 Migration Results:');
        $this->line("Tickets processed: {$result['processed']}");
        $this->line("Tickets updated: {$result['updated']}");
        $this->line('Errors: '.count($result['errors']));
        $this->line('Warnings: '.count($result['warnings'] ?? []));

        // Mostra warnings (non bloccanti)
        if (! empty($result['warnings'])) {
            $this->newLine();
            $this->warn('⚠️  Warnings (non-blocking):');
            foreach (array_slice($result['warnings'], 0, 5) as $warning) {
                $this->line("  • $warning");
            }
            if (count($result['warnings']) > 5) {
                $this->line('  • ... and '.(count($result['warnings']) - 5).' more warnings');
            }
        }

        // Mostra errori (bloccanti)
        if (! empty($result['errors'])) {
            $this->newLine();
            $this->error('❌ Errors occurred:');
            foreach (array_slice($result['errors'], 0, 5) as $error) {
                $this->line("  • $error");
            }
            if (count($result['errors']) > 5) {
                $this->line('  • ... and '.(count($result['errors']) - 5).' more errors');
            }
        }

        if ($result['success']) {
            $this->newLine();
            $this->info('✅ Migration completed successfully!');
            if (! empty($result['warnings'])) {
                $this->warn('⚠️  Some warnings occurred but migration was successful.');
            }
            $this->newLine();
            $this->info('🎯 Next steps:');
            $this->line('   • Verify data integrity: Check some tickets manually');
            $this->line('   • If all looks good, modify DB structure (NOT NULL, indices)');
            $this->line('   • Update application code to use new stage system');
            $this->newLine();
            $this->info('💡 Remember: If stages are missing, run first:');
            $this->line('   • php artisan db:seed --class=TicketStageSeeder');
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

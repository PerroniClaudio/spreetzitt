<?php

namespace App\Services;

use App\Models\TicketStage;
use Illuminate\Support\Facades\Cache;

/**
 * Bridge per la migrazione dal vecchio sistema di status numerici
 * al nuovo sistema con TicketStage.
 *
 * Questo helper gestisce la conversione bidirezionale tra:
 * - Status numerici (indici array da config/app.php)
 * - TicketStage objects/IDs
 */
class TicketStatusBridge
{
    /**
     * Mapping dal vecchio sistema al nuovo.
     * Basato su config('app.ticket_stages') che usa indici 0-based.
     */
    private static array $legacyToNewMapping = [
        0 => 'Nuovo',                    // Index 0 -> "Nuovo"
        1 => 'Assegnato',               // Index 1 -> "Assegnato"
        2 => 'In corso',                // Index 2 -> "In corso"
        3 => 'In attesa',               // Index 3 -> "In attesa"
        4 => 'Risolto',                 // Index 4 -> "Risolto"
        5 => 'Chiuso',                  // Index 5 -> "Chiuso"
        6 => 'Attesa feedback cliente', // Index 6 -> "Attesa feedback cliente"
    ];

    /**
     * Cache key per evitare query ripetute
     */
    private const CACHE_KEY = 'ticket_stages_mapping';

    private const CACHE_TTL = 3600; // 1 ora

    /**
     * Ottiene il TicketStage corrispondente al vecchio status numerico
     */
    public static function getStageFromLegacyStatus(int $legacyStatus): ?TicketStage
    {
        $stageName = self::$legacyToNewMapping[$legacyStatus] ?? null;

        if (! $stageName) {
            return null;
        }

        return self::getStageMapping()[$stageName] ?? null;
    }

    /**
     * Ottiene l'ID del TicketStage corrispondente al vecchio status numerico
     */
    public static function getStageIdFromLegacyStatus(int $legacyStatus): ?int
    {
        $stage = self::getStageFromLegacyStatus($legacyStatus);

        return $stage?->id;
    }

    /**
     * Ottiene il vecchio status numerico dal TicketStage
     */
    public static function getLegacyStatusFromStage(TicketStage $stage): ?int
    {
        $stageName = $stage->name;

        return self::getLegacyStatusFromStageName($stageName);
    }

    /**
     * Ottiene il vecchio status numerico dal nome dello stage
     */
    public static function getLegacyStatusFromStageName(string $stageName): ?int
    {
        return array_search($stageName, self::$legacyToNewMapping, true) ?: null;
    }

    /**
     * Ottiene il vecchio status numerico dall'ID del TicketStage
     */
    public static function getLegacyStatusFromStageId(int $stageId): ?int
    {
        $stageMapping = self::getStageMapping();

        foreach ($stageMapping as $stageName => $stage) {
            if ($stage->id === $stageId) {
                return self::getLegacyStatusFromStageName($stageName);
            }
        }

        return null;
    }

    /**
     * Verifica se un vecchio status numerico è valido
     */
    public static function isValidLegacyStatus(int $status): bool
    {
        return isset(self::$legacyToNewMapping[$status]);
    }

    /**
     * Ottiene tutti i mapping legacy -> stage ID
     */
    public static function getAllMappings(): array
    {
        $mappings = [];
        $stageMapping = self::getStageMapping();

        foreach (self::$legacyToNewMapping as $legacyStatus => $stageName) {
            $stage = $stageMapping[$stageName] ?? null;
            if ($stage) {
                $mappings[$legacyStatus] = $stage->id;
            }
        }

        return $mappings;
    }

    /**
     * Get migration statistics
     */
    public static function getMigrationStats(): array
    {
        $legacyStatuses = array_keys(self::$legacyToNewMapping);
        $mappedStatuses = [];
        $unmappedStatuses = [];

        foreach ($legacyStatuses as $legacyStatus) {
            $stageId = self::getStageIdFromLegacyStatus($legacyStatus);
            if ($stageId) {
                $mappedStatuses[] = $legacyStatus;
            } else {
                $unmappedStatuses[] = $legacyStatus;
            }
        }

        return [
            'total_legacy_statuses' => count($legacyStatuses),
            'mapped_statuses' => count($mappedStatuses),
            'unmapped_statuses' => implode(', ', $unmappedStatuses),
            'mapping_complete' => empty($unmappedStatuses),
            'legacy_statuses' => $legacyStatuses,
            'mapped_list' => $mappedStatuses,
            'unmapped_list' => $unmappedStatuses,
        ];
    }

    /**
     * Cache degli stage per nome per evitare query ripetute
     */
    private static function getStageMapping()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return TicketStage::all()->keyBy('name');
        });
    }

    /**
     * Clear the mapping cache
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

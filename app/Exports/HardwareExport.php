<?php

namespace App\Exports;

use App\Models\Hardware;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection;

class HardwareExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $companyId;
    protected $userId;
    protected $includeTrashed;
    protected $allowedStatuses;
    protected $allowedPositions;
    protected $hardwareOwnershipTypes;

    public function __construct($companyId = null, $userId = null, $includeTrashed = false)
    {
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->includeTrashed = $includeTrashed;

        $this->allowedStatuses = config('app.hardware_statuses');
        $this->allowedPositions = config('app.hardware_positions');
        $this->hardwareOwnershipTypes = config('app.hardware_ownership_types');
    }

    public function collection(): Collection
    {
        $query = Hardware::query();

        // Includi soft deleted se richiesto
        if ($this->includeTrashed) {
            $query->withTrashed();
        }

        // Filtra per azienda se specificata
        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        // Filtra per utente se specificato
        if ($this->userId) {
            $query->whereHas('users', function ($q) {
                $q->where('user_id', $this->userId);
            });
        }

        return $query->with(['hardwareType', 'company', 'users'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Marca',
            'Modello',
            'Numero di serie',
            'Cespite aziendale',
            'Identificativo (se non c\'è il cespite)',
            'Stato',
            'Posizione',
            'Uso esclusivo',
            'Data di acquisto',
            'Proprietà',
            'Nota sulla proprietà (se altro)',
            'Note',
            'Azienda',
            'Tipo di hardware',
            'Utenti assegnati',
            'Creato il',
            'Aggiornato il',
            'Eliminato il',
        ];
    }

    public function map($hardware): array
    {
        return [
            $hardware->id,
            $hardware->make,
            $hardware->model,
            $hardware->serial_number,
            $hardware->company_asset_number,
            $hardware->support_label,
            $this->allowedStatuses[$hardware->status] ?? $hardware->status,
            $this->allowedPositions[$hardware->position] ?? $hardware->position,
            $hardware->is_exclusive_use ? 'Si' : 'No',
            $this->formatDate($hardware->purchase_date),
            $this->hardwareOwnershipTypes[$hardware->ownership_type] ?? $hardware->ownership_type,
            $hardware->ownership_type_note,
            $hardware->notes,
            $hardware->company ? $hardware->company->name : '',
            $hardware->hardwareType ? $hardware->hardwareType->name : '',
            $hardware->users->map(function ($user) {
                return $user->name . ' ' . $user->surname . ' (' . $user->email . ')';
            })->implode('; '),
            $this->formatDateTime($hardware->created_at),
            $this->formatDateTime($hardware->updated_at),
            $this->formatDateTime($hardware->deleted_at),
        ];
    }

    /**
     * Format date safely
     */
    private function formatDate($date): string
    {
        if (!$date) {
            return '';
        }
        
        if (is_string($date)) {
            try {
                return Carbon::parse($date)->format('Y-m-d');
            } catch (\Exception $e) {
                return $date; // Return as is if parsing fails
            }
        }
        
        if (method_exists($date, 'format')) {
            return $date->format('Y-m-d');
        }
        
        return (string) $date;
    }

    /**
     * Format datetime safely
     */
    private function formatDateTime($datetime): string
    {
        if (!$datetime) {
            return '';
        }
        
        if (is_string($datetime)) {
            try {
                return Carbon::parse($datetime)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return $datetime; // Return as is if parsing fails
            }
        }
        
        if (method_exists($datetime, 'format')) {
            return $datetime->format('Y-m-d H:i:s');
        }
        
        return (string) $datetime;
    }
}
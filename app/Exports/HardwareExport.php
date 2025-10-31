<?php

namespace App\Exports;

use App\Models\Hardware;
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

    public function __construct($companyId = null, $userId = null, $includeTrashed = false)
    {
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->includeTrashed = $includeTrashed;

        $this->allowedStatuses = config('app.hardware_statuses');
        $this->allowedPositions = config('app.hardware_positions');
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
            'Tipo di possesso',
            'Nota sul tipo di possesso',
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
            $hardware->purchase_date ? $hardware->purchase_date->format('Y-m-d') : '',
            $hardware->ownership_type,
            $hardware->ownership_type_note,
            $hardware->notes,
            $hardware->company ? $hardware->company->name : '',
            $hardware->hardwareType ? $hardware->hardwareType->name : '',
            $hardware->users->map(function ($user) {
                return $user->name . ' ' . $user->surname . ' (' . $user->email . ')';
            })->implode('; '),
            $hardware->created_at ? $hardware->created_at->format('Y-m-d H:i:s') : '',
            $hardware->updated_at ? $hardware->updated_at->format('Y-m-d H:i:s') : '',
            $hardware->deleted_at ? $hardware->deleted_at->format('Y-m-d H:i:s') : '',
        ];
    }
}
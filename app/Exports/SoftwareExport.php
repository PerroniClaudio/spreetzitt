<?php

namespace App\Exports;

use App\Models\Software;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection;

class SoftwareExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $companyId;
    protected $userId;
    protected $includeTrashed;

    public function __construct($companyId = null, $userId = null, $includeTrashed = false)
    {
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->includeTrashed = $includeTrashed;
    }

    public function collection(): Collection
    {
        $query = Software::query();

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

        return $query->with(['softwareType', 'company', 'users'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fornitore',
            'Nome prodotto',
            'Versione',
            'Chiave di attivazione',
            'Cespite aziendale',
            'Tipo di licenza',
            'Massimo installazioni',
            'Uso esclusivo',
            'Data di acquisto',
            'Data di scadenza',
            'Scadenza supporto',
            'Stato',
            'Note',
            'Azienda',
            'Tipo di software',
            'Utenti assegnati',
            'Creato il',
            'Aggiornato il',
            'Eliminato il',
        ];
    }

    public function map($software): array
    {
        return [
            $software->id,
            $software->vendor,
            $software->product_name,
            $software->version,
            $software->activation_key,
            $software->company_asset_number,
            $software->license_type,
            $software->max_installations,
            $software->is_exclusive_use ? 'Si' : 'No',
            $this->formatDate($software->purchase_date),
            $this->formatDate($software->expiration_date),
            $this->formatDate($software->support_expiration_date),
            $software->status,
            $software->notes,
            $software->company ? $software->company->name : '',
            $software->softwareType ? $software->softwareType->name : '',
            $software->users->map(function ($user) {
                return $user->name . ' ' . $user->surname . ' (' . $user->email . ')';
            })->implode('; '),
            $this->formatDateTime($software->created_at),
            $this->formatDateTime($software->updated_at),
            $this->formatDateTime($software->deleted_at),
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

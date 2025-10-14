<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Collection;

class TicketTypesExportCompany implements FromCollection, WithHeadings
{
    protected $ticketTypes;

    public function __construct(Collection $ticketTypes)
    {
        $this->ticketTypes = $ticketTypes;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // Map the ticket types into a simple collection of rows
        $rows = $this->ticketTypes->map(function ($tt) {
            return collect([
                'id' => $tt['id'] ?? $tt->id,
                'name' => $tt['name'] ?? '',
                'description' => $tt['description'] ?? '',
                'categoria' => isset($tt['category']) && is_array($tt['category']) ? ($tt['category']['name'] ?? '') : (isset($tt->category) ? ($tt->category->name ?? '') : ''),
                'brand' => isset($tt['brand']) && is_array($tt['brand']) ? ($tt['brand']['name'] ?? '') : (isset($tt->brand) ? ($tt->brand->name ?? '') : ''),
                'default_priority' => $tt['default_priority'] ?? $tt->default_priority ?? '',
                'default_sla_take' => $tt['default_sla_take'] ?? $tt->default_sla_take ?? '',
                'default_sla_solve' => $tt['default_sla_solve'] ?? $tt->default_sla_solve ?? '',
                'expected_processing_time' => $tt['expected_processing_time'] ?? $tt->expected_processing_time ?? '',
                'expected_is_billable' => isset($tt['expected_is_billable']) ? ($tt['expected_is_billable'] ? 'Si' : 'No') : (isset($tt->expected_is_billable) ? ($tt->expected_is_billable ? 'Si' : 'No') : ''),
                'is_massive_enabled' => isset($tt['is_massive_enabled']) ? ($tt['is_massive_enabled'] ? 'Si' : 'No') : (isset($tt->is_massive_enabled) ? ($tt->is_massive_enabled ? 'Si' : 'No') : ''),
                'is_custom_group_exclusive' => isset($tt['is_custom_group_exclusive']) ? ($tt['is_custom_group_exclusive'] ? 'Si' : 'No') : (isset($tt->is_custom_group_exclusive) ? ($tt->is_custom_group_exclusive ? 'Si' : 'No') : ''),
                'is_master' => isset($tt['is_master']) ? ($tt['is_master'] ? 'Si' : 'No') : (isset($tt->is_master) ? ($tt->is_master ? 'Si' : 'No') : ''),
                'warning' => $tt['warning'] ?? $tt->warning ?? '',
                'it_referer_limited' => isset($tt['it_referer_limited']) ? ($tt['it_referer_limited'] ? 'Si' : 'No') : (isset($tt->it_referer_limited) ? ($tt->it_referer_limited ? 'Si' : 'No') : ''),
            ]);
        });

        return new Collection($rows->values()->all());
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nome',
            'Descrizione',
            'Categoria',
            'Brand',
            'Priorità predefinita',
            'SLA presa in carico predefinita',
            'SLA risoluzione predefinita',
            'Tempo di lavorazione previsto',
            'Fatturabile previsto',
            'Massivo abilitato',
            'Gruppo custom esclusivo',
            'È master',
            'Warning',
            'Limitato a Referente IT',
        ];
    }
}

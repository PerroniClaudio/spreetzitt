<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;

class HardwareDeletionTemplateExport implements FromArray
{
    public function __construct(private readonly User $authUser) {}

    public function array(): array
    {
        $template_data = [];
        $headers = [
            'ID hardware *',
            ! $this->authUser->is_admin && $this->authUser->is_company_admin
                ? 'Tipo di eliminazione Soft/Recupero *'
                : 'Tipo di eliminazione Soft/Definitiva/Recupero *',
        ];

        return [
            $headers,
            $template_data,
        ];
    }
}

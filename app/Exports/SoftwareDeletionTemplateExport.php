<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class SoftwareDeletionTemplateExport implements FromArray
{
    public function __construct() {}

    public function array(): array
    {
        $template_data = [];
        $headers = [
            'ID software *',
            'Tipo di eliminazione Soft/Definitiva/Recupero *',
        ];

        return [
            $headers,
            $template_data,
        ];
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class HardwareDeletionTemplateExport implements FromArray
{
    public function __construct()
    {
    }
    
    public function array(): array {
        $template_data = [];
        $headers = [
            "ID hardware *",
            "Tipo di eliminazione Soft/Definitiva/Recupero *",
        ];
        
        return [
            $headers,
            $template_data
        ];
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class HardwareAssignationTemplateExport implements FromArray
{
    public function __construct() {}

    public function array(): array
    {
        $template_data = [];
        $headers = [
            'ID hardware *',
            'ID azienda da associare',
            'ID utente/i da associare (separati da virgola)',
            "ID azienda da rimuovere (rimuovendo l'associazione con l'azienda verranno rimosse anche quelle coi rispettivi utenti)",
            'ID utente/i da rimuovere (separati da virgola)',
            "ID responsabile dell'assegnazione (deve essere admin o del supporto). Se non indicato viene impostato l'ID di chi carica il file.",
        ];

        return [
            $headers,
            $template_data,
        ];
    }
}

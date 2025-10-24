<?php

namespace App\Exports;

// use App\Models\UserTemplate;
use Maatwebsite\Excel\Concerns\FromArray;

class UserTemplateExport implements FromArray
{
    public function __construct() {}

    // NON CAMBIARE L'ORDINE DEGLI ELEMENTI NELL'ARRAY. (Se si deve modificare allora va aggiornato anche in UsersImport)
    public function array(): array
    {
        $template_data = [];
        $headers = [
            'Nome *',
            'Cognome *',
            'Email *',
            'Abilitazione (UTENTE/AMMINISTRATORE) *',
            'ID Azienda *',
            'Telefono',
            'Città',
            'CAP',
            'Indirizzo',
        ];

        return [
            $headers,
            $template_data,
        ];
    }
}

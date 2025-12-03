<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class HardwareTemplateExport implements FromArray
{
    public function __construct() {}

    // NON CAMBIARE L'ORDINE DEGLI ELEMENTI NELL'ARRAY. (Se si deve modificare allora va aggiornato anche in HardwareImport)
    public function array(): array
    {
        $template_data = [];
        $headers = [
            'Marca *',
            'Modello *',
            'Seriale (* se non è un accessorio)',
            'Tipo (testo, preso dalla lista nel gestionale)',
            "Data d'acquisto (gg/mm/aaaa)",
            'Proprietà (testo, preso tra le opzioni nel gestionale)',
            'Specificare (se proprietà è Altro)',
            'Cespite aziendale (se non è un accessorio, compilare almeno uno tra cespite aziendale e identificativo)',
            'Identificativo (se non è un accessorio, compilare almeno uno tra cespite aziendale e identificativo)',
            'Note',
            'Uso esclusivo (Si/No, Se manca viene impostato su No)',
            'ID Azienda',
            'ID utenti (separati da virgola)',
            "ID utente responsabile dell'assegnazione (deve essere admin o del supporto)",
            "Posizione (testo, preso tra le opzioni nel gestionale, Se manca viene impostato su 'Azienda')",
            "Stato all'acquisto (testo, preso tra le opzioni nel gestionale, Se manca viene impostato su 'Nuovo')",
            "Stato (testo, preso tra le opzioni nel gestionale, Se manca viene impostato su 'Nuovo')",
            'È un accessorio (Si/No, Se manca viene impostato su No)',
        ];

        return [
            $headers,
            $template_data,
        ];
    }
}

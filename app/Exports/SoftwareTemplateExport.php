<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class SoftwareTemplateExport implements FromArray
{
    public function __construct() {}

    // NON CAMBIARE L'ORDINE DEGLI ELEMENTI NELL'ARRAY. (Se si deve modificare allora va aggiornato anche in SoftwareImport)
    public function array(): array
    {
        $template_data = [];
        $headers = [
            'Fornitore *',
            'Nome prodotto *',
            'Versione',
            'Chiave di attivazione',
            'Cespite aziendale (univoco)',
            'Tipo di licenza (perpetua, abbonamento, trial, open-source)',
            'Numero massimo installazioni',
            "Data d'acquisto (gg/mm/aaaa)",
            'Data scadenza (gg/mm/aaaa)',
            'Data scadenza supporto (gg/mm/aaaa)',
            'Uso esclusivo (Si/No, Se manca viene impostato su No)',
            "Stato (testo, preso tra le opzioni nel gestionale, Se manca viene impostato su 'active')",
            'ID Azienda',
            'ID Tipo software',
            'ID utenti (separati da virgola)',
            "ID utente responsabile dell'assegnazione (deve essere admin o del supporto)",
        ];

        return [
            $headers,
            $template_data,
        ];
    }
}

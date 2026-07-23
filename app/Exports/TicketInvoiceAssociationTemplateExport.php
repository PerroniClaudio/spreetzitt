<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class TicketInvoiceAssociationTemplateExport implements FromArray
{
    public function array(): array
    {
        return [[
            'ID ticket *',
            'ID fattura (* Compilare questo oppure numero e anno fattura)',
            'Numero fattura',
            'Anno fattura',
            'Sovrascrivi fattura esistente (SI/NO). Se lasciato vuoto si assume NO',
        ]];
    }
}

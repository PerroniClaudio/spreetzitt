<?php

namespace App\Imports;

use App\Models\Invoice;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class TicketInvoiceAssociationsImport implements ToCollection
{
    public function collection(Collection $rows): void
    {
        $associations = [];
        $errors = [];
        $ticketRows = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            if ($rowNumber === 1 || $this->isEmptyRow($row)) {
                continue;
            }

            $ticketId = $this->parseId($row->get(0));
            $invoiceId = $this->parseId($row->get(1));
            $invoiceNumber = $this->stringValue($row->get(2));
            $invoiceYear = $this->parseYear($row->get(3));
            $overwrite = $this->parseOverwrite($row->get(4), $rowNumber, $errors);

            if ($ticketId === null) {
                $errors[] = "Riga {$rowNumber}: l'ID ticket è obbligatorio e deve essere un numero intero positivo.";

                continue;
            }

            if (isset($ticketRows[$ticketId])) {
                $errors[] = "Riga {$rowNumber}: il ticket {$ticketId} è già presente nella riga {$ticketRows[$ticketId]}.";

                continue;
            }

            $ticketRows[$ticketId] = $rowNumber;

            $ticket = Ticket::query()->find($ticketId);
            if ($ticket === null) {
                $errors[] = "Riga {$rowNumber}: il ticket con ID {$ticketId} non esiste.";

                continue;
            }

            $invoiceId = $this->resolveInvoiceId($invoiceId, $invoiceNumber, $invoiceYear, $rowNumber, $errors);
            if ($invoiceId === null) {
                continue;
            }

            if ($ticket->invoice_id !== null && ! $overwrite) {
                $errors[] = "Riga {$rowNumber}: il ticket {$ticketId} è già associato alla fattura {$ticket->invoice_id}. Imposta \"si\" nella colonna di sovrascrittura per sostituirla.";

                continue;
            }

            $associations[] = [
                'ticket_id' => $ticketId,
                'invoice_id' => $invoiceId,
                'overwrite' => $overwrite,
                'row_number' => $rowNumber,
            ];
        }

        if ($errors !== []) {
            throw new \RuntimeException(implode("\n", $errors));
        }

        DB::transaction(function () use ($associations): void {
            foreach ($associations as $association) {
                $ticket = Ticket::query()->lockForUpdate()->find($association['ticket_id']);

                if ($ticket === null) {
                    throw new \RuntimeException("Riga {$association['row_number']}: il ticket con ID {$association['ticket_id']} non esiste più.");
                }

                if ($ticket->invoice_id !== null && ! $association['overwrite']) {
                    throw new \RuntimeException("Riga {$association['row_number']}: il ticket {$association['ticket_id']} è già associato alla fattura {$ticket->invoice_id}. Nessuna associazione è stata modificata.");
                }

                $ticket->update(['invoice_id' => $association['invoice_id']]);
            }
        });
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function isEmptyRow(Collection $row): bool
    {
        return $row->every(fn (mixed $value): bool => $this->stringValue($value) === null);
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function parseOverwrite(mixed $value, int $rowNumber, array &$errors): bool
    {
        $value = $this->stringValue($value);

        if ($value === null || mb_strtolower($value) === 'no') {
            return false;
        }

        if (mb_strtolower($value) === 'si') {
            return true;
        }

        $errors[] = "Riga {$rowNumber}: il valore di sovrascrittura deve essere \"si\", \"no\" oppure vuoto.";

        return false;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function resolveInvoiceId(?int $invoiceId, ?string $invoiceNumber, ?int $invoiceYear, int $rowNumber, array &$errors): ?int
    {
        if ($invoiceId !== null) {
            if (! Invoice::query()->whereKey($invoiceId)->exists()) {
                $errors[] = "Riga {$rowNumber}: la fattura con ID {$invoiceId} non esiste o è stata eliminata.";

                return null;
            }

            return $invoiceId;
        }

        if ($invoiceNumber === null || $invoiceYear === null) {
            $errors[] = "Riga {$rowNumber}: indica l'ID fattura oppure entrambi il numero fattura e l'anno fattura.";

            return null;
        }

        $invoices = Invoice::query()
            ->where('number', $invoiceNumber)
            ->whereYear('invoice_date', $invoiceYear)
            ->get(['id']);

        if ($invoices->isEmpty()) {
            $errors[] = "Riga {$rowNumber}: non è stata trovata alcuna fattura con numero \"{$invoiceNumber}\" per l'anno {$invoiceYear}.";

            return null;
        }

        if ($invoices->count() > 1) {
            $errors[] = "Riga {$rowNumber}: sono state trovate più fatture con numero \"{$invoiceNumber}\" per l'anno {$invoiceYear}.";

            return null;
        }

        return $invoices->first()->id;
    }

    private function parseId(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_float($value) && $value > 0 && floor($value) === $value) {
            return (int) $value;
        }

        $value = $this->stringValue($value);

        if ($value === null || ! ctype_digit($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    private function parseYear(mixed $value): ?int
    {
        $year = $this->parseId($value);

        return $year !== null && $year >= 1000 && $year <= 9999 ? $year : null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

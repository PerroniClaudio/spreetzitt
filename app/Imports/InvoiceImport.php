<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePaymentStage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class InvoiceImport implements ToCollection, WithMultipleSheets
{
    /**
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        $invoices = [];
        $errors = [];
        $invoiceNumbersByYear = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            if ($rowNumber === 1 || $this->isEmptyInvoiceRow($row) || $this->isTrailingEmptyInvoiceRow($row)) {
                continue;
            }

            $invoice = $this->validateRow($row, $rowNumber, $errors);

            if ($invoice === null) {
                continue;
            }

            $invoiceKey = mb_strtolower($invoice['number']).'|'.$invoice['invoice_date']->year;
            if (isset($invoiceNumbersByYear[$invoiceKey])) {
                $errors[] = "Riga {$rowNumber}: il numero fattura \"{$invoice['number']}\" è già presente nella riga {$invoiceNumbersByYear[$invoiceKey]} per l'anno {$invoice['invoice_date']->year}.";

                continue;
            }

            $invoiceNumbersByYear[$invoiceKey] = $rowNumber;

            $exists = Invoice::withTrashed()
                ->where('number', $invoice['number'])
                ->whereYear('invoice_date', $invoice['invoice_date']->year)
                ->exists();

            if ($exists) {
                $errors[] = "Riga {$rowNumber}: il numero fattura \"{$invoice['number']}\" è già utilizzato per l'anno {$invoice['invoice_date']->year}.";

                continue;
            }

            $invoices[] = $invoice;
        }

        if ($errors !== []) {
            throw new \RuntimeException(implode("\n", $errors));
        }

        DB::transaction(function () use ($invoices): void {
            foreach ($invoices as $invoice) {
                Invoice::create($invoice);
            }
        });
    }

    /**
     * @param  Collection<int, mixed>  $row
     * @param  array<int, string>  $errors
     * @return array{number: string, description: ?string, company_id: ?int, payment_stage_id: ?int, invoice_date: Carbon}|null
     */
    private function validateRow(Collection $row, int $rowNumber, array &$errors): ?array
    {
        $number = $this->stringValue($row->get(0));
        $description = $this->stringValue($row->get(1));
        $companyName = $this->stringValue($row->get(2));
        $paymentStageName = $this->stringValue($row->get(3));
        $dateValue = $row->get(4);

        if ($number === null) {
            $errors[] = "Riga {$rowNumber}: il numero fattura è obbligatorio.";
        } elseif (mb_strlen($number) > 255) {
            $errors[] = "Riga {$rowNumber}: il numero fattura non può superare 255 caratteri.";
        }

        $invoiceDate = $this->parseDate($dateValue);
        if ($invoiceDate === null) {
            $errors[] = "Riga {$rowNumber}: la data emissione deve essere nel formato gg/mm/aaaa.";
        }

        $companyId = $this->findIdByName(Company::query(), $companyName, 'azienda', $rowNumber, $errors);
        $paymentStageId = $this->findIdByName(InvoicePaymentStage::query(), $paymentStageName, 'stato pagamento', $rowNumber, $errors);

        if ($number === null || $invoiceDate === null) {
            return null;
        }

        return [
            'number' => $number,
            'description' => $description,
            'company_id' => $companyId,
            'payment_stage_id' => $paymentStageId,
            'invoice_date' => $invoiceDate,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Company|InvoicePaymentStage>  $query
     * @param  array<int, string>  $errors
     */
    private function findIdByName($query, ?string $name, string $label, int $rowNumber, array &$errors): ?int
    {
        if ($name === null) {
            return null;
        }

        $records = $query->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->get(['id']);

        if ($records->isEmpty()) {
            $errors[] = "Riga {$rowNumber}: {$label} \"{$name}\" non trovato.";

            return null;
        }

        if ($records->count() > 1) {
            $errors[] = "Riga {$rowNumber}: {$label} \"{$name}\" non è univoco.";

            return null;
        }

        return $records->first()->id;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject($value))->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        $date = $this->stringValue($value);
        if ($date === null) {
            return null;
        }

        $date = trim(str_replace(["\u{00A0}", "\u{200E}", "\u{200F}"], ' ', $date));

        foreach (['!d/m/Y', '!d-m-Y', '!d.m.Y', '!d/m/Y H:i:s', '!Y-m-d', '!Y-m-d H:i:s'] as $format) {
            try {
                $parsedDate = Carbon::createFromFormat($format, $date);

                if ($parsedDate->format(str_replace('!', '', $format)) === $date) {
                    return $parsedDate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  Collection<int, mixed>  $row
     */
    private function isEmptyInvoiceRow(Collection $row): bool
    {
        return $row->every(fn (mixed $value): bool => $this->isBlankCell($value));
    }

    private function isBlankCell(mixed $value): bool
    {
        return $this->stringValue($value) === null
            || (is_numeric($value) && (float) $value === 0.0);
    }

    /**
     * Alcuni fogli Excel mantengono celle apparentemente vuote come zero.
     * Se manca il numero fattura e non ci sono altri dati significativi, è una riga vuota del template.
     *
     * @param  Collection<int, mixed>  $row
     */
    private function isTrailingEmptyInvoiceRow(Collection $row): bool
    {
        if ($this->stringValue($row->get(0)) !== null) {
            return false;
        }

        return $row->slice(1)->every(fn (mixed $value): bool => $this->isBlankCell($value));
    }
}

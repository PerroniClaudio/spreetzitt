<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\InvoicePaymentStage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;

class InvoiceTemplateExport implements FromArray, WithEvents
{
    /**
     * @var array<int, string>
     */
    private array $companyNames;

    /**
     * @var array<int, string>
     */
    private array $paymentStageNames;

    public function __construct()
    {
        $this->companyNames = Company::query()
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        $this->paymentStageNames = InvoicePaymentStage::query()
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function array(): array
    {
        return [[
            'Numero fattura *',
            'Descrizione',
            'Azienda',
            'Stato pagamento',
            'Data emissione (gg/mm/aaaa) *',
        ]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [$this, 'afterSheet'],
        ];
    }

    public function afterSheet(AfterSheet $event): void
    {
        $spreadsheet = $event->sheet->getDelegate()->getParent();
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Elenchi');

        foreach ($this->companyNames as $index => $companyName) {
            $lookupSheet->setCellValueByColumnAndRow(1, $index + 1, $companyName);
        }

        foreach ($this->paymentStageNames as $index => $paymentStageName) {
            $lookupSheet->setCellValueByColumnAndRow(2, $index + 1, $paymentStageName);
        }

        if ($this->companyNames !== []) {
            $spreadsheet->addNamedRange(new NamedRange('AziendeFatture', $lookupSheet, '$A$1:$A$'.count($this->companyNames)));
            $this->addListValidation($event, 'C', 'AziendeFatture');
        }

        if ($this->paymentStageNames !== []) {
            $spreadsheet->addNamedRange(new NamedRange('StatiPagamentoFatture', $lookupSheet, '$B$1:$B$'.count($this->paymentStageNames)));
            $this->addListValidation($event, 'D', 'StatiPagamentoFatture');
        }

        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
    }

    private function addListValidation(AfterSheet $event, string $column, string $namedRange): void
    {
        $sheet = $event->sheet->getDelegate();
        $validation = $sheet->getDataValidation($column.'2');
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valore non valido');
        $validation->setError('Seleziona un valore presente nell’elenco.');
        $validation->setFormula1('='.$namedRange);

        for ($row = 3; $row <= 1000; $row++) {
            $sheet->setDataValidation($column.$row, clone $validation);
        }
    }
}

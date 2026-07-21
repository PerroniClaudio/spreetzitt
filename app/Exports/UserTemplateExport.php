<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;

class UserTemplateExport implements FromArray, WithEvents
{
    /** @var array<int, Company> */
    private array $companies;

    public function __construct(User $authUser)
    {
        $companiesQuery = Company::query()->orderBy('name');

        if (! $authUser->is_admin) {
            $companiesQuery->whereKey($authUser->selectedCompany()?->id);
        }

        $this->companies = $companiesQuery->get(['id', 'name'])->all();
    }

    public function array(): array
    {
        return [[
            'Nome *',
            'Cognome *',
            'Email *',
            'Abilitazione (UTENTE/AMMINISTRATORE) *',
            'ID Azienda *',
            'Telefono',
            'Città',
            'CAP',
            'Indirizzo',
        ]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => [$this, 'afterSheet']];
    }

    public function afterSheet(AfterSheet $event): void
    {
        $spreadsheet = $event->sheet->getDelegate()->getParent();
        $event->sheet->getDelegate()->setTitle('Utenti');
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Tendine');
        $lookupSheet->setCellValue('A1', 'Abilitazioni');
        $lookupSheet->setCellValue('A2', 'UTENTE');
        $lookupSheet->setCellValue('A3', 'AMMINISTRATORE');
        $lookupSheet->setCellValue('B1', 'Aziende');

        foreach ($this->companies as $index => $company) {
            $lookupSheet->setCellValueByColumnAndRow(2, $index + 2, "{$company->id} - {$company->name}");
        }

        $this->addListValidation($event, 'D', 'AbilitazioniUtenti', 'A', 2);
        $this->addListValidation($event, 'E', 'AziendeUtenti', 'B', count($this->companies));

        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
    }

    private function addListValidation(AfterSheet $event, string $column, string $name, string $lookupColumn, int $count): void
    {
        if ($count === 0) {
            return;
        }

        $sheet = $event->sheet->getDelegate();
        $lookupSheet = $sheet->getParent()->getSheetByName('Tendine');
        $sheet->getParent()->addNamedRange(new NamedRange($name, $lookupSheet, '$'.$lookupColumn.'$2:$'.$lookupColumn.'$'.($count + 1)));

        $validation = $sheet->getDataValidation($column.'2');
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setFormula1('='.$name);

        for ($row = 3; $row <= 1000; $row++) {
            $sheet->setDataValidation($column.$row, clone $validation);
        }
    }
}

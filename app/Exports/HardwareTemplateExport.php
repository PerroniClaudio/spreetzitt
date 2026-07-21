<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\HardwareType;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;

class HardwareTemplateExport implements FromArray, WithEvents
{
    /**
     * @var array<int, array{id: int, name: string}>
     */
    private array $companies;

    /** @var array<int, User> */
    private array $responsibleUsers;

    /**
     * @var array<int, string>
     */
    private array $hardwareTypeNames;

    public function __construct(User $authUser)
    {
        $companiesQuery = Company::query()->orderBy('name');

        if (! $authUser->is_admin) {
            $companiesQuery->whereKey($authUser->selectedCompany()?->id);
        }

        $this->companies = $companiesQuery->get(['id', 'name'])->all();
        $companyIds = array_map(fn (Company $company): int => $company->id, $this->companies);
        $responsibleUsersQuery = User::query()
            ->with('companies:id,name')
            ->where(function ($query) {
                $query->where('is_admin', true)
                    ->orWhere('is_superadmin', true)
                    ->orWhere('is_company_admin', true);
            });

        if (! $authUser->is_admin) {
            $responsibleUsersQuery->where('is_company_admin', true)
                ->whereHas('companies', fn ($query) => $query->whereIn('companies.id', $companyIds));
        }

        $this->responsibleUsers = $responsibleUsersQuery
            ->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'name', 'surname', 'email', 'is_admin', 'is_superadmin', 'is_company_admin'])
            ->all();
        $this->hardwareTypeNames = HardwareType::query()->orderBy('name')->pluck('name')->all();
    }

    public function array(): array
    {
        return [[
            'Marca *',
            'Modello *',
            'Seriale (* se non è un accessorio)',
            'Tipo',
            "Data d'acquisto (gg/mm/aaaa)",
            'Proprietà',
            'Specificare (se proprietà è Altro)',
            'Cespite aziendale (se non è un accessorio, compilare almeno uno tra cespite aziendale e identificativo)',
            'Identificativo (se non è un accessorio, compilare almeno uno tra cespite aziendale e identificativo)',
            'Note',
            'Uso esclusivo (Si/No)',
            'ID Azienda',
            'ID utenti (solo i numeri separati da virgola)',
            "ID utente responsabile dell'assegnazione",
            'Posizione',
            "Stato all'acquisto",
            'Stato',
            'È un accessorio (Si/No)',
        ]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => [$this, 'afterSheet']];
    }

    public function afterSheet(AfterSheet $event): void
    {
        $spreadsheet = $event->sheet->getDelegate()->getParent();
        $event->sheet->getDelegate()->setTitle('Hardware');
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Tendine');

        $this->writeColumn($lookupSheet, 1, 'Tipi hardware', $this->hardwareTypeNames);
        $this->writeColumn($lookupSheet, 2, 'Proprietà', array_values(config('app.hardware_ownership_types')));
        $this->writeColumn($lookupSheet, 3, 'Aziende', array_map(fn (Company $company): string => "{$company->id} - {$company->name}", $this->companies));
        $this->writeColumn($lookupSheet, 4, 'Responsabili assegnazione', array_map(fn (User $user): string => $this->responsibleUserLabel($user), $this->responsibleUsers));
        $this->writeColumn($lookupSheet, 5, 'Posizioni', array_values(config('app.hardware_positions')));
        $this->writeColumn($lookupSheet, 6, "Stati all'acquisto", array_values(config('app.hardware_statuses_at_purchase')));
        $this->writeColumn($lookupSheet, 7, 'Stati', array_values(config('app.hardware_statuses')));
        $this->writeColumn($lookupSheet, 8, 'Si/No', ['Si', 'No']);

        $this->addListValidation($event, 'D', 'TipiHardware', 1, count($this->hardwareTypeNames));
        $this->addListValidation($event, 'F', 'ProprietaHardware', 2, count(config('app.hardware_ownership_types')));
        $this->addListValidation($event, 'K', 'SiNoUsoEsclusivo', 8, 2);
        $this->addListValidation($event, 'L', 'AziendeHardware', 3, count($this->companies));
        $this->addListValidation($event, 'N', 'UtentiResponsabiliHardware', 4, count($this->responsibleUsers));
        $this->addListValidation($event, 'O', 'PosizioniHardware', 5, count(config('app.hardware_positions')));
        $this->addListValidation($event, 'P', 'StatiAcquistoHardware', 6, count(config('app.hardware_statuses_at_purchase')));
        $this->addListValidation($event, 'Q', 'StatiHardware', 7, count(config('app.hardware_statuses')));
        $this->addListValidation($event, 'R', 'SiNoAccessorio', 8, 2);

        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
    }

    private function responsibleUserLabel(User $user): string
    {
        $companies = $user->companies->pluck('name')->join(', ');

        return "{$user->id} - {$user->surname} {$user->name} {$user->email} ({$companies})";
    }

    /**
     * @param  array<int, string>  $values
     */
    private function writeColumn($sheet, int $column, string $heading, array $values): void
    {
        $sheet->setCellValueByColumnAndRow($column, 1, $heading);

        foreach ($values as $index => $value) {
            $sheet->setCellValueByColumnAndRow($column, $index + 2, $value);
        }
    }

    private function addListValidation(AfterSheet $event, string $column, string $name, int $lookupColumn, int $count): void
    {
        if ($count === 0) {
            return;
        }

        $sheet = $event->sheet->getDelegate();
        $lookupSheet = $sheet->getParent()->getSheetByName('Tendine');
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lookupColumn);
        $sheet->getParent()->addNamedRange(new NamedRange($name, $lookupSheet, '$'.$letter.'$2:$'.$letter.'$'.($count + 1)));

        $validation = $sheet->getDataValidation($column.'2');
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valore non valido');
        $validation->setError('Seleziona un valore presente nell’elenco.');
        $validation->setFormula1('='.$name);

        for ($row = 3; $row <= 1000; $row++) {
            $sheet->setDataValidation($column.$row, clone $validation);
        }
    }
}

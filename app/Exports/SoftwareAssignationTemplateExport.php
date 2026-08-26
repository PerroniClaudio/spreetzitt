<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\Software;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;

class SoftwareAssignationTemplateExport implements FromArray, WithEvents
{
    /** @var array<int, Company> */
    private array $companies;

    /** @var array<int, Software> */
    private array $software;

    /** @var array<int, User> */
    private array $responsibleUsers;

    /** @var array<int, User> */
    private array $assignableUsers;

    public function __construct(User $authUser)
    {
        $companiesQuery = Company::query()->orderBy('name');

        if (! $authUser->is_admin) {
            $companiesQuery->whereKey($authUser->selectedCompany()?->id);
        }

        $this->companies = $companiesQuery->get(['id', 'name'])->all();
        $companyIds = array_map(fn (Company $company): int => $company->id, $this->companies);
        $this->software = Software::query()
            ->whereIn('company_id', $companyIds)
            ->orderBy('id')
            ->get(['id', 'vendor', 'product_name', 'company_asset_number'])
            ->all();
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
        $this->assignableUsers = User::query()
            ->with('companies:id,name')
            ->where('is_deleted', false)
            ->whereHas('companies', fn ($query) => $query->whereIn('companies.id', $companyIds))
            ->when($authUser->is_company_admin, fn ($query) => $query
                ->where('is_admin', false)
                ->where('is_superadmin', false))
            ->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'name', 'surname', 'email', 'is_admin', 'is_superadmin', 'is_company_admin'])
            ->all();
    }

    public function array(): array
    {
        $headers = [
            'ID software *',
            'ID azienda da associare',
            'ID utente/i da associare (separati da virgola)',
            "ID azienda da rimuovere (rimuovendo l'associazione con l'azienda verranno rimosse anche quelle coi rispettivi utenti)",
            'ID utente/i da rimuovere (separati da virgola)',
            "ID responsabile dell'assegnazione (deve essere admin o del supporto). Se non indicato viene impostato l'ID di chi carica il file.",
        ];

        return [$headers];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => [$this, 'afterSheet']];
    }

    public function afterSheet(AfterSheet $event): void
    {
        $spreadsheet = $event->sheet->getDelegate()->getParent();
        $event->sheet->getDelegate()->setTitle('Assegnazioni software');
        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Tendine');

        $this->writeColumn($lookupSheet, 1, 'Software', array_map(fn (Software $software): string => "{$software->id} - {$software->vendor} {$software->product_name} ({$software->company_asset_number})", $this->software));
        $this->writeColumn($lookupSheet, 2, 'Aziende', array_map(fn (Company $company): string => "{$company->id} - {$company->name}", $this->companies));
        $this->writeColumn($lookupSheet, 3, 'Responsabili assegnazione', array_map(fn (User $user): string => $this->responsibleUserLabel($user), $this->responsibleUsers));
        $this->writeColumn($lookupSheet, 4, 'Utenti assegnabili', array_map(fn (User $user): string => $this->responsibleUserLabel($user), $this->assignableUsers));

        $this->addListValidation($event, 'A', 'SoftwareAssegnazioni', 1, count($this->software));
        $this->addListValidation($event, 'B', 'AziendeSoftwareDaAssociare', 2, count($this->companies));
        $this->addListValidation($event, 'C', 'UtentiSoftwareDaAssociare', 4, count($this->assignableUsers));
        $this->addListValidation($event, 'D', 'AziendeSoftwareDaRimuovere', 2, count($this->companies));
        $this->addListValidation($event, 'E', 'UtentiSoftwareDaRimuovere', 4, count($this->assignableUsers));
        $this->addListValidation($event, 'F', 'ResponsabiliSoftwareAssegnazioni', 3, count($this->responsibleUsers));

        $lookupSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
    }

    private function responsibleUserLabel(User $user): string
    {
        $companies = $user->companies->pluck('name')->join(', ');

        return "{$user->id} - {$user->surname} {$user->name} {$user->email} ({$companies})";
    }

    /** @param array<int, string> $values */
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

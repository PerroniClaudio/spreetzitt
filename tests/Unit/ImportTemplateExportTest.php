<?php

use App\Exports\HardwareAssignationTemplateExport;
use App\Exports\HardwareTemplateExport;
use App\Exports\UserTemplateExport;
use App\Imports\HardwareAssignationsImport;
use App\Imports\HardwareImport;
use App\Imports\SoftwareAssignationsImport;
use App\Imports\SoftwareImport;
use App\Imports\UsersImport;
use App\Models\Company;
use App\Models\Hardware;
use App\Models\HardwareType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

it('generates import templates with visible lists and dropdowns limited to the selected company', function () {
    $allowedCompany = Company::factory()->create();
    $unavailableCompany = Company::factory()->create();
    $authUser = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $allowedUser = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $unavailableUser = User::factory()->create(['password' => Hash::make('password')]);

    $authUser->companies()->attach($allowedCompany);
    $allowedUser->companies()->attach($allowedCompany);
    $allowedUser->companies()->attach($unavailableCompany);
    $unavailableUser->companies()->attach($unavailableCompany);
    HardwareType::query()->create(['name' => 'Notebook test']);
    session(['selected_company_id' => $allowedCompany->id]);

    $hardwareWorkbook = workbookFor(new HardwareTemplateExport($authUser));
    $hardwareLists = $hardwareWorkbook->getSheetByName('Tendine');
    $hardwareSheet = $hardwareWorkbook->getSheetByName('Hardware');

    expect($hardwareWorkbook->getSheetNames())->toBe(['Hardware', 'Tendine'])
        ->and($hardwareSheet->getDataValidation('D2')->getFormula1())->toBe('=TipiHardware')
        ->and($hardwareSheet->getDataValidation('L2')->getFormula1())->toBe('=AziendeHardware')
        ->and($hardwareLists->getCell('C2')->getValue())->toBe("{$allowedCompany->id} - {$allowedCompany->name}")
        ->and($hardwareLists->getCell('C3')->getValue())->toBeNull()
        ->and($hardwareLists->getSheetState())->toBe(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN)
        ->and(array_filter(array_column($hardwareLists->rangeToArray('D2:D4'), 0)))->toContain("{$allowedUser->id} - {$allowedUser->surname} {$allowedUser->name} {$allowedUser->email} ({$allowedCompany->name}, {$unavailableCompany->name})")
        ->not->toContain("{$unavailableUser->id} - {$unavailableUser->surname} {$unavailableUser->name} {$unavailableUser->email} ({$unavailableCompany->name})");

    $assignationWorkbook = workbookFor(new HardwareAssignationTemplateExport($authUser));
    $assignationSheet = $assignationWorkbook->getSheetByName('Assegnazioni hardware');

    expect($assignationWorkbook->getSheetNames())->toBe(['Assegnazioni hardware', 'Tendine'])
        ->and($assignationSheet->getDataValidation('B2')->getFormula1())->toBe('=AziendeDaAssociare')
        ->and($assignationSheet->getDataValidation('F2')->getFormula1())->toBe('=ResponsabiliAssegnazioni')
        ->and($assignationWorkbook->getSheetByName('Tendine')->getSheetState())->toBe(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

    $usersWorkbook = workbookFor(new UserTemplateExport($authUser));
    $usersSheet = $usersWorkbook->getSheetByName('Utenti');
    $usersLists = $usersWorkbook->getSheetByName('Tendine');

    expect($usersWorkbook->getSheetNames())->toBe(['Utenti', 'Tendine'])
        ->and($usersSheet->getDataValidation('D2')->getFormula1())->toBe('=AbilitazioniUtenti')
        ->and($usersSheet->getDataValidation('E2')->getFormula1())->toBe('=AziendeUtenti')
        ->and($usersLists->getCell('B2')->getValue())->toBe("{$allowedCompany->id} - {$allowedCompany->name}")
        ->and($usersLists->getCell('B3')->getValue())->toBeNull()
        ->and($usersLists->getSheetState())->toBe(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
});

it('imports only the main worksheet from templates with visible lists', function () {
    $company = Company::factory()->create();
    $authUser = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $authUser->companies()->attach($company);

    foreach ([
        [new HardwareTemplateExport($authUser), new HardwareImport($authUser)],
        [new HardwareAssignationTemplateExport($authUser), new HardwareAssignationsImport($authUser)],
        [new UserTemplateExport($authUser), new UsersImport($authUser)],
    ] as [$export, $import]) {
        $path = templatePathFor($export);
        Excel::import($import, $path);
        unlink($path);
    }

    expect(true)->toBeTrue();
});

it('creates hardware from a filled template row', function () {
    $company = Company::factory()->create();
    $hardwareType = HardwareType::query()->create(['name' => 'PC test']);
    $authUser = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $serialNumber = 'TEST-'.uniqid();
    $path = templatePathFor(new HardwareTemplateExport($authUser));
    $workbook = IOFactory::load($path);

    $workbook->getSheetByName('Hardware')->fromArray([
        ['Marca test', 'Modello test', $serialNumber, $hardwareType->name, '01/01/2024', null, null, 'CESPITE-'.uniqid(), null, null, 'No', "{$company->id} - {$company->name}", null, null, 'Azienda', 'Nuovo', 'Nuovo', 'No'],
    ], null, 'A2');
    IOFactory::createWriter($workbook, 'Xlsx')->save($path);

    Excel::import(new HardwareImport($authUser), $path);
    unlink($path);

    expect(Hardware::query()->where('serial_number', $serialNumber)->first())
        ->not->toBeNull()
        ->company_id->toBe($company->id)
        ->hardware_type_id->toBe($hardwareType->id);
});

it('creates users from a filled template row and ignores empty dropdown rows', function () {
    Queue::fake();

    $company = Company::factory()->create();
    $authUser = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $email = 'utente-importato-'.uniqid().'@example.test';
    $path = templatePathFor(new UserTemplateExport($authUser));
    $workbook = IOFactory::load($path);

    $workbook->getSheetByName('Utenti')->fromArray([
        ['Pasqualino', 'Testolino', $email, 'UTENTE', "{$company->id} - {$company->name}", '879987987', 'Città del Vaticano', '89879', 'Via Vaticana Papa, 123'],
    ], null, 'A2');
    IOFactory::createWriter($workbook, 'Xlsx')->save($path);

    Excel::import(new UsersImport($authUser), $path);
    unlink($path);

    expect(User::query()->where('email', $email)->first())
        ->not->toBeNull()
        ->city->toBe('Città del Vaticano');
});

it('extracts IDs from dropdown labels while accepting plain IDs', function () {
    foreach ([HardwareImport::class, HardwareAssignationsImport::class, UsersImport::class] as $importClass) {
        $method = new ReflectionMethod($importClass, 'extractId');
        $method->setAccessible(true);
        $authUser = User::factory()->make(['is_admin' => true, 'password' => Hash::make('password')]);
        $import = new $importClass($authUser);

        expect($method->invoke($import, '42 - Voce di esempio'))->toBe(42)
            ->and($method->invoke($import, 42))->toBe(42)
            ->and($method->invoke($import, 'Voce senza ID'))->toBeNull();
    }
});

it('allows only authorized responsible users for the asset company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $authUser = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $supportAdmin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $companyAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $otherCompanyAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $regularUser = User::factory()->create(['password' => Hash::make('password')]);

    $companyAdmin->companies()->attach($company);
    $otherCompanyAdmin->companies()->attach($otherCompany);

    foreach ([HardwareImport::class, HardwareAssignationsImport::class, SoftwareImport::class, SoftwareAssignationsImport::class] as $importClass) {
        $method = new ReflectionMethod($importClass, 'canBeResponsible');
        $method->setAccessible(true);
        $import = new $importClass($authUser);

        expect($method->invoke($import, $supportAdmin, $company->id))->toBeTrue()
            ->and($method->invoke($import, $companyAdmin, $company->id))->toBeTrue()
            ->and($method->invoke($import, $otherCompanyAdmin, $company->id))->toBeFalse()
            ->and($method->invoke($import, $regularUser, $company->id))->toBeFalse();
    }
});

it('ignores blank rows left by template dropdowns', function () {
    $authUser = User::factory()->make(['is_admin' => true, 'password' => Hash::make('password')]);

    foreach ([HardwareImport::class, HardwareAssignationsImport::class] as $importClass) {
        $method = new ReflectionMethod($importClass, 'isEmptyRow');
        $method->setAccessible(true);
        $import = new $importClass($authUser);

        expect($method->invoke($import, collect(array_fill(0, 18, null))))->toBeTrue()
            ->and($method->invoke($import, new Collection(['Marca compilata', null])))->toBeFalse();
    }
});

function workbookFor(object $export): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    return IOFactory::load(templatePathFor($export));
}

function templatePathFor(object $export): string
{
    $path = sys_get_temp_dir().'/import-template-'.uniqid().'.xlsx';
    file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));

    return $path;
}

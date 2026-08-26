<?php

use App\Exports\HardwareAssignationTemplateExport;
use App\Exports\HardwareDeletionTemplateExport;
use App\Exports\HardwareTemplateExport;
use App\Exports\SoftwareAssignationTemplateExport;
use App\Exports\SoftwareDeletionTemplateExport;
use App\Exports\SoftwareTemplateExport;
use App\Exports\UserTemplateExport;
use App\Imports\HardwareAssignationsImport;
use App\Imports\HardwareDeletionsImport;
use App\Imports\HardwareImport;
use App\Imports\SoftwareAssignationsImport;
use App\Imports\SoftwareDeletionsImport;
use App\Imports\SoftwareImport;
use App\Imports\UsersImport;
use App\Models\Company;
use App\Models\Hardware;
use App\Models\HardwareType;
use App\Models\Software;
use App\Models\SoftwareType;
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
    $supportAdmin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $unavailableUser = User::factory()->create(['password' => Hash::make('password')]);

    $authUser->companies()->attach($allowedCompany);
    $allowedUser->companies()->attach($allowedCompany);
    $allowedUser->companies()->attach($unavailableCompany);
    $supportAdmin->companies()->attach($allowedCompany);
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
        ->not->toContain("{$unavailableUser->id} - {$unavailableUser->surname} {$unavailableUser->name} {$unavailableUser->email} ({$unavailableCompany->name})")
        ->and($hardwareSheet->getDataValidation('M2')->getFormula1())->toBe('=UtentiHardware')
        ->and(array_filter(array_column($hardwareLists->rangeToArray('I2:I100'), 0)))
        ->toContain("{$allowedUser->id} - {$allowedUser->surname} {$allowedUser->name} {$allowedUser->email} ({$allowedCompany->name}, {$unavailableCompany->name})")
        ->not->toContain("{$supportAdmin->id} - {$supportAdmin->surname} {$supportAdmin->name} {$supportAdmin->email} ({$allowedCompany->name})");

    $assignationWorkbook = workbookFor(new HardwareAssignationTemplateExport($authUser));
    $assignationSheet = $assignationWorkbook->getSheetByName('Assegnazioni hardware');

    expect($assignationWorkbook->getSheetNames())->toBe(['Assegnazioni hardware', 'Tendine'])
        ->and($assignationSheet->getDataValidation('B2')->getFormula1())->toBe('=AziendeDaAssociare')
        ->and($assignationSheet->getDataValidation('C2')->getFormula1())->toBe('=UtentiHardwareDaAssociare')
        ->and($assignationSheet->getDataValidation('F2')->getFormula1())->toBe('=ResponsabiliAssegnazioni')
        ->and($assignationWorkbook->getSheetByName('Tendine')->getSheetState())->toBe(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

    $softwareType = SoftwareType::query()->create(['name' => 'Gestionale test']);
    $software = Software::query()->create([
        'vendor' => 'Acme',
        'product_name' => 'Suite test',
        'company_asset_number' => 'CESPITE-'.uniqid(),
        'company_id' => $allowedCompany->id,
    ]);
    $softwareWorkbook = workbookFor(new SoftwareTemplateExport($authUser));
    $softwareSheet = $softwareWorkbook->getSheetByName('Software');
    $softwareLists = $softwareWorkbook->getSheetByName('Tendine');

    expect($softwareWorkbook->getSheetNames())->toBe(['Software', 'Tendine'])
        ->and($softwareSheet->getDataValidation('K2')->getFormula1())->toBe('=SiNoUsoEsclusivoSoftware')
        ->and($softwareSheet->getDataValidation('L2')->getFormula1())->toBe('=StatiSoftware')
        ->and($softwareSheet->getDataValidation('N2')->getFormula1())->toBe('=AziendeSoftware')
        ->and($softwareSheet->getDataValidation('O2')->getFormula1())->toBe('=TipiSoftware')
        ->and($softwareSheet->getDataValidation('P2')->getFormula1())->toBe('=UtentiSoftware')
        ->and($softwareSheet->getDataValidation('Q2')->getFormula1())->toBe('=ResponsabiliSoftware')
        ->and($softwareLists->getCell('A2')->getValue())->toBe("{$allowedCompany->id} - {$allowedCompany->name}")
        ->and(array_filter(array_column($softwareLists->rangeToArray('B2:B100'), 0)))->toContain("{$softwareType->id} - {$softwareType->name}")
        ->and($softwareLists->rangeToArray('D2:D3'))->toBe([['Si'], ['No']])
        ->and(array_filter(array_column($softwareLists->rangeToArray('E2:E100'), 0)))->toBe(array_values(config('app.software_statuses')))
        ->and($softwareLists->getSheetState())->toBe(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

    $softwareAssignationWorkbook = workbookFor(new SoftwareAssignationTemplateExport($authUser));
    $softwareAssignationSheet = $softwareAssignationWorkbook->getSheetByName('Assegnazioni software');

    expect($softwareAssignationWorkbook->getSheetNames())->toBe(['Assegnazioni software', 'Tendine'])
        ->and($softwareAssignationSheet->getDataValidation('A2')->getFormula1())->toBe('=SoftwareAssegnazioni')
        ->and($softwareAssignationSheet->getDataValidation('B2')->getFormula1())->toBe('=AziendeSoftwareDaAssociare')
        ->and($softwareAssignationSheet->getDataValidation('C2')->getFormula1())->toBe('=UtentiSoftwareDaAssociare')
        ->and($softwareAssignationSheet->getDataValidation('D2')->getFormula1())->toBe('=AziendeSoftwareDaRimuovere')
        ->and($softwareAssignationSheet->getDataValidation('F2')->getFormula1())->toBe('=ResponsabiliSoftwareAssegnazioni')
        ->and($softwareAssignationWorkbook->getSheetByName('Tendine')->getCell('A2')->getValue())->toBe("{$software->id} - {$software->vendor} {$software->product_name} ({$software->company_asset_number})")
        ->and($softwareAssignationWorkbook->getSheetByName('Tendine')->getSheetState())->toBe(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

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
        [new SoftwareTemplateExport($authUser), new SoftwareImport($authUser)],
        [new SoftwareAssignationTemplateExport($authUser), new SoftwareAssignationsImport($authUser)],
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

it('imports software and assignations from dropdown labels', function () {
    $company = Company::factory()->create();
    $authUser = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $responsibleUser = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $softwareType = SoftwareType::query()->create(['name' => 'Utility test']);
    $path = templatePathFor(new SoftwareTemplateExport($authUser));
    $workbook = IOFactory::load($path);

    $workbook->getSheetByName('Software')->fromArray([
        ['Acme import', 'Prodotto test', null, null, 'CESPITE-'.uniqid(), null, null, null, null, null, 'No', null, null, "{$company->id} - {$company->name}", "{$softwareType->id} - {$softwareType->name}", null, "{$responsibleUser->id} - {$responsibleUser->surname} {$responsibleUser->name} {$responsibleUser->email} ()"],
    ], null, 'A2');
    IOFactory::createWriter($workbook, 'Xlsx')->save($path);

    Excel::import(new SoftwareImport($authUser), $path);
    unlink($path);

    $software = Software::query()->where('vendor', 'Acme import')->firstOrFail();

    expect($software->company_id)->toBe($company->id)
        ->and($software->software_type_id)->toBe($softwareType->id);

    $newCompany = Company::factory()->create();
    $assignationPath = templatePathFor(new SoftwareAssignationTemplateExport($authUser));
    $assignationWorkbook = IOFactory::load($assignationPath);
    $assignationWorkbook->getSheetByName('Assegnazioni software')->fromArray([
        ["{$software->id} - {$software->vendor} {$software->product_name} ({$software->company_asset_number})", "{$newCompany->id} - {$newCompany->name}", null, "{$company->id} - {$company->name}", null, "{$responsibleUser->id} - {$responsibleUser->surname} {$responsibleUser->name} {$responsibleUser->email} ()"],
    ], null, 'A2');
    IOFactory::createWriter($assignationWorkbook, 'Xlsx')->save($assignationPath);

    Excel::import(new SoftwareAssignationsImport($authUser), $assignationPath);
    unlink($assignationPath);

    expect($software->fresh()->company_id)->toBe($newCompany->id);
});

it('extracts IDs from dropdown labels while accepting plain IDs', function () {
    foreach ([HardwareImport::class, HardwareAssignationsImport::class, SoftwareImport::class, SoftwareAssignationsImport::class, UsersImport::class] as $importClass) {
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

    $companyAdminImport = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $companyAdminImport->companies()->attach($company);
    session(['selected_company_id' => $company->id]);

    foreach ([HardwareImport::class, HardwareAssignationsImport::class, SoftwareImport::class, SoftwareAssignationsImport::class] as $importClass) {
        $method = new ReflectionMethod($importClass, 'canBeResponsible');
        $method->setAccessible(true);
        $import = new $importClass($companyAdminImport);

        expect($method->invoke($import, $supportAdmin, $company->id))->toBeFalse()
            ->and($method->invoke($import, $companyAdmin, $company->id))->toBeTrue();
    }
});

it('rejects company admin imports outside the selected company or for privileged users', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $companyAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $supportAdmin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $companyAdmin->companies()->attach($company);
    $supportAdmin->companies()->attach($company);
    session(['selected_company_id' => $company->id]);

    $hardwareRow = collect([
        'Acme', 'Notebook', 'IMPORT-'.uniqid(), null, null, null, null, 'ASSET-'.uniqid(), null, null, 'No', $otherCompany->id, null, null, 'Azienda', 'Nuovo', 'Condizioni all\'acquisto', 'No',
    ]);
    expect(fn () => (new HardwareImport($companyAdmin))->collection(collect([$hardwareRow])))
        ->toThrow(Exception::class, 'Non puoi importare hardware per l\'azienda indicata.');

    $softwareRow = collect([
        'Acme', 'Suite', null, null, 'SOFTWARE-'.uniqid(), null, null, null, null, null, 'No', 'Attiva', null, $otherCompany->id, null, null, null,
    ]);
    expect(fn () => (new SoftwareImport($companyAdmin))->collection(collect([$softwareRow])))
        ->toThrow(Exception::class, 'Non puoi importare software per l\'azienda indicata.');

    $hardware = Hardware::query()->create([
        'make' => 'Acme',
        'model' => 'Notebook',
        'serial_number' => 'OWN-'.uniqid(),
        'company_asset_number' => 'OWN-ASSET-'.uniqid(),
        'company_id' => $company->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);
    $software = Software::query()->create([
        'vendor' => 'Acme',
        'product_name' => 'Suite',
        'company_asset_number' => 'OWN-SOFTWARE-'.uniqid(),
        'company_id' => $company->id,
        'is_exclusive_use' => false,
        'status' => 'active',
    ]);

    expect(fn () => (new HardwareAssignationsImport($companyAdmin))->collection(collect([
        collect([$hardware->id, null, (string) $supportAdmin->id, null, null, $companyAdmin->id]),
    ])))->toThrow(Exception::class, 'non è assegnato alla stessa azienda');

    expect(fn () => (new SoftwareAssignationsImport($companyAdmin))->collection(collect([
        collect([$software->id, null, (string) $supportAdmin->id, null, null, $companyAdmin->id]),
    ])))->toThrow(Exception::class, 'non è assegnato alla stessa azienda');
});

it('allows company admins to import only soft deletion and recovery for their company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $companyAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $companyAdmin->companies()->attach($company);
    session(['selected_company_id' => $company->id]);

    expect((new HardwareDeletionTemplateExport($companyAdmin))->array()[0][1])
        ->toBe('Tipo di eliminazione Soft/Recupero *')
        ->and((new SoftwareDeletionTemplateExport($companyAdmin))->array()[0][1])
        ->toBe('Tipo di eliminazione Soft/Recupero *');

    $hardware = Hardware::query()->create([
        'make' => 'Acme',
        'model' => 'Notebook',
        'serial_number' => 'DELETE-'.uniqid(),
        'company_asset_number' => 'DELETE-ASSET-'.uniqid(),
        'company_id' => $company->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);
    $software = Software::query()->create([
        'vendor' => 'Acme',
        'product_name' => 'Suite',
        'company_asset_number' => 'DELETE-SOFTWARE-'.uniqid(),
        'company_id' => $company->id,
        'is_exclusive_use' => false,
        'status' => 'active',
    ]);

    (new HardwareDeletionsImport($companyAdmin))->collection(collect([
        collect([$hardware->id, 'Soft']),
    ]));
    (new SoftwareDeletionsImport($companyAdmin))->collection(collect([
        collect([$software->id, 'Soft']),
    ]));

    expect($hardware->fresh()->trashed())->toBeTrue()
        ->and($software->fresh()->trashed())->toBeTrue();

    expect(fn () => (new HardwareDeletionsImport($companyAdmin))->collection(collect([
        collect([$hardware->id, 'Definitiva']),
    ])))->toThrow(Exception::class, 'non possono eliminare definitivamente');
    expect(fn () => (new SoftwareDeletionsImport($companyAdmin))->collection(collect([
        collect([$software->id, 'Definitiva']),
    ])))->toThrow(Exception::class, 'non possono eliminare definitivamente');

    (new HardwareDeletionsImport($companyAdmin))->collection(collect([
        collect([$hardware->id, 'Recupero']),
    ]));
    (new SoftwareDeletionsImport($companyAdmin))->collection(collect([
        collect([$software->id, 'Recupero']),
    ]));

    $otherHardware = Hardware::query()->create([
        'make' => 'External',
        'model' => 'Notebook',
        'serial_number' => 'OTHER-DELETE-'.uniqid(),
        'company_asset_number' => 'OTHER-ASSET-'.uniqid(),
        'company_id' => $otherCompany->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);

    expect(fn () => (new HardwareDeletionsImport($companyAdmin))->collection(collect([
        collect([$otherHardware->id, 'Soft']),
    ])))->toThrow(Exception::class, 'non trovato o non autorizzato');
});

it('ignores blank rows left by template dropdowns', function () {
    $authUser = User::factory()->make(['is_admin' => true, 'password' => Hash::make('password')]);

    foreach ([HardwareImport::class, HardwareAssignationsImport::class, SoftwareImport::class, SoftwareAssignationsImport::class] as $importClass) {
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

<?php

use App\Models\Company;
use App\Models\Hardware;
use App\Models\Software;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

it('allows a company admin to create hardware only for assignable users in their company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $companyAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $companyUser = User::factory()->create(['password' => Hash::make('password')]);
    $supportAdmin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $externalUser = User::factory()->create(['password' => Hash::make('password')]);

    $companyAdmin->companies()->attach($company);
    $companyUser->companies()->attach($company);
    $supportAdmin->companies()->attach($company);
    $externalUser->companies()->attach($otherCompany);
    Sanctum::actingAs($companyAdmin);

    $payload = [
        'make' => 'Acme',
        'model' => 'Notebook',
        'serial_number' => 'COMPANY-ADMIN-HW-'.uniqid(),
        'is_accessory' => false,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
        'support_label' => 'LABEL-'.uniqid(),
        'company_id' => $company->id,
        'users' => [$companyUser->id],
    ];

    $hardwareId = $this->postJson('/api/hardware', $payload)
        ->assertCreated()
        ->assertJsonPath('hardware.company_id', $company->id)
        ->json('hardware.id');

    $this->postJson('/api/hardware', [...$payload, 'serial_number' => 'ADMIN-'.uniqid(), 'users' => [$supportAdmin->id]])
        ->assertBadRequest();

    $this->postJson('/api/hardware', [...$payload, 'serial_number' => 'EXTERNAL-'.uniqid(), 'users' => [$externalUser->id]])
        ->assertBadRequest();

    $this->postJson('/api/hardware', [...$payload, 'serial_number' => 'OTHER-'.uniqid(), 'company_id' => $otherCompany->id, 'users' => []])
        ->assertForbidden();

    $this->getJson("/api/companies/{$company->id}/allusers")
        ->assertOk()
        ->assertJsonFragment(['id' => $companyUser->id])
        ->assertJsonMissing(['id' => $supportAdmin->id]);

    $softwarePayload = [
        'vendor' => 'Acme',
        'product_name' => 'Company Suite',
        'company_asset_number' => 'SOFTWARE-'.uniqid(),
        'is_exclusive_use' => false,
        'status' => 'active',
        'company_id' => $company->id,
        'users' => [$companyUser->id],
    ];

    $softwareId = $this->postJson('/api/software', $softwarePayload)
        ->assertCreated()
        ->assertJsonPath('software.company_id', $company->id)
        ->json('software.id');

    $this->postJson('/api/software', [...$softwarePayload, 'company_asset_number' => 'SOFTWARE-ADMIN-'.uniqid(), 'users' => [$supportAdmin->id]])
        ->assertBadRequest();

    $this->postJson('/api/software', [...$softwarePayload, 'company_asset_number' => 'SOFTWARE-OTHER-'.uniqid(), 'company_id' => $otherCompany->id, 'users' => []])
        ->assertForbidden();

    $assignmentPayload = [
        'company_id' => $company->id,
        'users' => [$supportAdmin->id],
        'responsible_user_id' => $companyAdmin->id,
    ];

    $this->patchJson("/api/hardware-users/{$hardwareId}", $assignmentPayload)
        ->assertBadRequest();

    $this->patchJson("/api/software-users/{$softwareId}", $assignmentPayload)
        ->assertBadRequest();

    Hardware::findOrFail($hardwareId)->users()->attach($supportAdmin->id, [
        'created_by' => $supportAdmin->id,
        'responsible_user_id' => $supportAdmin->id,
    ]);
    Software::findOrFail($softwareId)->users()->attach($supportAdmin->id, [
        'created_by' => $supportAdmin->id,
        'responsible_user_id' => $supportAdmin->id,
    ]);

    $hardwareUsers = collect($this->getJson('/api/hardware-list')->assertOk()->json('hardwareList'))
        ->firstWhere('id', $hardwareId)['users'];
    $softwareUsers = collect($this->getJson('/api/software-list')->assertOk()->json('softwareList'))
        ->firstWhere('id', $softwareId)['users'];

    expect(collect($hardwareUsers)->pluck('id'))->not->toContain($supportAdmin->id)
        ->and(collect($softwareUsers)->pluck('id'))->not->toContain($supportAdmin->id);

    Hardware::findOrFail($hardwareId)->users()->detach($supportAdmin->id);
    Software::findOrFail($softwareId)->users()->detach($supportAdmin->id);

    $validAssignmentPayload = [
        'company_id' => $company->id,
        'users' => [$companyUser->id],
        'responsible_user_id' => $companyAdmin->id,
    ];

    $this->patchJson("/api/hardware-users/{$hardwareId}", $validAssignmentPayload)
        ->assertOk();
    $this->patchJson("/api/software-users/{$softwareId}", $validAssignmentPayload)
        ->assertOk();

    $emptyAssignmentPayload = [
        'company_id' => $company->id,
        'users' => [],
        'responsible_user_id' => null,
    ];

    $this->patchJson("/api/hardware-users/{$hardwareId}", $emptyAssignmentPayload)
        ->assertOk();
    $this->patchJson("/api/software-users/{$softwareId}", $emptyAssignmentPayload)
        ->assertOk();

    $this->deleteJson("/api/hardware/{$hardwareId}")
        ->assertOk();
    $this->deleteJson("/api/hardware-trashed/{$hardwareId}")
        ->assertForbidden();
    $this->postJson("/api/hardware-restore/{$hardwareId}")
        ->assertOk();

    $this->deleteJson("/api/software/{$softwareId}")
        ->assertOk();
    $this->deleteJson("/api/software-trashed/{$softwareId}")
        ->assertForbidden();
    $this->postJson("/api/software-restore/{$softwareId}")
        ->assertOk();

    $otherHardware = Hardware::query()->create([
        'make' => 'External',
        'model' => 'Notebook',
        'serial_number' => 'EXTERNAL-DELETE-'.uniqid(),
        'company_asset_number' => 'EXTERNAL-ASSET-'.uniqid(),
        'company_id' => $otherCompany->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);
    $otherSoftware = Software::query()->create([
        'vendor' => 'External',
        'product_name' => 'Suite',
        'company_asset_number' => 'EXTERNAL-SOFTWARE-'.uniqid(),
        'company_id' => $otherCompany->id,
        'is_exclusive_use' => false,
        'status' => 'active',
    ]);

    $this->deleteJson("/api/hardware/{$otherHardware->id}")
        ->assertForbidden();
    $this->deleteJson("/api/software/{$otherSoftware->id}")
        ->assertForbidden();
});

it('keeps hardware assignments unchanged when updating only hardware data', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $responsibleUser = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $assignedUser = User::factory()->create(['password' => Hash::make('password')]);

    $responsibleUser->companies()->attach($company);
    $assignedUser->companies()->attach($company);

    $hardware = Hardware::query()->create([
        'make' => 'Acme',
        'model' => 'Notebook',
        'serial_number' => 'DATA-UPDATE-'.uniqid(),
        'company_asset_number' => 'ASSET-'.uniqid(),
        'company_id' => $company->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);

    $hardware->users()->attach($assignedUser->id, [
        'created_by' => $admin->id,
        'responsible_user_id' => $responsibleUser->id,
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/hardware/{$hardware->id}", [
        'make' => 'Acme Updated',
        'model' => $hardware->model,
        'serial_number' => $hardware->serial_number,
        'is_accessory' => false,
        'is_exclusive_use' => false,
        'status_at_purchase' => $hardware->status_at_purchase,
        'status' => $hardware->status,
        'position' => $hardware->position,
        'company_asset_number' => $hardware->company_asset_number,
        'support_label' => $hardware->support_label,
        'purchase_date' => $hardware->purchase_date,
        'hardware_type_id' => $hardware->hardware_type_id,
        'ownership_type' => $hardware->ownership_type,
        'ownership_type_note' => $hardware->ownership_type_note,
        'notes' => $hardware->notes,
    ])->assertCreated();

    $assignedHardwareUser = $hardware->fresh()->users()->whereKey($assignedUser->id)->first();

    expect($assignedHardwareUser)->not->toBeNull()
        ->and($assignedHardwareUser->pivot->responsible_user_id)->toBe($responsibleUser->id);
});

it('allows a company admin to view trashed assets only for their company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $companyAdmin = User::factory()->create([
        'is_company_admin' => true,
        'password' => Hash::make('password'),
    ]);

    $companyAdmin->companies()->attach($company);
    Sanctum::actingAs($companyAdmin);

    $companyHardware = Hardware::query()->create([
        'make' => 'Acme',
        'model' => 'Notebook',
        'serial_number' => 'COMPANY-TRASHED-'.uniqid(),
        'company_asset_number' => 'COMPANY-ASSET-'.uniqid(),
        'company_id' => $company->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);
    $otherHardware = Hardware::query()->create([
        'make' => 'External',
        'model' => 'Notebook',
        'serial_number' => 'OTHER-TRASHED-'.uniqid(),
        'company_asset_number' => 'OTHER-ASSET-'.uniqid(),
        'company_id' => $otherCompany->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);
    $companyHardware->delete();
    $otherHardware->delete();

    $companySoftware = Software::query()->create([
        'vendor' => 'Acme',
        'product_name' => 'Company Suite',
        'company_asset_number' => 'COMPANY-SOFTWARE-'.uniqid(),
        'company_id' => $company->id,
        'is_exclusive_use' => false,
        'status' => 'active',
    ]);
    $otherSoftware = Software::query()->create([
        'vendor' => 'External',
        'product_name' => 'External Suite',
        'company_asset_number' => 'OTHER-SOFTWARE-'.uniqid(),
        'company_id' => $otherCompany->id,
        'is_exclusive_use' => false,
        'status' => 'active',
    ]);
    $companySoftware->delete();
    $otherSoftware->delete();

    $this->getJson('/api/hardware-list-full')
        ->assertOk()
        ->assertJsonFragment(['id' => $companyHardware->id])
        ->assertJsonMissing(['id' => $otherHardware->id]);

    $this->getJson('/api/software-list-full')
        ->assertOk()
        ->assertJsonFragment(['id' => $companySoftware->id])
        ->assertJsonMissing(['id' => $otherSoftware->id]);

    $this->getJson('/api/hardware-list-full?company_id='.$otherCompany->id)
        ->assertOk()
        ->assertJsonFragment(['id' => $companyHardware->id])
        ->assertJsonMissing(['id' => $otherHardware->id]);

    $this->getJson('/api/software-list-full?company_id='.$otherCompany->id)
        ->assertOk()
        ->assertJsonFragment(['id' => $companySoftware->id])
        ->assertJsonMissing(['id' => $otherSoftware->id]);

    $supportAdmin = User::factory()->create([
        'is_admin' => true,
        'password' => Hash::make('password'),
    ]);
    Sanctum::actingAs($supportAdmin);

    $this->getJson('/api/hardware-list-full')
        ->assertOk()
        ->assertJsonFragment(['id' => $companyHardware->id])
        ->assertJsonFragment(['id' => $otherHardware->id]);

    $this->getJson('/api/software-list-full')
        ->assertOk()
        ->assertJsonFragment(['id' => $companySoftware->id])
        ->assertJsonFragment(['id' => $otherSoftware->id]);
});

it('allows a company admin to view the detail of deleted hardware in their company', function () {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $companyAdmin = User::factory()->create([
        'is_company_admin' => true,
        'password' => Hash::make('password'),
    ]);

    $companyAdmin->companies()->attach($company);
    Sanctum::actingAs($companyAdmin);

    $hardware = Hardware::query()->create([
        'make' => 'Acme',
        'model' => 'Notebook',
        'serial_number' => 'DETAIL-TRASHED-'.uniqid(),
        'company_asset_number' => 'DETAIL-ASSET-'.uniqid(),
        'company_id' => $company->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);
    $otherHardware = Hardware::query()->create([
        'make' => 'External',
        'model' => 'Notebook',
        'serial_number' => 'OTHER-DETAIL-TRASHED-'.uniqid(),
        'company_asset_number' => 'OTHER-DETAIL-ASSET-'.uniqid(),
        'company_id' => $otherCompany->id,
        'is_exclusive_use' => false,
        'status_at_purchase' => 'new',
        'status' => 'original_condition',
        'position' => 'company',
    ]);
    $hardware->delete();
    $otherHardware->delete();

    $this->getJson('/api/hardware/'.$hardware->id)
        ->assertOk()
        ->assertJsonPath('hardware.id', $hardware->id)
        ->assertJsonPath('hardware.deleted_at', $hardware->deleted_at->toJSON());

    $this->getJson('/api/hardware/'.$otherHardware->id)
        ->assertForbidden();
});

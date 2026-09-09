<?php

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    config(['scout.driver' => 'null']);
});

function createMainTicketTestTicket(Company $company, User $user, TicketTypeCategory $category): Ticket
{
    $ticketType = TicketType::factory()->create([
        'company_id' => $company->id,
        'ticket_type_category_id' => $category->id,
    ]);

    return Ticket::withoutSyncingToSearch(fn () => Ticket::query()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'type_id' => $ticketType->id,
        'status' => 0,
        'description' => 'Ticket di test',
        'duration' => 0,
        'sla_take' => 60,
        'sla_solve' => 120,
        'priority' => 'low',
    ]));
}

it('allows admins to associate tickets from different companies to a main ticket', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();
    $firstUser = User::factory()->create(['password' => Hash::make('password')]);
    $secondUser = User::factory()->create(['password' => Hash::make('password')]);
    $category = TicketTypeCategory::factory()->create();
    $mainTicket = createMainTicketTestTicket($firstCompany, $firstUser, $category);
    $childTicket = createMainTicketTestTicket($secondCompany, $secondUser, $category);

    Sanctum::actingAs($admin);

    $this->postJson("/api/ticket/{$mainTicket->id}/make-main")
        ->assertOk();

    $this->postJson("/api/ticket/{$childTicket->id}/connect-to-main", [
        'main_id' => $mainTicket->id,
    ])->assertOk();

    expect($childTicket->fresh()->main_id)->toBe($mainTicket->id);
    expect($mainTicket->fresh()->is_main)->toBeTrue();
});

it('does not allow a main ticket to be associated with another main ticket', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $company = Company::factory()->create();
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $category = TicketTypeCategory::factory()->create();
    $firstMain = createMainTicketTestTicket($company, $user, $category);
    $secondMain = createMainTicketTestTicket($company, $user, $category);
    $firstMain->update(['is_main' => true]);
    $secondMain->update(['is_main' => true]);

    Sanctum::actingAs($admin);

    $this->postJson("/api/ticket/{$secondMain->id}/connect-to-main", [
        'main_id' => $firstMain->id,
    ])->assertBadRequest();
});

it('removing a main ticket dissociates all its tickets', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $company = Company::factory()->create();
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $category = TicketTypeCategory::factory()->create();
    $mainTicket = createMainTicketTestTicket($company, $user, $category);
    $childTicket = createMainTicketTestTicket($company, $user, $category);
    $mainTicket->update(['is_main' => true]);
    $childTicket->update(['main_id' => $mainTicket->id]);

    Sanctum::actingAs($admin);

    $this->postJson("/api/ticket/{$mainTicket->id}/remove-main")
        ->assertOk()
        ->assertJsonPath('disassociated_count', 1);

    expect($mainTicket->fresh()->is_main)->toBeFalse();
    expect($childTicket->fresh()->main_id)->toBeNull();
});

it('allows an admin to grant and revoke main ticket access for another company', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $ownerCompany = Company::factory()->create();
    $enabledCompany = Company::factory()->create();
    $user = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $category = TicketTypeCategory::factory()->create();
    $mainTicket = createMainTicketTestTicket($ownerCompany, $user, $category);
    $mainTicket->update(['is_main' => true]);
    $user->companies()->attach($enabledCompany);

    Sanctum::actingAs($admin);

    $this->putJson("/api/ticket/{$mainTicket->id}/main-access-companies", [
        'company_ids' => [$enabledCompany->id],
    ])->assertOk();

    Sanctum::actingAs($user);

    $this->getJson("/api/main-ticket-access/{$mainTicket->id}")
        ->assertOk()
        ->assertJsonPath('ticket.id', $mainTicket->id);

    $this->getJson("/api/ticket/{$mainTicket->id}/user-main-ticket-connections")
        ->assertOk()
        ->assertJsonPath('connected_tickets', []);

    Sanctum::actingAs($admin);
    $this->putJson("/api/ticket/{$mainTicket->id}/main-access-companies", [
        'company_ids' => [],
    ])->assertOk();

    Sanctum::actingAs($user);
    $this->getJson("/api/main-ticket-access/{$mainTicket->id}")
        ->assertUnauthorized();
});

it('removes company access when its last connected ticket is removed', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $ownerCompany = Company::factory()->create();
    $childCompany = Company::factory()->create();
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $category = TicketTypeCategory::factory()->create();
    $mainTicket = createMainTicketTestTicket($ownerCompany, $user, $category);
    $childTicket = createMainTicketTestTicket($childCompany, $user, $category);
    $mainTicket->update(['is_main' => true]);
    $childTicket->update(['main_id' => $mainTicket->id]);
    $mainTicket->mainAccessCompanies()->attach($childCompany);

    Sanctum::actingAs($admin);

    $this->postJson("/api/ticket/{$childTicket->id}/remove-main-connection")
        ->assertOk();

    expect($mainTicket->fresh()->mainAccessCompanies()->whereKey($childCompany->id)->exists())->toBeFalse();
});

it('allows a company admin without a selected company to load an authorized main ticket', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
    $ownerCompany = Company::factory()->create();
    $enabledCompany = Company::factory()->create();
    $companyAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $category = TicketTypeCategory::factory()->create();
    $mainTicket = createMainTicketTestTicket($ownerCompany, $companyAdmin, $category);
    $mainTicket->update(['is_main' => true]);
    $companyAdmin->companies()->attach($enabledCompany);
    $mainTicket->mainAccessCompanies()->attach($enabledCompany);

    Sanctum::actingAs($companyAdmin);

    $this->getJson('/api/user')->assertOk();
    $this->getJson("/api/main-ticket-access/{$mainTicket->id}")->assertOk();
});

it('allows an enabled company admin to load closing messages and referer name of the main ticket', function () {
    $ownerCompany = Company::factory()->create();
    $enabledCompany = Company::factory()->create();
    $ownerAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $referer = User::factory()->create(['password' => Hash::make('password')]);
    $referer->companies()->attach($ownerCompany);
    $companyAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $companyAdmin->companies()->attach($enabledCompany);
    $category = TicketTypeCategory::factory()->create();
    $mainTicket = createMainTicketTestTicket($ownerCompany, $ownerAdmin, $category);
    $mainTicket->update(['is_main' => true, 'referer_id' => $referer->id]);
    $mainTicket->mainAccessCompanies()->attach($enabledCompany);

    Sanctum::actingAs($companyAdmin);

    $this->getJson("/api/ticket/{$mainTicket->id}/closing-messages")->assertOk();
    $this->getJson("/api/user/{$referer->id}/get-name?main_ticket_id={$mainTicket->id}")->assertOk();
});

it('does not leak access to a sibling main ticket of the same owner company', function () {
    $ownerCompany = Company::factory()->create();
    $enabledCompany = Company::factory()->create();
    $ownerAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $granterReferer = User::factory()->create(['password' => Hash::make('password')]);
    $granterReferer->companies()->attach($ownerCompany);
    $otherReferer = User::factory()->create(['password' => Hash::make('password')]);
    $otherReferer->companies()->attach($ownerCompany);
    $companyAdmin = User::factory()->create(['is_company_admin' => true, 'password' => Hash::make('password')]);
    $companyAdmin->companies()->attach($enabledCompany);
    $category = TicketTypeCategory::factory()->create();

    // Only the first main ticket grants access to $enabledCompany.
    $grantedMainTicket = createMainTicketTestTicket($ownerCompany, $ownerAdmin, $category);
    $grantedMainTicket->update(['is_main' => true, 'referer_id' => $granterReferer->id]);
    $grantedMainTicket->mainAccessCompanies()->attach($enabledCompany);

    $ungrantedMainTicket = createMainTicketTestTicket($ownerCompany, $ownerAdmin, $category);
    $ungrantedMainTicket->update(['is_main' => true, 'referer_id' => $otherReferer->id]);

    $office = \App\Models\Office::factory()->create(['company_id' => $ownerCompany->id]);
    \App\Models\TicketMessage::factory()->create([
        'ticket_id' => $grantedMainTicket->id,
        'user_id' => $ownerAdmin->id,
        'message' => json_encode(['office' => $office->id]),
    ]);

    Sanctum::actingAs($companyAdmin);

    // Access to the granted main ticket, its own referer and its own office works.
    $this->getJson("/api/main-ticket-access/{$grantedMainTicket->id}")->assertOk();
    $this->getJson("/api/user/{$granterReferer->id}/get-name?main_ticket_id={$grantedMainTicket->id}")->assertOk();
    $this->getJson("/api/offices/{$office->id}?main_ticket_id={$grantedMainTicket->id}")->assertOk();

    // The sibling main ticket, not explicitly granted, must stay unreachable...
    $this->getJson("/api/main-ticket-access/{$ungrantedMainTicket->id}")->assertUnauthorized();
    // ...and its referer must not be reachable just by referencing the granted ticket id.
    $this->getJson("/api/user/{$otherReferer->id}/get-name?main_ticket_id={$grantedMainTicket->id}")->assertUnauthorized();
    // Passing the correct (but ungranted) ticket id must also fail.
    $this->getJson("/api/user/{$otherReferer->id}/get-name?main_ticket_id={$ungrantedMainTicket->id}")->assertUnauthorized();
});

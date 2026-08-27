<?php

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

it('lets support admins update their support author display preference', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'password' => Hash::make('password'),
        'support_author_display' => 'id',
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson('/api/user/preferences', [
        'support_author_display' => 'full_name',
    ])
        ->assertOk()
        ->assertJsonPath('user.support_author_display', 'full_name');

    expect($admin->fresh()->support_author_display)->toBe('full_name');
});

it('does not expose support user identifiers in ticket messages returned to company users', function () {
    $company = Company::factory()->create();
    $companyUser = User::factory()->create([
        'password' => Hash::make('password'),
    ]);
    $supportAdmin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Ada',
        'password' => Hash::make('password'),
        'surname' => 'Lovelace',
    ]);
    $ticketTypeCategory = TicketTypeCategory::factory()->create();
    $ticketType = TicketType::factory()->create([
        'company_id' => $company->id,
        'ticket_type_category_id' => $ticketTypeCategory->id,
    ]);

    $companyUser->companies()->attach($company);

    $ticket = Ticket::withoutSyncingToSearch(fn () => Ticket::query()->create([
        'user_id' => $companyUser->id,
        'company_id' => $company->id,
        'type_id' => $ticketType->id,
        'status' => '0',
        'description' => 'Richiesta di test',
        'duration' => '0',
        'sla_take' => 60,
        'sla_solve' => 120,
        'priority' => 'low',
    ]));

    TicketMessage::withoutSyncingToSearch(fn () => TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supportAdmin->id,
        'message' => 'Risposta del supporto',
    ]));

    Sanctum::actingAs($companyUser);

    $this->getJson("/api/ticket/{$ticket->id}/messages")
        ->assertOk()
        ->assertJsonPath('ticket_messages.0.user.is_admin', 1)
        ->assertJsonMissingPath('ticket_messages.0.user_id')
        ->assertJsonMissingPath('ticket_messages.0.user.id')
        ->assertJsonMissingPath('ticket_messages.0.user.name')
        ->assertJsonMissingPath('ticket_messages.0.user.surname')
        ->assertJsonMissing(['name' => 'Ada'])
        ->assertJsonMissing(['surname' => 'Lovelace']);
});

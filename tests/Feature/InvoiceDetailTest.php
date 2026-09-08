<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

it('returns ticket company data in the invoice detail', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'password' => Hash::make('password'),
    ]);
    $ticketUser = User::factory()->create([
        'password' => Hash::make('password'),
    ]);
    $company = Company::factory()->create(['name' => 'Azienda filtro ticket']);
    $invoice = Invoice::factory()->create(['company_id' => $company->id]);
    $ticketType = TicketType::factory()->create([
        'company_id' => $company->id,
        'ticket_type_category_id' => TicketTypeCategory::factory(),
    ]);

    $ticket = Ticket::withoutSyncingToSearch(fn () => Ticket::query()->create([
        'company_id' => $company->id,
        'user_id' => $ticketUser->id,
        'type_id' => $ticketType->id,
        'status' => '0',
        'description' => 'Ticket collegato alla fattura',
        'duration' => '0',
        'sla_take' => 60,
        'sla_solve' => 120,
        'priority' => 'low',
        'invoice_id' => $invoice->id,
    ]));

    Sanctum::actingAs($admin);

    $this->getJson("/api/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('invoice.tickets.0.id', $ticket->id)
        ->assertJsonPath('invoice.tickets.0.company.name', 'Azienda filtro ticket');
});

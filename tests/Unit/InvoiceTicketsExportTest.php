<?php

use App\Exports\InvoiceTicketsExport;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketStage;
use App\Models\TicketStatusUpdate;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

it('exports invoice tickets with ticket links, author label and invoice sheet', function () {
    config(['app.frontend_url' => 'https://frontend.test']);

    $company = Company::factory()->create();
    $invoice = Invoice::factory()->create([
        'number' => 'FT/2026/42',
        'description' => 'Canone e interventi settembre',
        'company_id' => $company->id,
        'invoice_date' => '2026-09-08',
    ]);
    $brand = Brand::factory()->create();
    $category = TicketTypeCategory::factory()->create(['name' => 'Incident']);
    $ticketType = TicketType::factory()->create([
        'company_id' => $company->id,
        'brand_id' => $brand->id,
        'ticket_type_category_id' => $category->id,
        'name' => 'Postazione bloccata',
    ]);
    $closedStage = TicketStage::query()->where('system_key', 'closed')->first()
        ?? TicketStage::query()->forceCreate([
            'name' => 'Chiuso',
            'system_key' => 'closed',
            'order' => 99,
            'is_system' => true,
        ]);
    $admin = User::factory()->create([
        'is_admin' => true,
        'is_superadmin' => false,
        'name' => 'Ada',
        'password' => Hash::make('password'),
        'surname' => 'Lovelace',
    ]);

    $ticket = Ticket::withoutSyncingToSearch(fn () => Ticket::query()->create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
        'stage_id' => $closedStage->id,
        'status' => '0',
        'description' => "Prima riga\r\nSeconda riga; con tab\t e virgole, senza rompere celle",
        'duration' => '0',
        'sla_take' => 60,
        'sla_solve' => 120,
        'priority' => 'low',
        'type_id' => $ticketType->id,
        'invoice_id' => $invoice->id,
    ]));

    TicketStatusUpdate::query()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $admin->id,
        'content' => 'Ticket chiuso',
        'type' => 'closing',
    ]);

    [$ticketsSheet, $invoiceSheet] = (new InvoiceTicketsExport($invoice))->sheets();
    $ticketRows = $ticketsSheet->array();

    expect($ticketsSheet->title())->toBe('Ticket fattura FT_2026_42')
        ->and($ticketRows[0])->toBe([
            'Numero ticket',
            'Link admin',
            'Link utente',
            'Azienda',
            'Categoria',
            'Tipologia',
            'Data apertura',
            'Descrizione',
            'Data chiusura',
            'Aperto da',
        ])
        ->and($ticketRows[1][0])->toBe($ticket->id)
        ->and($ticketRows[1][1])->toBe("https://frontend.test/support/admin/ticket/{$ticket->id}")
        ->and($ticketRows[1][2])->toBe("https://frontend.test/support/user/ticket/{$ticket->id}")
        ->and($ticketRows[1][3])->toBe($company->name)
        ->and($ticketRows[1][4])->toBe('Incident')
        ->and($ticketRows[1][5])->toBe('Postazione bloccata')
        ->and($ticketRows[1][7])->toBe("Prima riga\nSeconda riga; con tab\t e virgole, senza rompere celle")
        ->and($ticketRows[1][8])->not->toBeNull()
        ->and($ticketRows[1][9])->toBe('Supporto')
        ->and($invoiceSheet->title())->toBe('Fattura FT_2026_42')
        ->and($invoiceSheet->array()[0])->toBe(['Campo', 'Valore'])
        ->and($invoiceSheet->array()[1])->toBe(['Numero', 'FT/2026/42'])
        ->and($invoiceSheet->array()[2])->toBe(['Azienda', $company->name])
        ->and($invoiceSheet->array()[3])->toBe(['Data emissione', '2026-09-08'])
        ->and($invoiceSheet->array()[4])->toBe(['Descrizione', 'Canone e interventi settembre']);
});

it('downloads invoice tickets using the invoice number in the file name', function () {
    Excel::fake();

    $admin = User::factory()->create([
        'is_admin' => true,
        'password' => Hash::make('password'),
    ]);
    $invoice = Invoice::factory()->create(['number' => 'FT/2026/42']);

    Sanctum::actingAs($admin);

    $this->get("/api/invoices/{$invoice->id}/tickets/export")->assertOk();

    Excel::assertDownloaded('fattura_FT_2026_42_ticket.xlsx', function (InvoiceTicketsExport $export): bool {
        return count($export->sheets()) === 2;
    });
});

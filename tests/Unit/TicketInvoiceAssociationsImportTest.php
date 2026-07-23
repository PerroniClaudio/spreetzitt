<?php

use App\Exports\TicketInvoiceAssociationTemplateExport;
use App\Imports\TicketInvoiceAssociationsImport;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

it('exports the expected ticket-invoice association template headers', function () {
    expect((new TicketInvoiceAssociationTemplateExport)->array())->toBe([[
        'ID ticket *',
        'ID fattura',
        'Numero fattura',
        'Anno fattura',
        'Sovrascrivi fattura esistente (SI/NO). Se lasciato vuoto si assume NO',
    ]]);
});

it('imports a ticket-invoice association when the ticket has no invoice', function () {
    $ticket = ticketForInvoiceAssociationImport();
    $invoice = Invoice::factory()->create();

    (new TicketInvoiceAssociationsImport)->collection(associationRows([
        [$ticket->id, $invoice->id, null, null, null],
    ]));

    expect($ticket->fresh()->invoice_id)->toBe($invoice->id);
});

it('imports a ticket-invoice association by invoice number and year when invoice id is absent', function () {
    $ticket = ticketForInvoiceAssociationImport();
    $invoice = Invoice::factory()->create([
        'number' => 'FAT-2026-42',
        'invoice_date' => '2026-05-15',
    ]);

    (new TicketInvoiceAssociationsImport)->collection(associationRows([
        [$ticket->id, null, $invoice->number, 2026, null],
    ]));

    expect($ticket->fresh()->invoice_id)->toBe($invoice->id);
});

it('uses the invoice id when both invoice identifiers are present', function () {
    $ticket = ticketForInvoiceAssociationImport();
    $invoiceById = Invoice::factory()->create();

    (new TicketInvoiceAssociationsImport)->collection(associationRows([
        [$ticket->id, $invoiceById->id, 'non-esistente', 2026, null],
    ]));

    expect($ticket->fresh()->invoice_id)->toBe($invoiceById->id);
});

it('rejects an invoice number and year that identify no invoice', function () {
    $ticket = ticketForInvoiceAssociationImport();

    expect(fn () => (new TicketInvoiceAssociationsImport)->collection(associationRows([
        [$ticket->id, null, 'FAT-INESISTENTE', 2026, null],
    ])))->toThrow(RuntimeException::class, 'non è stata trovata alcuna fattura');

    expect($ticket->fresh()->invoice_id)->toBeNull();
});

it('rolls back all associations when a ticket already has an invoice without overwrite permission', function () {
    $firstTicket = ticketForInvoiceAssociationImport();
    $secondTicket = ticketForInvoiceAssociationImport();
    $existingInvoice = Invoice::factory()->create();
    $newInvoice = Invoice::factory()->create();

    $secondTicket->update(['invoice_id' => $existingInvoice->id]);

    expect(fn () => (new TicketInvoiceAssociationsImport)->collection(associationRows([
        [$firstTicket->id, $newInvoice->id, null, null, null],
        [$secondTicket->id, $newInvoice->id, null, null, 'no'],
    ])))->toThrow(RuntimeException::class, "ticket {$secondTicket->id} è già associato");

    expect($firstTicket->fresh()->invoice_id)->toBeNull()
        ->and($secondTicket->fresh()->invoice_id)->toBe($existingInvoice->id);
});

it('overwrites an existing ticket invoice only when the overwrite column is set to si', function () {
    $ticket = ticketForInvoiceAssociationImport();
    $existingInvoice = Invoice::factory()->create();
    $replacementInvoice = Invoice::factory()->create();

    $ticket->update(['invoice_id' => $existingInvoice->id]);

    (new TicketInvoiceAssociationsImport)->collection(associationRows([
        [$ticket->id, $replacementInvoice->id, null, null, 'si'],
    ]));

    expect($ticket->fresh()->invoice_id)->toBe($replacementInvoice->id);
});

/**
 * @param  array<int, array<int, int|string|null>>  $associations
 */
function associationRows(array $associations): Collection
{
    return new Collection([
        collect(['ID ticket *', 'ID fattura', 'Numero fattura', 'Anno fattura', 'Sovrascrivi fattura esistente (SI/NO). Se lasciato vuoto si assume NO']),
        ...array_map(fn (array $association): Collection => collect($association), $associations),
    ]);
}

function ticketForInvoiceAssociationImport(): Ticket
{
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $ticketType = TicketType::factory()->create([
        'company_id' => $company->id,
        'ticket_type_category_id' => TicketTypeCategory::factory(),
    ]);

    return Ticket::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'status' => '0',
        'description' => 'Ticket di test per l’importazione delle associazioni fattura.',
        'duration' => '0',
        'type_id' => $ticketType->id,
    ]);
}

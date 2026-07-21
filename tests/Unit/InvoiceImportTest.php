<?php

use App\Imports\InvoiceImport;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePaymentStage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

it('imports only the first worksheet containing invoices', function () {
    expect((new InvoiceImport)->sheets())->toHaveKey(0)->toHaveCount(1);
});

it('imports invoices using company and payment stage names', function () {
    $company = Company::factory()->create(['name' => 'Azienda import '.fake()->uuid()]);
    $paymentStage = InvoicePaymentStage::factory()->create(['name' => 'Stato import '.fake()->uuid()]);

    (new InvoiceImport)->collection(new Collection([
        collect(['Numero fattura *', 'Descrizione', 'Azienda', 'Stato pagamento', 'Data emissione (gg/mm/aaaa) *']),
        collect(['IMPORT-'.fake()->uuid(), 'Descrizione di prova', $company->name, $paymentStage->name, '15/01/2026']),
    ]));

    expect(Invoice::query()->where('company_id', $company->id)->first())
        ->payment_stage_id->toBe($paymentStage->id)
        ->invoice_date->format('Y-m-d')->toBe('2026-01-15');
});

it('rolls back the entire import when a row contains an invalid company', function () {
    $invoiceNumber = 'IMPORT-'.fake()->uuid();

    expect(fn () => (new InvoiceImport)->collection(new Collection([
        collect(['Numero fattura *', 'Descrizione', 'Azienda', 'Stato pagamento', 'Data emissione (gg/mm/aaaa) *']),
        collect([$invoiceNumber, null, null, null, '15/01/2026']),
        collect(['IMPORT-'.fake()->uuid(), null, 'Azienda inesistente', null, '16/01/2026']),
    ])))->toThrow(RuntimeException::class);

    expect(Invoice::query()->where('number', $invoiceNumber)->exists())->toBeFalse();
});

it('ignores completely blank template rows', function () {
    $invoiceNumber = 'IMPORT-'.fake()->uuid();

    (new InvoiceImport)->collection(new Collection([
        collect(['Numero fattura *', 'Descrizione', 'Azienda', 'Stato pagamento', 'Data emissione (gg/mm/aaaa) *']),
        collect([$invoiceNumber, null, null, null, '12/12/2024']),
        collect([null, null, null, null, null]),
    ]));

    expect(Invoice::query()->where('number', $invoiceNumber)->exists())->toBeTrue();
});

it('accepts issue dates with alternative Excel separators', function () {
    $invoiceNumber = 'IMPORT-'.fake()->uuid();

    (new InvoiceImport)->collection(new Collection([
        collect(['Numero fattura *', 'Descrizione', 'Azienda', 'Stato pagamento', 'Data emissione (gg/mm/aaaa) *']),
        collect([$invoiceNumber, null, null, null, "\u{00A0}12-12-2024\u{00A0}"]),
    ]));

    expect(Invoice::query()->where('number', $invoiceNumber)->first())
        ->invoice_date->format('Y-m-d')->toBe('2024-12-12');
});

it('imports an Excel numeric issue date', function () {
    $invoiceNumber = 'IMPORT-'.fake()->uuid();

    (new InvoiceImport)->collection(new Collection([
        collect(['Numero fattura *', 'Descrizione', 'Azienda', 'Stato pagamento', 'Data emissione (gg/mm/aaaa) *']),
        collect([$invoiceNumber, null, null, null, 45638]),
    ]));

    expect(Invoice::query()->where('number', $invoiceNumber)->first())
        ->invoice_date->format('Y-m-d')->toBe('2024-12-12');
});

<?php

use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

it('allows an invoice number to be reused in a different year', function () {
    $invoiceNumber = 'TEST-'.fake()->uuid();

    Invoice::create([
        'number' => $invoiceNumber,
        'invoice_date' => '2025-01-15',
    ]);

    Invoice::create([
        'number' => $invoiceNumber,
        'invoice_date' => '2026-01-15',
    ]);

    expect(Invoice::where('number', $invoiceNumber)->count())->toBe(2);
});

it('prevents an invoice number from being created twice in the same year', function () {
    $invoiceNumber = 'TEST-'.fake()->uuid();

    Invoice::create([
        'number' => $invoiceNumber,
        'invoice_date' => '2025-01-15',
    ]);

    expect(fn () => Invoice::create([
        'number' => $invoiceNumber,
        'invoice_date' => '2025-12-31',
    ]))->toThrow(ValidationException::class);
});

it('prevents a number or issue-date update that duplicates an invoice in the same year', function () {
    $invoiceNumber = 'TEST-'.fake()->uuid();

    $invoice = Invoice::create([
        'number' => $invoiceNumber,
        'invoice_date' => '2025-01-15',
    ]);

    $otherInvoice = Invoice::create([
        'number' => 'INV-002',
        'invoice_date' => '2025-01-15',
    ]);

    expect(fn () => $otherInvoice->update(['number' => $invoiceNumber]))->toThrow(ValidationException::class);

    $invoiceInAnotherYear = Invoice::create([
        'number' => $invoiceNumber,
        'invoice_date' => '2026-02-15',
    ]);

    expect(fn () => $invoiceInAnotherYear->update(['invoice_date' => '2025-02-15']))->toThrow(ValidationException::class);
});

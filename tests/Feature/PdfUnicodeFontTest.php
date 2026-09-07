<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

uses(TestCase::class);

it('embeds the Unicode font for report headings and text', function () {
    $output = Pdf::loadHtml('<style>.main-header { font-family: \'DejaVu Sans\'; font-weight: bold; }</style><h1 class="main-header">Report attività eseguite</h1><h2 class="main-header"><b>Azienda</b></h2><h1 class="main-header">Ticket #1 →</h1>')->output();

    expect($output)->toContain('DejaVuSans')
        ->not->toContain('Times');
});

it('uses a consistent size for every ticket heading', function () {
    $template = file_get_contents(resource_path('views/pdf/exportpdf.blade.php'));

    expect($template)->toContain("<h2 class=\"main-header\" style=\"font-family: 'DejaVu Sans'; font-size:1rem; line-height:1rem;\">Ticket")
        ->toContain("<h1 class=\"main-header\" style=\"font-family: 'DejaVu Sans'; font-size:1rem; line-height:1rem;\">Ticket #");
});

it('wraps long webform values within the available PDF space', function () {
    $template = file_get_contents(resource_path('views/pdf/exportpdf.blade.php'));
    $styles = file_get_contents(resource_path('views/components/style.blade.php'));

    expect($template)->toContain('class="ticket-webform-table"')
        ->toContain('class="ticket-webform-hardware-table"')
        ->toContain('style="width: 33.33%;"')
        ->and($styles)->toContain('table-layout: fixed;')
        ->toContain('overflow-wrap: break-word;')
        ->toContain('word-break: break-all;');
});

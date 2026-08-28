<?php

use App\Jobs\GeneratePdfReport;
use App\Models\TicketReportPdfExport;

it('initializes with the supplied report', function () {
    $report = new TicketReportPdfExport;

    $job = new GeneratePdfReport($report, true);

    expect($job->report)->toBe($report)
        ->and($job->isRegeneration)->toBeTrue();
});

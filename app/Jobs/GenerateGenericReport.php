<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;
use App\Models\TicketReportExport;

class GenerateGenericReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $report;

    /**
     * Create a new job instance.
     */
    public function __construct(TicketReportExport $report)
    {
        //
        $this->report = $report;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Excel::store(new GenericExport($this->report), $this->report->file_path, 'gcs');
        $this->report->is_generated = true;
        $this->report->save();
    }
}

<?php

namespace App\Jobs;

use App\Exports\GenericExport;
use App\Http\Controllers\FileUploadController;
use App\Models\TicketReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

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
        $disk = FileUploadController::getStorageDisk();
        Excel::store(new GenericExport($this->report), $this->report->file_path, $disk);
        $this->report->is_generated = true;
        $this->report->save();
    }
}

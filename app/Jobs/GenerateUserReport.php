<?php

namespace App\Jobs;

use App\Exports\UserReportExport;
use App\Models\TicketReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use \Exception as Exception;

class GenerateUserReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 420; // Timeout in seconds
    public $tries = 2; // Number of attempts


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
        //
        try{
            Excel::store(new UserReportExport($this->report), $this->report->file_path, 'gcs');
            $this->report->is_generated = true;
            $this->report->save();
        } catch (Exception $e) {
            if ($this->attempts() >= $this->tries) {
                $this->report->is_failed = true;
                $this->report->error_message = "Attempts: " . $this->attempts() . " - Error: " . $e->getMessage() ?: 'An error occurred while generating the report';
                $this->report->save();
            } else {
                throw $e;
            }
        }

    }
}

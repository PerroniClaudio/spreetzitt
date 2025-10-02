<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\TicketProFormaBill;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class GenerateTicketProFormaBillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 420; // Timeout in seconds

    public $tries = 2; // Number of attempts

    public function __construct(public TicketProFormaBill $bill) {}

    public function handle(): void
    {
        $proFormaBill = $this->bill->fresh();
        $proFormaBill->update([
            'is_generated' => false,
            'is_failed' => false,
            'error_message' => null,
        ]);

        try {
            // Deve recuperare i ticket allo stesso modo del job GeneratePdfReport perchè comparando report e pro-forma devono coincidere i dati.
            $user = User::find($proFormaBill->user_id);
            $company = Company::find($proFormaBill->company_id);
            $queryTo = \Carbon\Carbon::parse($proFormaBill->end_date)->endOfDay()->toDateTimeString();

            if(! $user || ! $company) {
                throw new Exception('User or Company not found');
            }
            if(! $user->is_superadmin) {
                throw new Exception('User is not superadmin');
            }

            $tickets = Ticket::where('company_id', $proFormaBill->company_id)
                ->where('created_at', '<=', $queryTo)
                ->where('description', 'NOT LIKE', 'Ticket importato%')
                ->whereDoesntHave('statusUpdates', function ($query) use ($proFormaBill) {
                    if (! empty($proFormaBill->start_date)) {
                        $query->where('type', 'closing')
                            ->where('created_at', '<=', $proFormaBill->start_date);
                    }
                })
                ->get();

            if (! $tickets->isEmpty()) {
                $tickets->load('ticketType');
            }

            Pdf::setOptions([
                'dpi' => 150,
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);
            $pdf = Pdf::loadView('pdf.pro-forma-fattura', [
                'bill' => $proFormaBill,
                'tickets' => $tickets,
            ]);

            if (! $pdf) {
                throw new Exception('PDF generation failed');
            }

            $disk = \App\Http\Controllers\FileUploadController::getStorageDisk();
            Storage::disk($disk)->put($proFormaBill->file_path, $pdf->output());

            $proFormaBill->update([
                'is_generated' => true,
                'is_failed' => false,
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $shortenedMessage = $e->getMessage();
            if (strlen($shortenedMessage) > 500) {
                $shortenedMessage = substr($shortenedMessage, 0, 500).'...';
            }

            $errorLine = $e->getLine();
            $errorFile = $e->getFile();

            // $proFormaBill->update([
            //     'is_generated' => false,
            //     'is_failed' => true,
            //     'error_message' => $e->getMessage(),
            // ]);
            if ($this->attempts() >= $this->tries) {
                $proFormaBill->is_failed = true;

                $proFormaBill->error_message = 'Error generating the pro forma at ' . now() .
                    ' (line ' . $errorLine . ' in ' . $errorFile . '). ' . $shortenedMessage;

                $proFormaBill->save();
            } else {
                throw $e;
            }
        }
    }
}

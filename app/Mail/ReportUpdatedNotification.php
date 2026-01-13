<?php

namespace App\Mail;

use App\Models\TicketReportPdfExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ReportUpdatedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public TicketReportPdfExport $report;

    /**
     * Create a new message instance.
     */
    public function __construct(TicketReportPdfExport $report)
    {
        $this->report = $report;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Report disponibile o aggiornato',
        );
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->withSymfonyMessage(function ($message) {
            $message->getHeaders()->addTextHeader('X-Ticket-Report-ID', $this->report->id);
        });
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.report.ticket-updated',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->report->is_generated && $this->report->file_path) {
            $disk = \App\Http\Controllers\FileUploadController::getStorageDisk();
            
            if (Storage::disk($disk)->exists($this->report->file_path)) {
                $attachments[] = Attachment::fromStorage($this->report->file_path)
                    ->as($this->report->file_name)
                    ->withMime('application/pdf');
            }
        }

        return $attachments;
    }
}

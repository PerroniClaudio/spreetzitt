<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\TicketTypeCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CloseTicketEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $previewText;
    public $ticketType;
    public $category;

    /**
     * Create a new message instance.
     */
    public function __construct(public Ticket $ticket, public $message, public $link, public $brand_url, public $sendToDataOwner = false)
    {
        //
        $this->ticketType = TicketType::find($this->ticket->type_id);
        $this->category = TicketTypeCategory::find($this->ticketType->ticket_type_category_id);
        $this->previewText =  'Supporto' . ' - ' . $this->message;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Chiusura ' . ($this->category->is_problem ? 'Incident' : 'Request') . ' n° ' . $this->ticket->id . ' - ' . $this->ticketType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.closeticket',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignToUserEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $stages;

    public $previewText;

    /**
     * Create a new message instance.
     */
    public function __construct(public Ticket $ticket, public $company, public $ticketType, public $category, public $link, public $update, public $user)
    {
        // l'array viene creato in modo da poter accedere allo stage direttamente con l'array stages[stage_id]
        $this->stages = \App\Models\TicketStage::all()->mapWithKeys(function ($stage) {
            return [$stage->id => [
                'name' => $stage->name,
                'admin_color' => $stage->admin_color,
                'user_color' => $stage->user_color,
                'is_sla_pause' => $stage->is_sla_pause,
            ]];
        })->toArray();
        $this->previewText = $this->ticket->description;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Assegnazione ticket '.$this->ticket->id.' - '.$this->ticketType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.assigntouser',
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

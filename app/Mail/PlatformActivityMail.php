<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    public $stages;

    /**
     * Create a new message instance.
     */
    public function __construct(public iterable $tickets)
    {
        $this->stages = \App\Models\TicketStage::all()->mapWithKeys(function ($stage) {
            return [$stage->id => [
                'name' => $stage->name,
                'admin_color' => $stage->admin_color,
                'user_color' => $stage->user_color,
                'is_sla_pause' => $stage->is_sla_pause
            ]];
        })->toArray();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Report orario - Attività piattaforma',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.platformactivity',
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

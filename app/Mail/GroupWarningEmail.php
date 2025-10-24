<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GroupWarningEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $stages;

    public $updateTypes;

    public $previewText; // Testo visualizzato nella preview dell'email

    public $envelopSubject; // Oggetto dell'email

    public $category;

    public $company;

    public $ticketType;

    /**
     * Create a new message instance.
     */
    // public function __construct(public Ticket $ticket, public $company, public $ticketType, public $category, public $link, public $update, public $user)
    public function __construct(public $type, public $link = null, public $ticket = null, public $update = null)
    {
        // l'array viene creato in modo da poter accedere allo stage direttamente con stages[stage_id]
        $this->stages = \App\Models\TicketStage::all()->mapWithKeys(function ($stage) {
            return [$stage->id => [
                'name' => $stage->name,
                'admin_color' => $stage->admin_color,
                'user_color' => $stage->user_color,
                'is_sla_pause' => $stage->is_sla_pause,
            ]];
        })->toArray();

        $this->updateTypes = config('app.update_types');

        // $this->previewText = $this->company->name . ' - ' . $this->updateTypes[$this->update->type] . " - " . $this->update->content;
        $this->previewText = '';

        switch ($type) {
            case 'auto-assign':
                if (! $ticket) {
                    throw new \Exception('Per questo tipo serve anche il ticket.');
                }
                if (! $update) {
                    throw new \Exception('Per questo tipo serve anche l\'update.');
                }
                $this->company = $ticket->company;
                $this->category = $ticket->ticketType->category;
                $this->ticketType = $ticket->ticketType;
                $ticketTypeName = $ticket->ticketType->name;
                $requestProblem = $this->category->is_problem ? 'Incident' : 'Request';
                // Questi due possono essere diversi tra loro ma per ora li lascio così
                $this->previewText = 'Warning! '.$requestProblem.' n° '.$ticket->id.' assegnato/a automaticamente - '.$ticketTypeName;
                $this->envelopSubject = 'Warning! '.$requestProblem.' n° '.$ticket->id.' assegnato/a automaticamente - '.$ticketTypeName;
                break;
            default:
                $this->previewText = 'Warning! Non specificato';
                $this->envelopSubject = 'Warning! Non specificato';
                break;
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->envelopSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.groupwarning',
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

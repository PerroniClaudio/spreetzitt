<?php

namespace App\Mail;

use App\Models\Office;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OpenTicketEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $previewText;

    public $form;

    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(public Ticket $ticket, public $company, public $ticketType, public $category, public $link, public $brand_url, public $mailType)
    {
        // Utente che ha aperto il ticket
        $this->user = User::find($this->ticket->user_id);
        $this->previewText = $this->company->name.' - '.($this->user->is_admin ? 'Supporto' : ($this->user->name.' '.$this->user->surname ?? '')).' - '.$this->ticket->description.' - ';

        $firstMessage = $ticket->messages[0]->message;
        $data = json_decode($firstMessage, true);
        unset($data['description']);
        if (isset($data['office']) && $data['office'] != 0) {
            $office = Office::find($data['office']);
            $data['Sede'] = $office
                ? $office->name.' - '.$office->city.', '.$office->address.' '.$office->number
                : $data['office'];
            unset($data['office']);
        }
        if (isset($data['referer_it'])) {
            if ($data['referer_it'] != 0) {
                $refererIT = User::find($data['referer_it']);
                $data[\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'Referente IT')] = $refererIT
                    ? $refererIT->name.' '.$refererIT->surname ?? ''
                    : $data['referer_it'];
            }
            unset($data['referer_it']);
        }
        if (isset($data['referer'])) {
            if ($data['referer'] != 0) {
                $referer = User::find($data['referer']);
                $data['Utente interessato'] = $referer
                    ? $referer->name.' '.$referer->surname ?? ''
                    : $data['referer'];
            }
            unset($data['referer']);
        }

        $formText = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $formText .= htmlspecialchars($key.': '.$value, ENT_QUOTES, 'UTF-8', false).'<br>';
        }
        $this->form = $formText;
        // $this->form = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Apertura '.($this->category->is_problem ? 'Incident' : 'Request').' n° '.$this->ticket->id.' - '.$this->ticketType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.openticket',
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

<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Office;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OpenMassiveTicketEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $previewText;
    public $form;
    public $user;
    public $description;

    /**
     * Create a new message instance.
     * brand_url, previewText, category, mailType, company, user, ticketType, description, form, link
     */
    public function __construct(public $ticketsInfo, public $company, public $ticketType, public $category, public $brand_url, public $mailType)
    {   

        // Controllare se funziona
        if($mailType == "admin") {
          foreach ($this->ticketsInfo as $index => $ticketInfo) {
            $this->ticketsInfo[$index]['link'] = env('FRONTEND_URL') . '/support/admin/ticket/' . $ticketInfo['id'];
          }
        } else {
          foreach ($this->ticketsInfo as $index => $ticketInfo) {
            $this->ticketsInfo[$index]['link'] = env('FRONTEND_URL') . '/support/user/ticket/' . $ticketInfo['id'];
          }
        }

        $sampleTicket = Ticket::find($this->ticketsInfo[0]['id']);
        $this->description = $sampleTicket->description;

        // Utente che ha aperto il ticket
        $this->user = User::find($sampleTicket->user_id);
        $this->previewText = $company->name . ' - ' . $this->description . ' - ';
        

        $firstMessage = $sampleTicket->messages[0]->message;
        $data = json_decode($firstMessage, true);
        unset($data['description']);
        unset($data['Identificativo']);
        if(isset($data['office'])){
            $office = Office::find($data['office']);
            $data["Sede"] = $office 
                ? $office->name . " - " . $office->city . ", " . $office->address . " " . $office->number
                : $data['office'];
            unset($data['office']);
        }
        if(isset($data['referer_it'])){
            $refererIT = User::find($data['referer_it']);
            $data[\App\Models\TenantTerm::getCurrentTenantTerm('referente_it', 'Referente IT')] = $refererIT
                ? $refererIT->name . ' ' . $refererIT->surname ?? ''
                : $data['referer_it'];
            unset($data['referer_it']);
        }
        if(isset($data['referer'])){
            $referer = User::find($data['referer']);
            $data["Utente interessato"] = $referer
                ? $referer->name . ' ' . $referer->surname ?? ''
                : $data['referer'];
            unset($data['referer']);
        }

        $formText = '';
        foreach($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $formText .= htmlspecialchars($key . ': ' . $value, ENT_QUOTES, 'UTF-8', false) . '<br>';
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
            subject: 'Apertura massiva ' . ($this->category->is_problem ? 'Incident' : 'Request') . ' - ' . $this->ticketType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.openmassiveticket',
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

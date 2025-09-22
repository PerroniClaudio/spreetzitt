<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UpdateEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $stages;
    // public $stages = [
    //   "Nuovo", 
    //   "Assegnato", 
    //   "In corso", 
    //   "In attesa", 
    //   "Risolto", 
    //   "Chiuso",
    //   "Attesa feedback cliente"
    // ];

    public $updateTypes;
    // public $updateTypes = config('app.update_types');
    // public $updateTypes = [
    //   "assign" => "Assegnazione", 
    //   "status" => "Stato", 
    //   "sla" => "SLA", 
    //   "closing" => "Chiusura", 
    //   "note" => "Nota", 
    //   "blame" => "Colpa", 
    //   "group_assign" => "Assegnazione gruppo"
    // ];

    public $previewText; // Testo visualizzato nella preview dell'email
    
    /**
     * Create a new message instance.
     */
    public function __construct(public Ticket $ticket, public $company, public $ticketType, public $category, public $link, public $update, public $user, public $isAutomatic = false)
    {
        //
        $this->updateTypes = config('app.update_types');
        $this->stages = \App\Models\TicketStage::all()->mapWithKeys(function ($stage) {
            return [$stage->id => [
                'name' => $stage->name,
                'admin_color' => $stage->admin_color,
                'user_color' => $stage->user_color,
                'is_sla_pause' => $stage->is_sla_pause
            ]];
        })->toArray();

        $this->previewText = $this->company->name . ' - ' . $this->updateTypes[$this->update->type] . " - " . $this->update->content;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update ' . ($this->category->is_problem ? 'Incident' : 'Request') . ' n° ' . $this->ticket->id . ' - ' . $this->ticketType->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.statusupdate',
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

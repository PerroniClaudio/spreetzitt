<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class HourlyCostExpirationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Company $company;
    public Collection $expiringTicketTypes;
    public string $frontendUrl;
    public int $nullExpirationZeroPrice;
    public int $nullExpirationWithPrice;

    /**
     * Create a new message instance.
     */
    public function __construct(
        Company $company, 
        Collection $expiringTicketTypes, 
        int $nullExpirationZeroPrice = 0, 
        int $nullExpirationWithPrice = 0
    ) {
        $this->company = $company;
        $this->expiringTicketTypes = $expiringTicketTypes;
        $this->nullExpirationZeroPrice = $nullExpirationZeroPrice;
        $this->nullExpirationWithPrice = $nullExpirationWithPrice;
        $this->frontendUrl = config('app.frontend_url');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Scadenza Costi Orari - {$this->company->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.hourly-cost-expiration',
            with: [
                'company' => $this->company,
                'expiringTicketTypes' => $this->expiringTicketTypes,
                'frontendUrl' => $this->frontendUrl,
                'nullExpirationZeroPrice' => $this->nullExpirationZeroPrice,
                'nullExpirationWithPrice' => $this->nullExpirationWithPrice,
            ],
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

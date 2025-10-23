<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\TicketType;
use App\Mail\HourlyCostExpirationNotification;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class TestHourlyCostExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:hourly-cost-expiration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test hourly cost expiration logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting hourly cost expiration check...');

        try {
            // Date for 2 weeks from now
            $twoWeeksFromNow = Carbon::now()->addWeeks(2);
            $oneMonthFromNow = Carbon::now()->addMonth();

            $this->info("Checking for hourly costs expiring between now and {$twoWeeksFromNow->format('Y-m-d H:i:s')}");

            // Find companies with ticket types that have hourly cost expiring
            $companies = Company::whereHas('ticketTypes', function ($query) use ($twoWeeksFromNow) {
                $query->whereNotNull('hourly_cost')
                      ->where('hourly_cost', '>', 0)
                      ->whereNotNull('hourly_cost_expires_at')
                      ->where('hourly_cost_expires_at', '<=', $twoWeeksFromNow);
            })->get();

            $this->info("Found {$companies->count()} companies with expiring hourly costs");

            foreach ($companies as $company) {
                $this->info("Processing company: {$company->name} (ID: {$company->id})");

                // Get all ticket types with hourly cost expiring in the next month for this company
                $expiringTicketTypes = $company->ticketTypes()
                    ->whereNotNull('hourly_cost')
                    ->where('hourly_cost', '>', 0)
                    ->whereNotNull('hourly_cost_expires_at')
                    ->where('hourly_cost_expires_at', '<=', $oneMonthFromNow)
                    ->get();

                // Conta i tipi di ticket con data di scadenza null per questa azienda
                // Tipi di ticket senza data di scadenza con prezzo = 0
                $nullExpirationZeroPrice = $company->ticketTypes()
                    ->whereNull('hourly_cost_expires_at')
                    ->where('is_deleted', false)
                    ->where(function($query) {
                        $query->whereNull('hourly_cost')->orWhere('hourly_cost', 0);
                    })
                    ->count();

                // Tipi di ticket senza data di scadenza con prezzo > 0
                $nullExpirationWithPrice = $company->ticketTypes()
                    ->whereNull('hourly_cost_expires_at')
                    ->where('is_deleted', false)
                    ->whereNotNull('hourly_cost')
                    ->where('hourly_cost', '>', 0)
                    ->count();

                $this->info("Found {$expiringTicketTypes->count()} expiring ticket types for this company");
                $this->info("Ticket types without expiration date: Zero price = {$nullExpirationZeroPrice}, With price = {$nullExpirationWithPrice}");

                foreach ($expiringTicketTypes as $ticketType) {
                    $this->info("- {$ticketType->name}: €{$ticketType->hourly_cost} expires on {$ticketType->hourly_cost_expires_at}");
                }

                // Check mail configuration
                $toAddress = config('mail.to_address');
                $this->info("Mail to address: " . ($toAddress ?: 'NOT SET'));

                if (!$toAddress) {
                    $this->error('MAIL_TO_ADDRESS not configured');
                    continue;
                }

                // Send notification
                $this->info("Sending notification email to: {$toAddress}");
                
                Mail::to($toAddress)->send(new HourlyCostExpirationNotification(
                    $company, 
                    $expiringTicketTypes, 
                    $nullExpirationZeroPrice, 
                    $nullExpirationWithPrice
                ));
                
                $this->info("Email sent successfully for company: {$company->name}");
            }

            $this->info('Hourly cost expiration check completed successfully');

        } catch (\Exception $e) {
            $this->error('Error during hourly cost expiration check: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}

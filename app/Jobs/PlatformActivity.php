<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class PlatformActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $tickets = Ticket::where("status", "!=", 5)->with("company", "ticketType")->orderBy("created_at", "desc")->get();
        $supportMail = env('MAIL_TO_ADDRESS');
        Mail::to($supportMail)->send(new \App\Mail\PlatformActivityMail($tickets));

        // Hardcodate
        Mail::to("p.massafra@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));
        Mail::to("a.fumagalli@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));
        Mail::to("e.salsano@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));
        Mail::to("c.perroni@ifortech.com")->send(new \App\Mail\PlatformActivityMail($tickets));
    }
}

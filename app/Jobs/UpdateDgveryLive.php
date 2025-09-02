<?php

namespace App\Jobs;

use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateDgveryLive implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private $payload;

    /**
     * Create a new job instance.
     */
    public function __construct($payload) {
        //
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void {
        //

        $payload = $this->payload;

        $message = TicketMessage::where("message", "\"identificativo_livesp\":\"{$payload['live_id']}\"")->first();

        $newMessage = TicketMessage::create([
            'ticket_id' => $message->ticket_id,
            'user_id' => 12,
            'message' => $payload['message'],
        ]);
    }
}

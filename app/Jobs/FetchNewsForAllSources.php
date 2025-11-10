<?php

namespace App\Jobs;

use App\Models\NewsSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FetchNewsForAllSources implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var Collection<int, NewsSource> $sources */
        $sources = NewsSource::all();

        if ($sources->isEmpty()) {
            Log::info('NEWS - Nessuna NewsSource trovata per FetchNewsForAllSources');

            return;
        }

        foreach ($sources as $source) {
            FetchNewsForSource::dispatch($source);
        }
    }
}

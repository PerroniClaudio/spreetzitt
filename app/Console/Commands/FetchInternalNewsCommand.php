<?php

namespace App\Console\Commands;

use App\Jobs\FetchNewsForSource;
use App\Models\NewsSource;
use Illuminate\Console\Command;

class FetchInternalNewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:fetch-internal';

    /**
     * The console command description.
     *
     * @var string|null
     */
    protected $description = 'Esegue FetchNewsForSource solo per le NewsSource di tipo internal_blog';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $internalType = NewsSource::TYPES[0] ?? null;

        if (! $internalType) {
            $this->error('Tipo internal_blog non definito.');

            return self::FAILURE;
        }

        $sources = NewsSource::where('type', $internalType)->get();

        if ($sources->isEmpty()) {
            $this->warn('Nessuna NewsSource di tipo internal_blog trovata.');

            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($sources as $source) {
            FetchNewsForSource::dispatch($source);
            $dispatched++;
            $this->line(sprintf('Job dispatchato per la sorgente #%d (%s)', $source->id, $source->display_name ?? $source->slug));
        }

        $this->info(sprintf('Dispatchati %d job FetchNewsForSource per internal_blog.', $dispatched));

        return self::SUCCESS;
    }
}


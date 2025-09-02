<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NewsSource;

class AddNewsSource extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-news-source';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggiunge una nuova NewsSource in modo interattivo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $displayName = $this->ask('Nome da mostrare (display_name)');
        $slug = $this->ask('Slug (unico, per URL)');
        $types = \App\Models\NewsSource::TYPES;
        $typeIndex = $this->choice(
            'Tipo sorgente',
            [
                '1. internal_blog (Blog interno aziendale)',
                '2. vendor_blog (Blog di un vendor esterno)',
                '3. vendor_social (Social network di un vendor)',
                '4. rss (Feed RSS/Atom generico)',
                '5. manual (Inserimento manuale)',
                '6. other (Altro)',
            ],
            0
        );
        $type = $types[(int) $typeIndex[0] - 1];
        $url = $this->ask('URL sorgente (opzionale)');
        $description = $this->ask('Descrizione (opzionale)');

        if (NewsSource::where('slug', $slug)->exists()) {
            $this->error('Slug già esistente. Operazione annullata.');
            return 1;
        }

        $newsSource = NewsSource::create([
            'display_name' => $displayName,
            'slug' => $slug,
            'type' => $type,
            'url' => $url,
            'description' => $description,
        ]);

        $this->info('Sorgente creata con successo!');
        return 0;
    }
}

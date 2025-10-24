<?php

namespace App\Jobs;

use App\Models\NewsSource;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchNewsForSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected NewsSource $source;

    /**
     * Create a new job instance.
     */
    public function __construct(NewsSource $source)
    {
        $this->source = $source;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        switch ($this->source->type) {
            case NewsSource::TYPES[0]:
                $this->fetchInternalBlog();
                break;
            case NewsSource::TYPES[1]:
                $this->fetchVendorBlog();
                break;
            case NewsSource::TYPES[2]:
                $this->fetchVendorSocial();
                break;
            case NewsSource::TYPES[3]:
                $this->fetchRss();
                break;
            case NewsSource::TYPES[4]:
                $this->fetchManual();
                break;
            case NewsSource::TYPES[5]:
                $this->fetchOther();
                break;
            default:
                // Log o throw exception
                break;
        }
    }

    /**
     * Fetch news da blog interno aziendale
     */
    private function fetchInternalBlog(): void
    {
        // TODO: implementare fetch da blog interno
    }

    /**
     * Fetch news da blog di un vendor esterno
     */
    private function fetchVendorBlog(): void
    {
        $url = $this->source->url;
        $html = \App\Http\Controllers\NewsController::getRenderedHtmlWithFirecrawl($url);
        $htmlRilevante = \App\Http\Controllers\NewsController::extractRelevantHtml($html);

        $vertex = new \App\Http\Controllers\VertexAiController;
        $response = $vertex->extractNewsFromHtml($htmlRilevante);

        $newsArray = json_decode($response['result'] ?? '', true);
        if (! is_array($newsArray)) {
            Log::error('NEWS - Vertex AI non ha restituito un array valido', ['response' => $response, 'source_id' => $this->source->id]);

            return;
        }

        $created = 0;
        foreach ($newsArray as $newsData) {

            // Validate published_at and fallback to now if invalid or missing
            if (isset($newsData['published_at']) && ! empty($newsData['published_at'])) {
                try {
                    $publishedAt = Carbon::parse($newsData['published_at']);
                } catch (\Exception $e) {
                    $publishedAt = Carbon::now();
                }
            } else {
                $publishedAt = Carbon::now();
            }

            $news = \App\Models\News::updateOrCreate([
                'news_source_id' => $this->source->id,
                'title' => $newsData['title'] ?? '',
            ], [
                'url' => $newsData['url'] ?? '',
                'description' => $newsData['description'] ?? '',
                'published_at' => $publishedAt,
            ]);

            if ($news->wasRecentlyCreated) {
                $created++;
            }
        }

        Log::info('NEWS - Fetch news completed for source', ['source_id' => $this->source->id, 'added' => $created, 'total_found' => count($newsArray)]);
    }

    /**
     * Fetch news da social network di un vendor
     */
    private function fetchVendorSocial(): void
    {
        // TODO: implementare fetch da social vendor
    }

    /**
     * Fetch news da feed RSS/Atom generico
     */
    private function fetchRss(): void
    {
        // TODO: implementare fetch da RSS/Atom
    }

    /**
     * Fetch news da inserimento manuale
     */
    private function fetchManual(): void
    {
        // TODO: implementare fetch/manual insert
    }

    /**
     * Fetch news da sorgente "other"
     */
    private function fetchOther(): void
    {
        // TODO: implementare fetch custom
    }
}

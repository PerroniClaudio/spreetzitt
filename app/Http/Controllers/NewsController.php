<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsController extends Controller
{
    /**
     * Estrae l'HTML di una pagina tramite file_get_contents (solo HTML statico).
     */
    public static function getRenderedHtml(string $url): string
    {
        return @file_get_contents($url);
    }

    /**
     * Estrae solo il contenuto rilevante (main, article, section) da una pagina HTML.
     * Riduce la lunghezza del prompt per Vertex AI.
     */
    public static function extractRelevantHtml(string $html): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//main | //article | //section');
        $content = '';
        foreach ($nodes as $node) {
            $content .= $dom->saveHTML($node);
        }
        // Fallback: se non trova nulla, restituisce solo il body
        if (empty($content)) {
            $body = $xpath->query('//body');
            if ($body->length > 0) {
                $content = $dom->saveHTML($body->item(0));
            }
        }
        // Rimuove script, style e commenti
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        // Rimuove tabulazioni e a capo
        $content = str_replace(["\t", "\n", "\r"], '', $content);

        return $content;
    }

    /**
     * Restituisce tutte le news per una determinata source tramite slug.
     *
     * @param  string  $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function bySource($slug)
    {
        $source = \App\Models\NewsSource::where('slug', $slug)->first();
        if (! $source) {
            return response()->json(['message' => 'NewsSource not found'], 404);
        }
        $news = $source->news()->orderByDesc('published_at')->get();

        return response()->json($news);
    }

    public function testScraper()
    {
        $url = 'https://www.ninjaone.com/it/blog/';
        $html = self::getRenderedHtml($url);
        $htmlRilevante = self::extractRelevantHtml($html);

        Log::info('Relevant HTML extracted:', ['html' => $htmlRilevante]);

        $controller = new VertexAiController;
        $response = $controller->extractNewsFromHtml($htmlRilevante);

        $newsArray = json_decode($response['result'], true);

        return response()->json($newsArray);
    }

    /**
     * Estrae l'HTML di una pagina utilizzando Firecrawl (supporta JavaScript e SPA).
     */
    public static function getRenderedHtmlWithFirecrawl(string $url): string
    {
        $apiKey = config('firecrawl.api_key');

        if (! $apiKey) {
            Log::error('Firecrawl API key not configured');
            throw new \Exception('Firecrawl API key not configured');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.firecrawl.dev/v2/scrape', [
                'url' => $url,
                'onlyMainContent' => false,
                'maxAge' => 172800000,
                'parsers' => [],
                'formats' => ['html'],
            ]);

            if ($response->failed()) {
                throw new \Exception('Firecrawl request failed: '.$response->status());
            }

            $data = $response->json();

            if ($data['success'] && isset($data['data']['html'])) {
                return $data['data']['html'];
            }

            throw new \Exception('Failed to scrape with Firecrawl: '.($data['error'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('Firecrawl scraping error', ['url' => $url, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Testa lo scraper con Firecrawl per pagine con JavaScript/SPA.
     */
    public function testScraperWithFirecrawl()
    {
        $url = 'https://www.ninjaone.com/it/blog/';

        try {
            $html = self::getRenderedHtmlWithFirecrawl($url);

            // Pulisce aggressivamente
            $htmlPulito = self::extractRelevantHtml($html);

            Log::info('HTML size - Before: '.strlen($html).' bytes, After: '.strlen($htmlPulito).' bytes');

            $controller = new VertexAiController;
            $response = $controller->extractNewsFromHtml($htmlPulito);

            $newsArray = json_decode($response['result'], true);

            return response()->json($newsArray);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error scraping with Firecrawl',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Metodo smart con fallback: prova Firecrawl, ritorna a file_get_contents se fallisce.
     */
    public static function getRenderedHtmlSmart(string $url): string
    {
        try {
            return self::getRenderedHtmlWithFirecrawl($url);
        } catch (\Exception $e) {
            Log::warning('Firecrawl failed, falling back to file_get_contents', ['url' => $url]);

            return self::getRenderedHtml($url);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(News $news)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(News $news)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, News $news)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(News $news)
    {
        //
    }
}

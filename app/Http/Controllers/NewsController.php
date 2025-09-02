<?php
namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NewsController extends Controller
{
    /**
     * Estrae l'HTML di una pagina tramite file_get_contents (solo HTML statico).
     *
     * @param string $url
     * @return string
     */
    public static function getRenderedHtml(string $url): string
    {
        return @file_get_contents($url);
    }
    
    /**
     * Estrae solo il contenuto rilevante (main, article, section) da una pagina HTML.
     * Riduce la lunghezza del prompt per Vertex AI.
     *
     * @param string $html
     * @return string
     */
    public static function extractRelevantHtml(string $html): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
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
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function bySource($slug)
    {
        $source = \App\Models\NewsSource::where('slug', $slug)->first();
        if (!$source) {
            return response()->json(['message' => 'NewsSource not found'], 404);
        }
        $news = $source->news()->orderByDesc('published_at')->get();
        return response()->json($news);
    }

    public function testScraper()
    {
        $url = 'https://wutheringwaves.kurogames.com/en/main/news';
        $html = self::getRenderedHtml($url);
        $htmlRilevante = self::extractRelevantHtml($html);

        Log::info('Relevant HTML extracted:', ['html' => $htmlRilevante]);

        $controller = new VertexAiController();
        $response = $controller->extractNewsFromHtml($htmlRilevante);

        $newsArray = json_decode($response['result'], true);
        return response()->json($newsArray);
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

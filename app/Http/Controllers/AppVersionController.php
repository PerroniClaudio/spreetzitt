<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AppVersionController extends Controller
{
    /**
     * Get the current app version.
     */
    public function getCurrentVersion(Request $request)
    {
        $branch = 'main'; // Puoi cambiare il branch se necessario
        // Recuperiamo i parametri inviati dal frontend
        $frontDate = $request->query('front_date');
        $frontCommits = (int) $request->query('front_commits', 0);

        // Usiamo una cache di 2 minuti sul backend indicizzata per la data del front per non saturare l'API di GitHub
        $cacheKey = "global_version_{$frontDate}_{$frontCommits}";

        return Cache::remember($cacheKey, 120, function () use ($frontDate, $frontCommits, $branch) {
            $token = env('GITHUB_TOKEN');
            $repoBackend = env('GITHUB_ACCOUNT') . '/' . env('GITHUB_REPO');

            // 1. Recupera l'ultimo commit del Backend tramite l'API di GitHub
            $resBack = Http::withToken($token)
                ->withHeaders(['User-Agent' => 'Laravel-Cloud-App'])
                ->get("https://api.github.com/repos/{$repoBackend}/commits", [
                'sha' => $branch,
                'per_page' => 1
            ]);

            $resBackJson = $resBack->json();
            
            $dateBackStr = $resBackJson[0]['commit']['committer']['date'] ?? null;

            if (!$dateBackStr) {
                // Fallback se GitHub non risponde
                return response()->json([
                    'error' => 'Unable to fetch backend commit date from GitHub.'
                ], 500);
            }

            $lastBackCommitDate = Carbon::parse($dateBackStr);
            $backDateFormatted = $lastBackCommitDate->format('Y.md');

            // 2. Conta i commit del Backend in quella specifica data
            $inizioGiorno = $lastBackCommitDate->copy()->startOfDay()->toIso8601String();
            $fineGiorno = $lastBackCommitDate->copy()->endOfDay()->toIso8601String();

            $resCount = Http::withToken($token)
                ->withHeaders(['User-Agent' => 'Laravel-Cloud-App']) // Aggiungi un User-Agent per evitare problemi con GitHub
                ->get("https://api.github.com/repos/{$repoBackend}/commits", [
                'sha' => $branch,
                'since' => $inizioGiorno,
                'until' => $fineGiorno,
                'per_page' => 100
            ]);
            $backCommits = is_array($resCount->json()) ? count($resCount->json()) : 1;

            // 3. LOGICA DI ARBITRATO UNIFICATA
            $finalVersion = "";

            if ($frontDate === $backDateFormatted) {
                // Se le date combaciano, somma i commit
                $totalCommits = $frontCommits + $backCommits;
                $finalVersion = "{$frontDate}.{$totalCommits}";
            } else {
                // Se le date sono diverse, converti in numeri puri per capire qual è la più recente
                $numFront = (int) str_replace('.', '', $frontDate);
                $numBack = (int) str_replace('.', '', $backDateFormatted);

                if ($numFront > $numBack) {
                    $finalVersion = "{$frontDate}.{$frontCommits}";
                } else {
                    $finalVersion = "{$backDateFormatted}.{$backCommits}";
                }
            }

            return response()->json([
                'version' => $finalVersion,
                'backend_date' => $backDateFormatted,
                'backend_commits' => $backCommits,
                'frontend_date' => $frontDate,
                'frontend_commits' => $frontCommits
            ]);
        });
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
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

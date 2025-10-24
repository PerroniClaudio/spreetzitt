<?php

// Semplice script per verificare lo stato del queue worker

// Verifica se il processo queue worker è in esecuzione
function isQueueWorkerRunning()
{
    exec("ps aux | grep 'queue:work' | grep -v grep", $output);

    return ! empty($output);
}

// Verifica se ci sono job in coda
function getQueueStats()
{
    // Questo è un placeholder - in produzione dovresti connetterti al database
    // e contare i job nella tabella jobs
    return [
        'pending' => 0,  // Placeholder
        'processing' => 0,  // Placeholder
        'failed' => 0,  // Placeholder
    ];
}

// Prepara la risposta
$isRunning = isQueueWorkerRunning();
$stats = getQueueStats();

$response = [
    'status' => $isRunning ? 'healthy' : 'degraded',
    'queue_worker' => $isRunning ? 'running' : 'stopped',
    'queue_stats' => $stats,
    'timestamp' => time(),
];

// Invia la risposta
header('Content-Type: application/json');
echo json_encode($response);

<?php
require_once 'vendor/autoload.php';

$redis = new Predis\Client();

while (true) {
    $job = $redis->lpop('job_queue');

    if ($job) {
        processJob($job);
    } else {
        sleep(1); // Evita il sovraccarico
    }
}

function processJob($job) {
    // Usa cURL per chiamare l'endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $job);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);

    // Qui puoi anche aggiungere logica per elaborare la risposta, se necessario
    echo "Lavoro completato: $output\n";
}

<?php
require_once 'vendor/autoload.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$redis = new Predis\Client();

$minQueueSize = 10;
$currentQueueSize = $redis->llen('job_queue');
echo "Dimensione corrente della coda: $currentQueueSize\n";

if ($currentQueueSize < $minQueueSize) {
    echo "Aggiunta di nuovi lavori alla coda...\n";
    $endpoints = [
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/sync?a=1",
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/sync?a=2",
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/sync?a=3",
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/sync?a=4",
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/sync?a=5",
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/sync?a=6",
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/sync?a=7",
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/sync?a=8",
        "curl -u parser:5Uvv^LT@JKUQ6paFo8k9 https://uboot.next-labs.io/parser/clean",
        // ... altri endpoint ...
    ];

    foreach ($endpoints as $endpoint) {
        $redis->rpush('job_queue', $endpoint);
        echo "Aggiunto lavoro: $endpoint\n";
    }
} else {
    echo "La coda ha già abbastanza lavori.\n";
}
?>
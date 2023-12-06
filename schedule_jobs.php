<?php
require_once 'vendor/autoload.php';

$redis = new Predis\Client();

// Definisci i tuoi endpoint e parametri
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
    //"https://uboot.next-labs.io/parser/sync?a=1",
    //"https://uboot.next-labs.io/parser/clean",
    // Aggiungi gli altri endpoint come necessario
];

foreach ($endpoints as $endpoint) {
    // Aggiungi ogni lavoro nella coda
    $redis->rpush('job_queue', $endpoint);
}

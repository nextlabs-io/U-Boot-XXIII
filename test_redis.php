<?php
require_once 'vendor/autoload.php';
$redis = new Predis\Client();

// Test scrittura e lettura
$redis->set('test_key', 'test_value');
echo "Scritto in Redis: " . $redis->get('test_key');
?>

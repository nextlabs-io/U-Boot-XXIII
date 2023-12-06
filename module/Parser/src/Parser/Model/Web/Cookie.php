<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.09.2017
 * Time: 17:42
 */

namespace Parser\Model\Web;

/*
 * class to define curl cookies depending on the proxy, ip etc.
 * Generates cookie on a hash combined of the ip, process and limits usage by count.
 */

use Laminas\Http\Cookies;
use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Plugin\ExceptionHandler;

class Cookie extends Cookies
{
    private $cache;

public function __construct($config = [])
{
    $cacheLifeTime = $config['cacheLifeTime'] ?? 200;
    $redis = new Redis([
        'server' => [
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
        'ttl' => $cacheLifeTime,
    ]);
    $plugin = new ExceptionHandler();
    $plugin->getOptions()->setThrowExceptions(false);
    $redis->addPlugin($plugin);
    $this->cache = $redis;
}

    public function getCookieFromCache($key)
    {
        $cookie = $this->cache->getItem($key, $success);
        $cookie = $success ? unserialize($cookie) : [];
        return $cookie;
    }

    public function setCookieCache($cookie, $key)
    {
        $this->cache->setItem($key, serialize($cookie));
        return $this;
    }
}
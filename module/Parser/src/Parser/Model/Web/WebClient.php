<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.03.2019
 * Time: 13:58
 */

namespace Parser\Model\Web;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use Parser\Model\Helper\Helper;
use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Plugin\ExceptionHandler;

/**
 * Class WebClient
 * @package Parser\Model\Web
 * basic class to obtain web pages data
 * utilizes proxy, useragent, cookies
 * should it be here to define if the extraction was successful?
 */
class WebClient extends \GuzzleHttp\Client
{
    /** @var String $lastCallError */
    public $lastCallError;
    protected $history;
    /** @var Proxy $proxy */
    private $proxy;
    /** @var UserAgent $userAgent */
    private $userAgent;
    /** @var Redis $cache */
    private $cache;
    /** @var String $host */
    private $host;
    /** @var String $url */
    private $url;

public function __construct(array $config = [])
{
    // initialize cache
    $cacheLifeTime = $config['cacheLifeTime'] ?? 100;

    $redisOptions = new RedisOptions([
        'server' => [
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
        'ttl' => $cacheLifeTime,
    ]);

    $cache = new Redis($redisOptions);

    $plugin = new ExceptionHandler();
    $plugin->getOptions()->setThrowExceptions(false);
    $cache->addPlugin($plugin);
    $this->cache = $cache;

    $config[RequestOptions::ALLOW_REDIRECTS] = $config[RequestOptions::ALLOW_REDIRECTS] ?? [
        'max' => 5,
        'strict' => false,
        'referer' => true,
        'protocols' => ['http', 'https'],
        'track_redirects' => true
    ];

    parent::__construct($config);
}

    /**
     * @param string $url
     * @param array $config
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    public function getPage(string $url, array $config = []): ?\Psr\Http\Message\ResponseInterface
    {
        /**
         * sample config
         *
         * $config = [
         * 'headers' => ['User-Agent' => 'someAgent'],
         * 'cookieCacheKey' => '',
         * 'method' => 'GET'
         * ]
         */
        $this->lastCallError = null;
        $this->url = $url;
        $this->host = $this->getHost($url);
        // main request options, make sure User-Agent is in the $config['headers']
        $options = [
            'http_errors' => false,
            'headers' => $this->generateHeader($config['headers'] ?? []),
            'timeout' => $config['timeout'] ?? 40,
            'verify' => false,
        ];
        if ($config['debugMode'] ?? false) {
            pr('request options and headers');
            pr($options);
        }
        $curlOptions = [];

        $cookieCacheKey = '';
        if (isset($config['cookieCacheKey']) && ($cookieCacheKey = $config['cookieCacheKey'])) {
            // we have a cookie cache key, need to get cookie from cache
            // TODO need to add here cookie check and modify if needed.
            $cookie = $this->cache->getItem($config['cookieCacheKey'], $success);
            $cookie = $success ? unserialize($cookie) : [];
            // adding a cookie
//            $cookie['sp-cdn'] = '"L5Z9:CA"';
            $options['cookies'] = CookieJar::fromArray($cookie, $this->host);
        }
        if (is_object($this->proxy)) {
            $proxyUrl = $this->proxy->getProperty('ip');
            $proxyPort = $this->proxy->getProperty('port');
            $proxyTorPort = $this->proxy->getProperty('tor_auth_port');
            $proxyTorAuth = $this->proxy->getProperty('tor_auth');
            $proxyUserName = $this->proxy->getProperty('user_name');
            $proxyUserPass = $this->proxy->getProperty('user_pass');

            if ($proxyUrl) {
                $curlOptions = [
                    CURLOPT_PROXY => $proxyUrl,
                    CURLOPT_PROXYPORT => $proxyPort,
                    CURLOPT_FORBID_REUSE => true,
                ];
                if ($proxyTorAuth) {
                    if ($this->getLastTorRequestResult($this->proxy->getProperty('proxy_id')) < -10) {

                        // you may specify the result later after reviewing the result by $this->setLastTorRequestResult(bool)
                        $this->resetTorProxy($proxyUrl, $proxyTorPort, $proxyTorAuth);
                        sleep(10);
                    }
                    $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
                } else if ($proxyUserName && $proxyUserPass) {
                    $curlOptions[CURLOPT_PROXYUSERPWD] = $proxyUserName . ':' . $proxyUserPass;
                }
            }
            $options['curl'] = $curlOptions;
        }
        try {
            $method = $config['method'] ?? 'GET';
            if (in_array($method, ['GET', 'POST'])) {
                if ($method === 'GET') {
                    $response = $this->get($url, $options);

                } else {
                    $options['form_params'] = $config['postFields'] ?? [];
                    $response = $this->post($url, $options);
                }
            } else {
                // no method allowed
                $this->lastCallError = $method . ' not allowed';
                return null;
            }
            if (isset($options['cookies']) && $response->getStatusCode() === 200) {
                // saving cookies;
                $cookie = [];
                foreach ($options['cookies']->toArray() as $item) {
                    $cookie[$item['Name']] = $item['Value'];
                }
                $this->cache->setItem($cookieCacheKey, serialize($cookie));
            }
//            if ($config['debugMode'] ?? false) {
//                pr('response headers');
//                pr($response->getHeaders());
//            }
            return $response;

        } catch (\GuzzleHttp\Exception\ClientException  $e) {
            $this->lastCallError = $e->getMessage();
            return $e->getResponse();
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->lastCallError = $e->getMessage();
            return $e->getResponse();
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $this->lastCallError = $e->getMessage();
            return $e->getResponse();
        } catch (\GuzzleHttp\Exception\TooManyRedirectsException $e) {
            $this->lastCallError = $e->getMessage();
            return $e->getResponse();
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            $this->lastCallError = $e->getMessage();
            return null;
        } catch (\GuzzleHttp\Exception\SeekException $e) {
            $this->lastCallError = $e->getMessage();
            return null;
        }

    }

    public function getHost($url)
    {
        $urlChunks = parse_url($url);
        $host = '';
        if (count($urlChunks)) {
            $host = $urlChunks['host'];
        }
        return $host;
    }

    public function generateHeader($headers)
    {
        $defaultHeader = BrowserHeader::getMozillaHeader();
        if ($headers) {
            // merging what we have from config, but not changing order of the fields.
            $defaultHeader = array_merge($defaultHeader, $headers);
        }
        $defaultHeader['Host'] = $defaultHeader['Host'] ?: $this->host;

        // do not use chrome like headers for now.
//        if (isset($headers['User-Agent']) && stripos($headers['User-Agent'], 'chrome') !== false) {
//            $defaultHeader = BrowserHeader::getChromeHeader();
//            $headers = array_change_key_case($headers);
//            $defaultHeader = array_merge($defaultHeader, $headers);
//            if (isset($defaultHeader['host']) && $this->host) {
//                $defaultHeader['host'] = $this->host;
//            }
//        } else {
//            $defaultHeader = BrowserHeader::getMozillaHeader();
//            if ($headers) {
//                // merging what we have from config, but not changing order of the fields.
//                $defaultHeader = array_merge($defaultHeader, $headers);
//            }
//            $defaultHeader['Host'] = $defaultHeader['Host'] ?: $this->host;
//        }
        return $defaultHeader;
    }

    /**
     * @param $proxyId
     * @return integer
     */
    public function getLastTorRequestResult($proxyId)
    {
        $cacheKey = 'torLastRequestResult-' . $proxyId;
        $cacheValue = $this->cache->getItem($cacheKey, $success);
        // by default false; if false, the tor setting will be reset
        $cacheValue = $success ? (int)$cacheValue : 0;
        return $cacheValue;
    }

    public function resetTorProxy($ip, $port, $auth): bool
    {
        pr('sending newsig to '. $ip. ':'. $port);
        $command = 'signal NEWNYM';
        $fp = fsockopen($ip, $port, $error_number, $err_string, 10);
        if (!$fp) {
            pr('failed to open socket '. $ip. ':'. $port);
            return false;
        }
        fwrite($fp, "AUTHENTICATE \"" . $auth . "\"\n");
        fread($fp, 512);
        fwrite($fp, $command . "\n");
        fread($fp, 512);
        fclose($fp);
        return true;
    }

    // working with tor, if we have

    public function getProxy(): Proxy
    {
        return $this->proxy;
    }

    /**
     * @param Proxy $proxy
     * @return $this
     */
    public function setProxy(Proxy $proxy): self
    {
        $this->proxy = $proxy;
        return $this;
    }

    /**
     * @param UserAgent $userAgent
     * @return $this
     */
    public function setUserAgent(UserAgent $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * @param bool $bool
     * @param $proxyId
     * @param bool $debug
     */
    public function setLastTorRequestResult(bool $bool, $proxyId, $debug = false): void
    {
        $value = $this->getLastTorRequestResult($proxyId);
        if($debug) {
            pr('set last tor result for' . $proxyId);
            pr('result is ' . $bool ? 'true' : 'false');
            pr('current proxy value' . $value);
        }
        if($bool){
            $newValue = $value + 1;
        } else {
            if($value) {
                // drop to zero on first fail
                $newValue = 0;
            } else {
                // decrease by 1;
                $newValue = $value - 1;
            }
        }
        if($debug) {
            pr('new proxy value' . $newValue);
        }

        $cacheKey = 'torLastRequestResult-' . $proxyId;
        $this->cache->setItem($cacheKey, $newValue);
    }

}
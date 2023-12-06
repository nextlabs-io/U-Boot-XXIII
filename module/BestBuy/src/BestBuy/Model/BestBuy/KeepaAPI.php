<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 31.07.2020
 * Time: 16:09
 */

namespace BestBuy\Model\BestBuy;


use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Parser\Model\Helper\Config;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\TableGateway\TableGateway;

class KeepaAPI extends TableGateway
{
    public const getProducts = 1;
    public const getDetails = 2;
    public const getDetailsByCode = 3;
    public const getTokenStatus = 4;

    public static $requestActions = [
        self::getProducts => ['POST', 'https://api.keepa.com/query?domain={LOCALE}&key={API_KEY}'],
        self::getDetails => ['GET', 'https://api.keepa.com/product?key={API_KEY}&domain={LOCALE}&asin={ASIN}'],
        self::getDetailsByCode => ['GET', 'https://api.keepa.com/product?key={API_KEY}&domain={LOCALE}&code={CODE}'],
        self::getTokenStatus => ['GET', 'https://api.keepa.com/token?key={API_KEY}']
    ];
    public static $contentType = 'application/json';
    public static $authHeader = [];
    private static $fields = [
        'action' => 'int',
        'product_id' => 'int',
        'campaign_id' => 'string',
        'error' => 'string',
        'status' => 'int',
        'created' => 'timestamp'
    ];
    public $lastInsertValue;
    public $table;
    public $adapter;
    public $data;
    /** @var $config Config */
    public $config;
    public $tokensLeft;
    public $locales = [1 => 'com', 2 => 'uk', 3 => 'de', 4 => 'fr',
        5 => 'jp', 6 => 'ca', 8 => 'it', 9 => 'es', 10 => 'in'];
    protected $apiKey;

    /**
     * CentricAPI constructor.
     * @param $config Config
     * @param $apiKey string
     */
    public function __construct($config, $apiKey = null)
    {
        $table = 'keepa';
        $this->config = $config;
        parent::__construct($table, $config->getDb());
        if ($apiKey) {
            $this->setApiKey($apiKey);
        }
    }

    public function getProducts($brand, $model)
    {
        $data['brand'] = $brand;
        $data['partNumber'] = $model;
        $result = [];
        $response = $this->sendRequest(self::getProducts, $data);
        $result['productSearch'] = $response;
        if ($asins = ($response['Data']['asinList'] ?? [])) {
            $asin = $asins[0];
            $details = $this->sendRequest(self::getDetails, ['ASIN' => $asin]);
            $result['productDetails'] = $details;
        }
        return $result;
    }

    /**
     * @param $requestType string
     * @param $data array
     * @param $key string
     * @return array
     * @throws \Exception
     */


    public function sendRequest($requestType, $data = [], $key = ''): array
    {
        $defaultLocale = 6;
        $options = [
            'http_errors' => false,
            'headers' => $this->generateHeader(),
            'timeout' => 30,
        ];
        if (!isset(self::$requestActions[$requestType])) {
            throw new \RuntimeException('not supported api request');
        }
        [$type, $url] = self::$requestActions[$requestType];

        // replacing url key

        if (strpos($url, '{API_KEY}')) {
            $url = str_replace('{API_KEY}', $this->getApiKey(), $url);
        }
        if (!($data['LOCALE'] ?? null)) {
            // set canadian locale
            $data['LOCALE'] = $defaultLocale;
        } else {
            // convert locale from 'com' to the number
            $locale = $data['LOCALE'];
            $data['LOCALE'] = array_search($locale,$this->locales, true) ?: $defaultLocale;
        }
        if (count($data)) {
            foreach ($data as $fieldId => $field) {
                if (strpos($url, '{' . $fieldId . '}')) {
                    $url = str_replace('{' . $fieldId . '}', $field, $url);
                    unset($data[$fieldId]);
                }
            }
        }
        if (count($data)) {
            $options[RequestOptions::JSON] = $data;
        }

        $config[RequestOptions::ALLOW_REDIRECTS] = [
            'max' => 5,
            'strict' => false,
            'referer' => true,
            'protocols' => ['http', 'https'],
            'track_redirects' => true
        ];
        $client = new Client($config);


        if ($type === 'GET') {
            $response = $client->get($url, $options);
        } elseif ($type === 'POST') {
            $response = $client->post($url, $options);
        } elseif ($type === 'DELETE') {
            $response = $client->delete($url, $options);
        } else {
            $response = $client->get($url, $options);
        }
        // response is always a json.
        $data = json_decode($response->getBody()->getContents(), true);

        if ($data['tokensLeft'] ?? null) {
            $this->tokensLeft = $data['tokensLeft'];
        }
        $response = ['Code' => $response->getStatusCode(), 'Data' => $data];
        if ($response['Code'] === 401) {
            pr($response);
            throw new \Exception('Unauthenticated.');
        }
        return $response;
    }

    private function generateHeader(): array
    {
        if (!$this->getApiKey()) {
            // throw exception
            throw new \RuntimeException('empty api key');
        }
        return ['Content-Type' => self::$contentType];
    }

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param mixed $apiKey
     */
    public function setApiKey($apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getProductsByCode($code, $locale = 'ca')
    {
        $data['CODE'] = $code;
        $data['LOCALE'] = $locale;
        return $this->sendRequest(self::getDetailsByCode, $data);
    }

    public function getApiKeyObfuscated()
    {
        return \Parser\Model\Helper\Helper::obfuscateString($this->getApiKey());
    }
}
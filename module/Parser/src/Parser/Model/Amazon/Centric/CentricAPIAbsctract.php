<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 13.07.2019
 * Time: 19:40
 */

namespace Parser\Model\Amazon\Centric;


use Parser\Model\Helper\Config;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\TableGateway\TableGateway;
use http\Exception\RuntimeException;
use Laminas\Db\Adapter\Adapter;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

abstract class CentricAPIAbsctract extends TableGateway
{
    public static $tableKey = 'centric_id';
    public const getStatus = 1;
    public const runProcess = 2;
    public const addProducts = 3;
    public const getProduct = 4;
    public const getAllProducts = 5;
    public const deleteAllProducts = 6;
    public const deleteProduct = 7;

    public static $requestActions = [
        self::getStatus => ['GET', 'https://v3.synccentric.com/api/v3/product_search/status'],
        self::runProcess => ['POST', 'https://v3.synccentric.com/api/v3/product_search'],
        self::addProducts => ['POST', 'https://v3.synccentric.com/api/v3/products'],
        // initial_identifier as id
        self::getProduct => ['GET', 'https://v3.synccentric.com/api/v3/products/{id}'],
        self::getAllProducts => ['GET', 'https://v3.synccentric.com/api/v3/products'],
        // either id of the product to the url, or all products in the campaign if not specified
        self::deleteAllProducts => ['DELETE', 'https://v3.synccentric.com/api/v3/products'],
        self::deleteProduct => ['DELETE', 'https://v3.synccentric.com/api/v3/products/{id}'],
    ];
    public static $contentType = 'application/json';
    public static $authHeader = ['Authorization' => 'Bearer {ApiKey}'];
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
    public $descriptionData = [
        'action' => 'Action type',
        'product_id' => 'Product ID',
    ];
    protected $apiKey;

    /**
     * CentricAPI constructor.
     * @param $db AdapterInterface
     * @param $config Config
     * @param $apiKey string
     */
    public function __construct($db, $config, $apiKey = null)
    {
        $table = 'centric';
        $this->config = $config;
        parent::__construct($table, $db);
        if ($apiKey) {
            $this->setApiKey($apiKey);
        }
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

    /**
     * @param $requestType string
     * @param $data array
     * @param $key string
     * @return array
     * @throws \Exception
     */


    public function sendRequest($requestType, $data = [], $key = ''): array
    {
        sleep(5);

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
        if (strpos($url, '{id}')) {
            $url = str_replace('{id}', $key, $url);
        }
        $config[RequestOptions::ALLOW_REDIRECTS] = $config[RequestOptions::ALLOW_REDIRECTS] ?? [
                'max' => 5,
                'strict' => false,
                'referer' => true,
                'protocols' => ['http', 'https'],
                'track_redirects' => true
            ];
        $client = new Client($config);

        if ($data) {
            $options[RequestOptions::JSON] = $data;
        }
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
        $headers = ['Content-Type' => self::$contentType];
        $auth = self::$authHeader;
        foreach ($auth as $key => $value) {
            $auth[$key] = str_replace('{ApiKey}', $this->getApiKey(), $value);
        }
        $headers += $auth;
        return $headers;
    }

}
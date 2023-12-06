<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 09.02.2020
 * Time: 12:15
 */

namespace Parser\Model\Amazon\Camel;


use Parser\Model\Helper\Helper;
use phpDocumentor\Reflection\Types\Parent_;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Parser\Model\Helper\Config;
use Parser\Model\TablePage;

/**
 * Class SyncProcessLimiter
 * This class to find out how many sync processes are running and to get process limits
 */
class Extractor extends TablePage
{
    public const STATUS_SUCCESS = 1;
    public const STATUS_NOT_FOUND = 2;
    public const STATUS_FAILED = 10;
    public const STATUS_FAILED_TO_EXTRACT_FIELDS = 11;
    public const STATUS_IN_PROGRESS = 20;
    public const STATUS_UNKNOWN_ERROR = 30;
    public const STATUS_NEVER_CHECKED = 50;

    public const DEBUG_SAVE_MODE = 3;
    public const DEBUG_PRINT_MODE = 1;

    private $debugMode;
    private $asin, $locale;


    /**
     * SyncProcessLimiter constructor.
     * @param $locale
     * @param $asin
     * @param Config $globalConfig
     * @param array $options
     * @throws \Exception
     */
    public function __construct($locale, $asin, Config $globalConfig, $options = [])
    {
        // [id, locale, asin, created, updated, code, data, message]
        $this->asin = $asin;
        $this->locale = $locale;
        if (!$asin) {
            throw new \Exception('no asin');
        }
        if (!$locale) {
            throw new \Exception('no locale');
        }

        $table = 'product_camel';
        $url = $this->generateUrl();
        parent::__construct($url, $globalConfig, $table);
        $this->debugMode = $options['debugMode'] ?? false;
        $this->fields = ['status', 'curl_code', 'asin', 'locale', 'data', 'ean', 'upc', 'updated', 'list_price', 'amazon_highest', 'amazon_average', 'thirdparty_highest', 'thirdparty_average'];
    }

    public function generateUrl()
    {
        $locale = $this->locale == 'com' ? '' : $this->locale . '.';
        $this->url = 'https://' . $locale . 'camelcamelcamel.com/product/' . $this->asin;
        return $this->url;
    }

    public function getProductData($loadFromDb = false)
    {
        $status = $this->checkStatus();
        if ($loadFromDb && $status === self::STATUS_SUCCESS) {
            return $this->loadDataAsArray();
        }
        if ($status === self::STATUS_NEVER_CHECKED || $status === self::STATUS_SUCCESS) {
            // extracting data
            $this->create();
            $this->getCamelPage();
            $config = $this->getConfig();
            if ($this->lastCallCode == 200) {
                // broken html
                $fields = $this->extractProductFields();
                if (!$fields) {
                    // failed to extract data;
                    $status = self::STATUS_FAILED_TO_EXTRACT_FIELDS;
                } else {
                    $status = self::STATUS_SUCCESS;
                }
                $prices = $this->extractPrices($this->content);
                $fields = array_merge($fields, $prices);

                $dataToSave = $this->generateDataToSave($status, $fields, $this->content, $this->lastCallCode);
                $this->updateProduct($dataToSave);
                // TODO optimize, no need to load it since all data is here
                return $this->loadDataAsArray();
            } else {
                // TODO handle this. for now just skip situation
                return [];
            }

        } else if ($status === self::STATUS_FAILED_TO_EXTRACT_FIELDS) {

            return [];
        } else {
            // not success may be in progress, will get later.
            return [];
        }
    }

    /**
     * Figure out if product was checked and in which status
     * @param $locale
     * @param $asin
     * @return int|mixed
     */
    public function checkStatus()
    {
        $where = new Where();
        $where->equalTo('locale', $this->locale);
        $where->equalTo('asin', $this->asin);
        $rowSet = $this->select($where);
        $data = [];
        $line = $rowSet->current();
        return (int)($line['status'] ?? self::STATUS_NEVER_CHECKED);
    }

    public function loadDataAsArray()
    {
        $rowSet = $this->select(['asin' => $this->asin, 'locale' => $this->locale]);
        $data = $rowSet->current();
        $data['data'] = unserialize($data['data']);
        return $data;
    }

    public function create()
    {
        return $this->insertOrUpdate(['asin' => $this->asin, 'locale' => $this->locale], ['asin' => $this->asin, 'locale' => $this->locale, 'status' => self::STATUS_IN_PROGRESS]);
    }

    public function getCamelPage($url = '')
    {
        if (!$url) {
            $url = $this->url;
        }
        $this->setTag($this->asin);
        $this->setGroup('camel-page');
        if (!$this->debugMode) {
            return $this->getPage($url);
        } elseif ($this->debugMode = self::DEBUG_SAVE_MODE) {
            $filePath = 'data/cache/camel-page-' . $this->asin . '-' . $this->locale . '.html';
            // we save data to file and if file exists - give the data without scraping.
            if (is_file($filePath)) {
                $content = file_get_contents($filePath);
                $this->content = $content;
                $this->lastCallCode = 200;
                return;
            } else {
                $this->getPage($url);
                file_put_contents($filePath, $this->content);
                return;
            }
        }
    }

    /**
     * get main fields of the product
     * @return array
     */
    public function extractProductFields()
    {
        $ctn = $this->content;
        $ctn = str_replace("</td>\n", '</td>', $ctn);
        $ctn = str_replace("</td>  <tr class=\"even\">", "</td>  </tr><tr class=\"even\">", $ctn);
        $fieldsCtn = $this->extractPartialContent("<table class=\"product_fields\">", "</table>", $ctn);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($fieldsCtn);
        $xpath = new \DOMXPath($dom);
        $res = $xpath->query('//td');
        $data = [];
        $keyMap = [0 => 'field', 1 => 'value'];
        foreach ($res as $key => $value) {
            $id = floor($key / 2);
            $key = $keyMap[$key % 2];
            $data[$id][$key] = trim($value->textContent);
        }
        $fields = [];
        foreach ($data as $field) {
            $key = strtolower($field['field']);
            $key = str_replace(' ', '_', $key);
            $fields[$key] = $field['value'];
        }


        return $fields;

    }

    public function extractPrices($content)
    {
        $hData = [];
        $data = ['amazon_highest' => '', 'amazon_average' => '', 'thirdparty_highest' => '', 'thirdparty_average' => ''];
        $headersMapper = ['amazon' => 'amazon', 'thirdparty' => 'new price'];

        $ex = new \Parser\Model\Html\Extractor($content);
        $pricesDivList = $ex->getResourceByXpath('//div[@id="histories"]/div');

        foreach($pricesDivList as $priceList){
            $table = $ex->getResourceByXpath('div//table[@class="product_pane"]/tbody/tr/td', $priceList);
            $header = $ex->getResourceByXpath('h4[contains(concat(@class, " "), "pricetype")]', $priceList);
            if(count($table) && count($header)) {
                $text = trim($header->item(0)->textContent);
                $title = '';
                foreach ($headersMapper as $key => $needle) {
                    if (stripos($text, $needle) !== false) {
                        $title = $key;
                    }
                }
                if($title) {
                    foreach ($table as $key => $item) {
                        if (strpos($item->textContent, 'Highest') !== false) {
                            $data[$title . '_highest'] = trim($table->item($key + 1)->textContent);
                        }
                        if (strpos($item->textContent, 'Average') !== false) {
                            $data[$title . '_average'] = trim($table->item($key + 1)->textContent);
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function generateDataToSave($status, $fields, $content, $curlCode = '', $message = '')
    {
        $data = ['fields' => $fields, 'content' => gzcompress($content)];
        $serialized = serialize($data);
        $ean = $fields['ean'] ?? null;
        $upc = $fields['upc'] ?? null;
        $listPrice = $fields['list_price'] ?? null;
        $dataToSave = ['asin' => $this->asin,
            'locale' => $this->locale, 'status' => $status,
            'list_price' => $listPrice,
            'ean' => $ean, 'upc' => $upc, 'data' => $serialized,
        ];
        if ($curlCode) {
            $dataToSave['curl_code'] = $curlCode;
        }
        if ($message) {
            $dataToSave['message'] = $message;
        }
        return $dataToSave;
    }

    public function updateProduct($data)
    {
        return $this->insertOrUpdate(['asin' => $this->asin, 'locale' => $this->locale], $data);
    }

    /**
     * clean broken items
     */
    public function resetHangingProducts()
    {
        $where = new Where();
        $where->equalTo('status', self::STATUS_IN_PROGRESS);
        $where->lessThan('created', new Expression('DATE_SUB(NOW(), INTERVAL 1 HOUR)'));
        $this->update(['status' => self::STATUS_UNKNOWN_ERROR], $where);
    }

    /**
     * @throws \Exception
     */
    public function selfUpdatePrices(): void
    {
        ini_set('memory_limit', 0);
        $sql = new Sql($this->getAdapter());
        $where = new Where();
        $where->isNull('amazon_highest')->equalTo('status', self::STATUS_SUCCESS);
        $select = $sql->select($this->getTable())
            ->where($where)
            ->columns(['asin', 'locale', 'data'])->limit(100);

        $stmt = $sql->prepareStatementForSqlObject($select);


        $result = $stmt->execute();

        while ($result->current()) {
            $element = $result->current();
            $data = unserialize($element['data']);
            $content = gzuncompress($data['content']);
            $ex = new Extractor($element['locale'], $element['asin'], $this->globalConfig);
            $prices = $ex->extractPrices($content);
//            $prices = array_filter($prices);
            if ($prices) {
                pr($prices);
                $ex->updateProduct($prices);
            }
            $result->next();
        }
    }
}
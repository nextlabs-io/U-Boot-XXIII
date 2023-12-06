<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 9/14/2020
 * Time: 10:09 PM
 */

namespace BestBuy\Model\BestBuy;


use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use Parser\Model\Profile;
use RuntimeException;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;

class ProductKeepa extends DefaultTablePage
{
    public $keepaColumns = [
        'asin',
        'brand',
        'product_group',
        'category',
        'manufacturer',
        'model',
        'ean',
        'upc',
        'mpn',
        'part_number',
        'label',
        'type',
        'rootCategory',
        'publisher',
        'description',
        'title',
        'features',
        'image',
        'data',
        'check'
    ];
    /**
     * @var KeepaAPI
     */
    public $keepa;

    public function __construct(Config $globalConfig, $apiCode = 'keepaApiKey')
    {
        $table = 'product_keepa';
        $tableKey = $table . '_id';
        parent::__construct('', $globalConfig, $table, $tableKey);
        array_push($this->fields, ...['locale', 'log'], ...$this->keepaColumns);
        $this->keepa = new KeepaAPI($this->globalConfig, $this->getApiKey($apiCode));
        $this->keepa->sendRequest(KeepaAPI::getTokenStatus);
    }

    public function getApiKey($code = 'keepaApiKey')
    {
        $identity = $this->getConfig('settings', 'keepaApiKeyIdentity') ?: 'admin';
        $profile = new Profile($this->getAdapter(), $identity);
        $key = $profile->getProfileSetting($code, $identity);
        if (!$key) {
            $key = $this->getConfig('settings', $code) ?: null;
        }
        if (!$key) {
            $this->msg->addError('KeepaApi key is missing');
        }
        return $key;
    }

    /**
     * @param array $asins
     * @param $locale
     * @return ProductKeepa
     */
    public function addNewProducts(array $asins, $locale): ProductKeepa
    {
        $static = 0;
        $asins = array_unique($asins);
        if (is_array($asins) && count($asins)) {
            array_map(function ($asin) use ($locale, $static) {
                $ids = ['asin' => $asin, 'locale' => $locale];
                $data = ['asin' => $asin, 'locale' => $locale];
                $data['updated'] = new Expression('NOW()');
                $result = $this->select($ids);
                if (!$result->current()) {
                    $data['created'] = new Expression('NOW()');
                    $data['status'] = self::STATUS_NEVER_CHECKED;
                    $this->insert($data);
                    $static++;
                } else {
                    $this->update($data, $ids);
                }
            }, $asins);
        }
        $this->msg->addMessage(count($asins) . ' unique asins found');
        $this->msg->addMessage($static . ' new asins added');

        return $this;
    }

    public function scrapeProduct()
    {
        if (!$this->keepa->tokensLeft || $this->keepa->tokensLeft < 10) {
            throw new RuntimeException('no api tokens left');
        }
        $product = $this->getScrapeCandidate();
        if ($product) {
            $this->setStatus($this::STATUS_CURRENTLY_IN_PROGRESS, $product[$this->getTableKey()]);
        } else {
            // no more products
            return null;
        }
        return $this->checkKeepa($product);
    }

    public function getScrapeCandidate($where = null): array
    {
        return parent::getScrapeCandidate($where); // TODO: Change the autogenerated stub
    }

    public function checkKeepa($product)
    {
        $asin = $product['asin'];
        $locale = $product['locale'];
        $keepaData = $this->keepa->sendRequest(KeepaAPI::getDetails, ['ASIN' => $asin, 'LOCALE' => $locale]);
        $totalResults = count($keepaData['Data']['products'] ?? []);
        $productResponse = ($keepaData['Data']['products'][0] ?? []);
        if ($totalResults) {
            $status = self::STATUS_SUCCESS;
            $keepaExtractedData = self::getFieldsFromKeepa($productResponse);
            $keepaExtractedData['status'] = $status;
            $keepaExtractedData['content'] = serialize($keepaData);
            $keepaExtractedData = $this->processData($keepaExtractedData);
            $this->update($keepaExtractedData, ['asin' => $asin, 'locale' => $locale]);
        } else {
            $status = self::STATUS_NOT_FOUND;
            $this->update(['status' => $status, 'content' => serialize($keepaData)], ['asin' => $asin, 'locale' => $locale]);
        }
        $this->limiter->touchProcess();
        $this->msg->addMessage('processing product: ' . $asin);
        return $status;
    }

    public static function getFieldsFromKeepa($fields)
    {
        $associations = [
            'asin' => 'asin',
            'brand' => 'brand',
            'product_group' => 'productGroup',
            'category' => 'categoryTree',
            'manufacturer' => 'manufacturer',
            'model' => 'model',
            'ean' => 'eanList',
            'upc' => 'upcList',
            'mpn' => 'mpn',
            'part_number' => 'partNumber',
            'label' => 'label',
            'type' => 'type',
            'rootCategory' => 'rootCategory',
            'publisher' => 'publisher',
            'description' => 'description',
            'title' => 'title',
            'features' => 'features',
            'image' => 'imagesCSV',
            'locale' => 'locale'
        ];
        $list = Product::getFieldsFromKeepaResponse($fields, $associations);
        $obfuscate = ['mpn', 'description', 'title', 'features', 'manufacturer', 'publisher', 'part_number', 'brand'];
        foreach ($obfuscate as $fieldId) {
            if (isset($list[$fieldId])) {
                $list[$fieldId] = \Parser\Model\Helper\Helper::stripDomains($list[$fieldId]);
            }
        }
        return $list;
    }

    /**
     * @return mixed
     */
    public function getAggregatedData()
    {
        $select = new Select($this->getTable());
        $select->columns(['qty' => new Expression('COUNT(*)'), 'status' => 'status']);
        $select->group('status');
        $rowSet = $this->selectWith($select);
        $data = [];
        while ($item = $rowSet->current()) {
            $data[] = $item;
            $rowSet->next();
        }
        return $data;
    }

    public function processKeepaFieldChange($tFrom = 5, $tFail = 2, $tTo = 100, $qty = 100): void
    {
        for ($i = 0; $i < $qty; $i++) {
            $select = new Select($this->getTable());
            $where = new Where();
            $where->equalTo('technical', $tFrom);
            $select->where($where);
            $select->limit(1);
            $rowset = $this->selectWith($select);
            $data = $rowset->current();
            $productId = $this->getIdFromArray($data);
            if (!$productId) {
                pr('no products found');
                break;
            }
            if (!$data['content']) {
                $this->update(['technical' => $tTo], [$this->tableKey => $productId]);
            } else {
                $keepaData = unserialize($data['content']);
                $totalResults = count($keepaData['Data']['products'] ?? []);
                $productResponse = ($keepaData['Data']['products'][0] ?? []);
//                pr($productResponse);
                if ($totalResults) {
                    $keepaExtractedData = self::getFieldsFromKeepa($productResponse);
                    $keepaExtractedData = $this->processData($keepaExtractedData);

                    $keepaExtractedData['technical'] = $tTo;
                    pr([$this->tableKey => $productId]);
                    pr($keepaExtractedData);
                    $this->update($keepaExtractedData, [$this->tableKey => $productId]);
                } else {
                    pr([$this->tableKey => $productId]);
                    pr('no changes');
                    $this->update(['technical' => $tFail], [$this->tableKey => $productId]);
                }
            }
        }
    }

    /**
     *
     */
    public function setNeverCheckedForFailed(): void
    {
        $where = new Where();
        $where->lessThan('updated', new Expression('DATE_SUB(NOW(), INTERVAL 1 HOUR)'));
        $where->equalTo('status', self::STATUS_CURRENTLY_IN_PROGRESS);
        $this->update(['status' => self::STATUS_NEVER_CHECKED], $where);
    }
}
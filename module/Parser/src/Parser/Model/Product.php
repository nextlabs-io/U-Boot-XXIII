<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 07.09.2017
 * Time: 12:01
 */

namespace Parser\Model;

/*
 * main class to handle product parsing
 */

use Parser\Model\Amazon\Attributes\SimpleAttribute;
use Parser\Model\Amazon\BrandBlacklist;
use Parser\Model\Amazon\Camel\Extractor;
use Parser\Model\Amazon\ProductMarker;
use Parser\Model\Amazon\Search\Product as Crawl;
use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Helper\CommonHook;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\EventLogger;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\Logger;
use Parser\Model\Html\ContentCollector;
use Parser\Model\Html\Paging;
use Parser\Model\Magento\Connector;
use Parser\Model\Magento\ProductToStore;
use Parser\Model\Magento\Request;
use Parser\Model\Product\Offers;
use Parser\Model\Product\SyncSpeed;
use Parser\Model\Web\Browser;
use Parser\Model\Web\Proxy;
use Westsworld\TimeAgo;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

// TODO SimpleObject is deprecated here.
class Product extends SimpleObject
{
    public $asin;
    public $locale;
    /* @var Logger $logger */
    public $logger;
    /* @var EventLogger $logger */
    public $eventLogger;
    public $localeConfig;
    public $moduleConfig;
    public $changed = 0;
    public $offersInfo = '';
    public $content;
    public $loaded = false;
    public $syncable = false;
    public $syncMessage = '';
    /* @var Config $config */
    public $config;
    public $dataCollector;
    public $primeOffers;
    public $primeTagForOffers = false;
    /* $var bool indicates if all queue processes has to be processed instantly  */
    private $configPath = 'data/parser/config/';
    private $proxy;
    /* $var bool indicates if only offers has to be processed  */
    private $userAgent;
    private $instantProcessing;
    private $processOnlyOffers;
    private $offers;
    public $offersPageRedirect;

    public function __construct(Config $config, $proxy, $userAgent, $asin = '', $locale = '')
    {
        $this->asin = $asin;
        $this->locale = $locale;
        $this->moduleConfig = $config->getConfig();
        $this->config = $config;
        /**
         * @var $proxy Proxy
         */
        $this->proxy = $proxy;
        $this->userAgent = $userAgent;

        $configFile = $this->configPath . 'profile/' . $this->locale . '.xml';

        // initial error checking


        if (!$asin) {
            $this->addError('No Asin were specified');
        }

        $this->localeConfig = Helper::loadConfig($configFile);

        if (!$this->localeConfig) {
            // can not perform parsing without locale file.
            $this->addError('No locale config file found');
        }
        $this->proxy->loadAvailableProxy();
        if ($this->proxy->hasErrors()) {
            $this->loadErrors($this->proxy);
        }
        // if there are errors, no parsing should be performed.
        // TODO add Config to class properties
        $this->logger = new Logger($this->proxy->getDb(), $this->moduleConfig['settings']);
        $this->eventLogger = new EventLogger($this->proxy->getDb(), []);

        $this->dataCollector = $this->getContentCollector();
    }

    /**
     * @return ContentCollector
     */
    public function getContentCollector(): ContentCollector
    {
        $settings = $this->config->getConfig('settings');
        $basePath = $settings['testContentPath'] ?? 'data/parser/test';
        return new ContentCollector($basePath, $this->config->getProperty('DebugMode'));
    }

    /**
     * @return array
     */
    public static function getEmptyProductData(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public static function getMassActions(): array
    {
        $list = [];
        $syncOptions = ProductSyncable::getOptions();
        foreach ($syncOptions as $key => $option) {
            $list[$key] = 'Set Sync Status: ' . $option;
        }
        $list['mass_update'] = 'Mass attributes update';
        $list['sync_selected'] = 'Sync Selected';
        $list['delete'] = 'Delete';

        return $list;
    }

    public static function getOrderedFields($data)
    {
        $list = [
            'product_id' => '',
            'title' => '',
            'asin' => '',
            'parent_asin' => '',
            'price' => '',
            'product_page_import_fee' => '',
            'product_page_price' => '',
            'offer_page_price' => '',
            'shippingPrice' => '',
            'prime' => '',
            'sku' => '',
            'locale' => '',
            'StockString' => '',
            'stock' => '',
            'shipping' => '',
            'delivery' => '',
            'isAddon' => '',
            'syncable' => '',
            'enabled' => '',
            'offerUrl' => '',
            'merchantOfferUrl' => '',
            'productUrl' => '',
            'merchantId' => '',
            'merchantName' => '',
            'fast_track' => '',
            'fast_track_to' => '',
            'fast_track_from' => '',

        ];
        return array_merge($list, $data);
    }

    /**
     * @param bool $val
     */
    public function setInstantProcessing(bool $val): void
    {
        $this->instantProcessing = $val;
    }

    public function setProcessOnlyOffers(bool $val): void
    {
        $this->processOnlyOffers = $val;
    }

    /**
     * @return array
     */
    public function getLocalesForForm(): array
    {
        $locales = $this->moduleConfig['locales'];
        $localeData = ['' => '--'];
        foreach ($locales as $item) {
            $localeData[$item['id']] = $item['id'];
        }
        return $localeData;
    }


    /**
     * @param $list
     * @param array $options
     * @return Product
     */
    public function addNewProducts($list, $options = []): Product
    {
        if (count($list)) {
            foreach ($list as $asin) {
                $this->add($asin, $this->locale, $options);
            }
        } else {
            $this->addMessage('No asins found in the file');
        }
        return $this;
    }

    public function add($asin, $locale, $options = []): Product
    {
        if (is_array($asin)) {
            $sku = $asin['sku'];
            $asin = trim($asin['asin']);
        } else {
            $asin = trim($asin);
            $sku = $asin;
        }
        $sql = new Sql($this->proxy->getDb());
        $select = $sql->select('product')->where(['asin' => $asin, 'locale' => $locale]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        if (!$this->validateAsin($asin) || !$this->validateLocale($locale)) {
            $this->addError($asin . '_' . $locale, 'locale:  and/or asin: are not correct');
            return $this;
        }
        if ($result->current()) {
            $this->addMessage($asin . '_' . $locale, 'products already exist, ' . $this->asin . ' ' . $this->locale);
            return $this;
        }

        // product does not exist, we may add it.
        $this->asin = $asin;
        $this->locale = $locale;
        //$data = $this->getProductData();


        $data['asin'] = $asin;
        $data['sku'] = $sku;
        $data['locale'] = $locale;
        $data['created'] = date('Y-m-d H:i:s');
        $data['modified'] = date('Y-m-d H:i:s');
        $data['updated_date'] = date('Y-m-d H:i:s');
        $data['next_update_date'] = date('Y-m-d H:i:s', strtotime('-3 years'));
        $data['sync_flag'] = false;
        $data['syncable'] = ProductSyncable::SYNCABLE_YES;

        if ($options && count($options)) {
            $data = array_merge($data, $options);
        }
        $insert = $sql->insert('product')
            ->values($data);
        $stmt = $sql->prepareStatementForSqlObject($insert);
        $result = $stmt->execute();

        if ($result->getAffectedRows()) {
            $this->addMessage($asin, 'added products: ');
        }

        $this->loadFromArray($data);

        return $this;
    }

    /**
     * @param $asin
     * @return bool
     */
    public function validateAsin($asin): bool
    {
        return strlen($asin) === 10;
    }

    public function validateLocale($locale)
    {
        $locales = $this->moduleConfig['locales'];
        $localeData = [];
        foreach ($locales as $item) {
            $localeData[] = $item['id'];
        }
        return in_array($locale, $localeData);
    }

    public function getLocales()
    {
        $sql = new Sql($this->proxy->getDb());
        $query = 'SELECT DISTINCT locale FROM product';
        $select = $sql->select('product');
        $stmt = $sql->prepareStatementForSqlObject($select);
        $stmt->setSql($query);
        $result = $stmt->execute();
        $list = ['-' => ['value' => '']];
        while ($result->current()) {
            $data = $result->current();
            $list[$data['locale']] = ['value' => $data['locale'], 'selected' => ''];
            $result->next();
        }
        return $list;
    }

    public function updateList($where, $data): int
    {
        $sql = new Sql($this->proxy->getDb());
        // delete products from magento if they are not syncable.
        if (isset($data['syncable']) && (int)$data['syncable'] !== ProductSyncable::SYNCABLE_YES) {
            // changing syncable state
            $config = new Config($this->proxy->getDb());
            $storeList = ProductToStore::getStoreIdsByProductList($config->getDb(), $where,
                ['enable' => 1, 'delete_trigger' => 1]);
            if (count($storeList)) {
                foreach ($storeList as $storeId => $productIds) {
                    $connector = new Connector($config, $storeId);


                    if ($connector->isConnected() && $connector->deleteUnsyncable()) {
                        // mass delete products
                        $connector->addRequestToQueue(Request::RequestDelete, ['list' => $productIds]);
//                        $connector->deleteRequest(['list' => $productIds]);
                    }
                }
            }
        }

        $update = $sql->update('product')
            ->where($where)
            ->set($data);
        $stmt = $sql->prepareStatementForSqlObject($update);

        $result = $stmt->execute();


        $this->addMessage('updated ' . $result->getAffectedRows());

        return $result->getAffectedRows();
    }

    public function getMassUpdateDataForConnector($where): array
    {
        $sql = new Sql($this->proxy->getDb());
        $list = [];
        $select = $sql->select('product')
            ->where($where)
            ->columns(['asin', 'locale']);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $res = $stmt->execute();
        while ($res->current()) {
            $list[] = $res->current();
            $res->next();
        }
        return $list;
    }

    public function getAsin($id)
    {
        $sql = new Sql($this->proxy->getDb());
        $select = $sql->select('product')->where(['product_id' => $id]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        if ($result->current()) {
            $data = $result->current();
            $list = [$data['asin'], $data['locale']];
        } else {
            $list = ['', ''];
        }
        return $list;

    }

    public function getProductIds($filter, $checkAllFlag)
    {
        if ($checkAllFlag) {
            // get all products according to the filter
            $where = $this->getCondition($filter);
        } else {
            if (isset($filter['products'])) {
                $list = array_keys((array)$filter['products']);
            } else {
                $list = [false];
            }
            $where = ['product_id' => $list];
        }
        return $where;
    }

    /**
     * @param $filter
     * @return Where
     */
    public function getCondition($filter): Where
    {
        $where = new Where();
        $where = $this->setWhere($filter, 'sku', 'like', $where);
        $where = $this->setWhere($filter, 'asin', 'like', $where);
        $where = $this->setWhere($filter, 'parent_asin', 'like', $where);
        $where = $this->setWhere($filter, 'locale', 'equalTo', $where);
        $where = $this->setWhere($filter, 'fromPrice', 'greaterThan', $where, ['float']);
        $where = $this->setWhere($filter, 'toPrice', 'lessThan', $where, ['float']);
        $where = $this->setWhere($filter, 'fromModified', 'greaterThan', $where, ['datetime']);
        $where = $this->setWhere($filter, 'toModified', 'lessThan', $where, ['datetime']);
        $where = $this->setWhere($filter, 'fromStock', 'greaterThan', $where, ['int']);
        $where = $this->setWhere($filter, 'toStock', 'lessThan', $where, ['int']);
        if (-1 !== (int)$filter['syncable']) {
            $where = $this->setWhere($filter, 'syncable', 'equalTo', $where);
        }
        if (-1 !== (int)$filter['enabled']) {
            $where = $this->setWhere($filter, 'enabled', 'equalTo', $where);
        }
        $where = $this->setWhere($filter, 'title', 'like', $where);
        return $where;
    }

    /**
     * @param $data
     * @param $value
     * @param $action
     * @param $where
     * @param array $validate
     * @return Where
     */
    private function setWhere($data, $value, $action, $where, $validate = []): Where
    {
        if (self::validateWhereValue($data, $value, $validate)) {

            /**
             * @var Where $where
             */
            switch ($action) {
                case 'equalTo':
                    $where->equalTo($value, $data[$value]);
                    break;
                case 'like':
                    $where->like($value, '%' . $data[$value] . '%');
                    break;
                case 'greaterThan' :

                    if (!in_array('datetime', $validate)) {
                        $properValue = $data[$value] - 0.001;
                    } else {
                        $date = \DateTime::createFromFormat('d/m/y H:i', $data[$value]);
                        $properValue = $date->format('Y-m-d H:i:s');
                    }
                    $where->greaterThanOrEqualTo(strtolower(str_replace('from', '', $value)), $properValue);
                    break;
                case 'lessThan' :
                    if (!in_array('datetime', $validate)) {
                        $properValue = $data[$value];
                    } else {
                        $date = \DateTime::createFromFormat('d/m/y H:i', $data[$value]);
                        $properValue = $date->format('Y-m-d H:i:s');
                    }
                    $where->lessThanOrEqualTo(strtolower(substr($value, 2)), $properValue);
                    break;
            }
        }
        return $where;
    }

    /**
     * @param $data
     * @param $value
     * @param array $validate
     * @return bool
     */
    public static function validateWhereValue($data, $value, $validate = []): bool
    {
        if (isset($data[$value]) && strlen($data[$value])) {
            if (count($validate)) {
                $isValid = true;
                foreach ($validate as $validator) {
                    switch ($validator) {
                        case 'int':
                            $isValid = ((int)$data[$value] || $data[$value] === 0);
                            break;
                        case 'float':
                            $isValid = ((float)$data[$value] || is_numeric($data[$value]));
                            break;
                        case 'datetime':
                            //"07/31/2018 12:46 AM"
                            $validator = new \Laminas\Validator\Date(['format' => 'd/m/y H:i']);
                            $isValid = $validator->isValid($data[$value]);
                            break;
                    }
                    if (!$isValid) {
                        return false;
                    }
                }
                return $isValid;
            }
            return true;
        }
        return false;
    }

    public function massSync($where)
    {
        $sql = new Sql($this->proxy->getDb());
        $select = $sql->select('product')
            ->where($where)
            ->columns(['product_id', 'asin', 'locale']);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        while ($data = $result->current()) {
            $asin = $data['asin'];
            $locale = $data['locale'];
            $toSync = new Product($this->config, $this->proxy, $this->userAgent, $asin, $locale);
            $toSync->setSyncFlag();
            $toSync->sync();
            $result->next();
        }

    }

    public function setSyncFlag($flag = true)
    {
        return $this->update(['sync_flag' => $flag, 'modified' => new Expression('NOW()')]);
    }

    /**
     * @param $data
     * @param bool $checkIfChanged
     * @return Product
     */
    public function update($data, $checkIfChanged = false): Product
    {
        $this->changed = 0;
        $changed = 0;
        $sql = new Sql($this->proxy->getDb());
        if ($checkIfChanged) {
            $oldData = $this->load()->getProperties();
            if ($oldData) {
                $attributesToCheck = ['price', 'stock'];
                foreach ($attributesToCheck as $attribute) {
                    if (isset($data[$attribute]) && $oldData[$attribute] != $data[$attribute]) {
                        $changed = $attribute === 'price' ? Helper::comparePriceDelta($oldData[$attribute], $data[$attribute]) : 1;
                        if ($changed) {
                            $this->saveAttributeChange($attribute, $data[$attribute], $this->getProperty('product_id'));
                            $this->logEvent($attribute, $this->asin);
                        }
                    }
                }
                if ($changed) {
                    // need to update the sync speed
                    $this->changed = 1;
                    $data['updated_date'] = date('Y-m-d H:i:s');
                } else {
                    $data['updated_date'] = $oldData['updated_date'];
                }
                $data['sync_speed'] = SyncSpeed::calculate($data, $oldData, $changed, $this->config->getConfig('settings'));
                // this one is deprecated
                $data['toDelete'] = $this->calculateToDeleteTrigger($data, $oldData);

            }
            $sync_delay = $this->moduleConfig['settings']['productSyncDelay'];
            $next_sync = isset($data['sync_speed']) ? $data['sync_speed'] * $sync_delay : $sync_delay;
            $data['next_update_date'] = new Expression('DATE_ADD(NOW(), INTERVAL  ' . $next_sync . ' SECOND)');
        }


        if (isset($data['sync_log']) && strlen($data['sync_log']) > 512) {
            $data['sync_log'] = substr($data['sync_log'], 0, 512);
        }
        if (isset($data['last_amazon_product_sync']) && !$data['last_amazon_product_sync']) {
            unset($data['last_amazon_product_sync']);
        }
        $update = $sql->update('product')
            ->where(['asin' => $this->asin, 'locale' => $this->locale])
            ->set($data);
        $stmt = $sql->prepareStatementForSqlObject($update);
        $stmt->execute();
        $this->addMessage('update successful');
        return $this;
    }

    public function load($forceReload = false, $asin = '', $locale = '')
    {
        if ($this->loaded && !$forceReload) {
            return $this;
        }
        $asin = $asin ?: $this->asin;
        $locale = $locale ?: $this->locale;
        if ($locale && $asin) {
            $sql = new Sql($this->proxy->getDb());
            $select = $sql->select('product')->where(['asin' => $asin, 'locale' => $locale]);
            $stmt = $sql->prepareStatementForSqlObject($select);
            $result = $stmt->execute();
            $data = $result->current();
            $this->loadFromArray($data);
            $this->loaded = 1;
        }
        return $this;
    }

    /**
     * @param string $attribute
     * @param $value
     * @param $productId
     */
    protected function saveAttributeChange(string $attribute, $value, $productId): void
    {
        if (!$productId) {
            return;
        }
        if ($attribute === 'stock') {
            $table = new TableGateway('product_stock', $this->config->getDb());
            $table->insert(['stock' => (int)$value, 'product_id' => $productId]);
        }
        if ($attribute === 'price') {
            $table = new TableGateway('product_price', $this->config->getDb());
            $table->insert(['price' => (float)$value, 'product_id' => $productId]);
        }
    }

    /**
     * @param $type
     * @param $asin
     * @param null $timeTaken
     */
    public function logEvent($type, $asin, $timeTaken = null): void
    {
        switch ($type) {
            case 'price' :
                $type = EventLogger::PRODUCT_PRICE_UPDATE;
                break;
            case 'stock' :
                $type = EventLogger::PRODUCT_STOCK_UPDATE;
                break;
            default:
                $type = EventLogger::PRODUCT_SYNC;
                break;
        }
        $this->eventLogger->add($type, $asin, $timeTaken);
    }

    public function calculateToDeleteTrigger($data, $oldData): bool
    {
        $syncSpeedLimit = $this->localeConfig['settings']['syncSpeedDelayToDeleteTrigger'] ?? 5;
        $stockString = $oldData['StockString'];
        return ($data['sync_speed'] > $syncSpeedLimit && $stockString === 'no offer found');
    }

    /**
     * @param bool $createOnMissing
     * @return $this|Product
     * @throws \Exception
     */
    public function sync($createOnMissing = true): self
    {
        $this->startTimeEvent();
        // if product does not exist, we need to create it, if it is - update it
        // during update we only change some of the fields, we do not change created, just modified and sync flag set to 0.
        if ($this->checkExist($this->asin, $this->locale)) {
            // update

            $parseOffers = $this->config->getProfileSetting('parseOffers');
//            pr($parseOffers);die();
            $syncables = [ProductSyncable::SYNCABLE_PRESYNCED, ProductSyncable::SYNCABLE_PREFOUND];

            if ((int)$parseOffers === 1 &&
                !in_array((int)$this->getProperty('syncable'), $syncables, true)) {
                $data = $this->getProductData();
            } else {
                // parsing only products page.
                $data = $this->getProductDataWithoutOffers();
            }
            $addImportFee = $this->config->getProfileSetting('addImportFee');
            $data = self::definePriceFromData($data, $addImportFee);
            $data['modified'] = date('Y-m-d H:i:s');
            $data['sync_flag'] = false;
            unset($data['asin'], $data['locale']);

            // do not change stock and price if got not proper curl code
            if ($this->config->getSetting('productKeepStockOnProxyFail') && $data['curl_code'] != 200) {
                unset($data['stock'], $data['price']);
            }


            // check syncable field if it has to be changed.
            $data = $this->checkSyncStatus($data);
            if (isset($data['made_by']) && $data['made_by']) {
                $this->addMessage('checking made by tag on blacklist');
                if (!BrandBlacklist::checkMadeByTag($data['made_by'], $this->locale, $this->proxy->getDb())) {
                    $this->syncable = ProductSyncable::SYNCABLE_BLACKLISTED;
                    $this->addMessage('blacklisted by brand');
                    $this->syncMessage = 'blacklisted by brand';
                }
            }

            /* blacklist for weight and dimension */
            if ($this->config->getSetting('blacklistByDimmension')) {
                $this->addMessage('checking dimensions blacklist');
                if (isset($data['title']) && $data['title'] && !$data['weight']) {
                    $this->syncable = ProductSyncable::SYNCABLE_BLACKLISTED;
                    $this->addMessage('blacklisted by weight');
                    $this->syncMessage = 'blacklisted by weight';
                } elseif (isset($data['title']) && $data['title'] && !$data['dimension']) {
                    $this->syncable = ProductSyncable::SYNCABLE_BLACKLISTED;
                    $this->addMessage('blacklisted by dimension');
                    $this->syncMessage = 'blacklisted by dimension';
                }
            }
            /* blacklist by addon flag */
            if ($this->config->getSetting('blacklistByAddon') && isset($data['title']) && $data['title'] && $data['isAddon']) {
                $this->syncable = ProductSyncable::SYNCABLE_BLACKLISTED;
                $this->addMessage('blacklisted by addon');
                $this->syncMessage = 'blacklisted by addon';
            }

            /* blacklist by delivery data */
            if ($this->config->getSetting('blacklistByDelivery') && isset($data['title']) && $data['title'] && $data['delivery_data'] && strpos($data['delivery_data'],
                    'This item does not ship to the United States.') !== false) {
                $this->addMessage('blacklisted by delivery data (not shipped to us)');
                $this->syncable = ProductSyncable::SYNCABLE_BLACKLISTED;
                $this->syncMessage = 'blacklisted USA';
            }

            /* add variation values to the product */
            $title = (isset($data['title']) && $data['title']) ? $data['title'] : $this->getProperty('title');
            if ($this->config->getSetting('addVariationAttributeToTitle') && $title && $this->getProperty('variation_attributes')) {
                $attributes = unserialize($this->getProperty('variation_attributes'));
                ksort($attributes);

                foreach ($attributes as $key => $attribute) {
                    if (!strpos($title, $attribute)) {
                        $title .= ' ' . $attribute;
                    }
                }
                $data['title'] = $title;
            }

            if ($this->syncable) {
                $data['syncable'] = $this->syncable;
            }
            if ($this->syncMessage) {
                $data['sync_message'] = $this->syncMessage;
            } else {
                $data['sync_message'] = '';
            }
            // zero stock if blacklisted or deleted
            if (isset($data['syncable'])
                && in_array($data['syncable'],
                    [ProductSyncable::SYNCABLE_DELETED, ProductSyncable::SYNCABLE_BLACKLISTED], true)) {
                if ($this->syncMessage !== 'blacklisted USA') {
                    $this->addMessage('setting stock to zero due to blacklisted');
                    $data['stock'] = 0;
                }
            }
            // remove descriptive fields in case if no content were retrieved
            $dimensions = $this->getDimensions($data);
            $data = array_merge($data, $dimensions);
            $data = $this->removeEmptyDescriptiveFields($data);


            // update product
            $this->update($data, 1);
            $this->load(1);

            $config = new Config($this->proxy->getDb());
            $storeIds = ProductToStore::getStoreIdsByProduct($this->proxy->getDb(), $this->getProperty('product_id'));

            foreach ($storeIds as $storeId) {
                $connector = new Connector($config, $storeId);
                // if we need to process requests instantly
                $connector->setInstantProcessing($this->instantProcessing);
                $connector->processSyncRequest($this->getDataForMagento());
                if ($connector->hasErrors()) {
                    // add some error messaging
                    $this->appendMessagesFromObject($connector, true);

                } elseif ($connector->instruction) {
                    $this->update($connector->instruction);
                    foreach ($connector->instruction as $key => $item) {
                        $this->setProperty($key, $item);
                    }
                }
            }
            $hook = new CommonHook($this->config);
            $hook->findHook('product-sync', ['asin' => $this->asin, 'locale' => $this->locale, 'data' => $this->getProperties()]);
        }
        if ($createOnMissing) {
            $this->add($this->asin, $this->locale);
        }
        $syncTime = $this->endTimeEvent();
        $this->addMessage('sync time (seconds) :' . $syncTime / 1000);
        $this->updateSyncLog()->logEvent('sync', $this->asin, $syncTime);

        return $this;
    }

    public function checkExist($asin, $locale): bool
    {
        if ($this->getProperty('product_id')) {
            return true;
        }
        $sql = new Sql($this->proxy->getDb());
        $select = $sql->select('product')->where(['asin' => $asin, 'locale' => $locale]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        if ($data = $result->current()) {
            $this->loaded = true;
            $this->loadFromArray($data);
            return true;
        }
        return false;
    }

    public function getProductData()
    {
        $settings = $this->config->getConfig('settings');
        /*
         * general workflow:
         * 1. get offer page and take first offer required
         *    get also offer page with prime offers.
         * 2. if no offer with required params - return zero qty/price
         * 3. once offer is here, get product page for right qty and price
         * TODO - redo the logic, get all offers, and many offers might be processed before getting the result, now first selected offer does the impact: if it is an addon or out of stock - this offer is used. However, may be other offer with higher price will do the job.
         *
         */

        $browser = $this->getBrowser();
        $browser->setGroup('amzn-product-page')->setTag($this->asin);
        // take the home page first. it should be easy
//        $baseUrl = $this->localeConfig['settings']['baseUrl'] ?? '';
//        if ($baseUrl) {
//            $browser->getAdvancedPage($baseUrl);
//        }


        // retrieve offers
        [$offers, $offerUrl, $initialProductUrl] = $this->getOffers($browser);
        $this->offers = $offers;

        $amazonDetails = $this->getAmazonDetails($offers);
        // there are two modes - simply get offers, and get offers with a detailed page. mode is defined in the globalConfig, but if there is a need to run full scan once? It should be an option mode

        $productDetails = new ProductDetails($this->logger, $this->asin);
        if($this->offersPageRedirect){
            $this->addMessage('processing details page');
            $productUrl = $this->compaundProductUrl(null, $initialProductUrl);
            $content = $browser->getAdvancedPage($productUrl)->getContent();
            $this->saveFile($content);
            $this->content = $content;
            // getting details from product page.
            $details = $productDetails->parse($content, $this->localeConfig);
            if (Helper::ifINN($this->localeConfig['offersPage'], 'sellerTag')) {
                $sellerValue = str_replace('{seller}', $details['merchantId'],
                    $this->localeConfig['offersPage']['sellerTag']);
                $merchantOfferUrl = $offerUrl . $sellerValue;
            } else {
                $merchantOfferUrl = null;
            }
            $this->offersInfo = 'redirect from offers page';
            $data = [
                'offerUrl' => $offerUrl,
                'merchantOfferUrl' => $merchantOfferUrl,
                'productUrl' => $productUrl,
                'sync_log' => $this->getStringErrorMessages(),
                'curl_code' => $browser->code,
            ];
            $data = array_merge($data, $details, $amazonDetails);
            $data = $this->calculateStockAndDelivery($data);

        } elseif ($this->processOnlyOffers) {
            $this->offersInfo = 'process only offers mode enabled';
            $this->addMessage('process only offers mode enabled');
            // define stock here.
        } elseif (!count($offers)) {
            $this->offersInfo = $this->offersInfo ?: 'no offer found';
            $this->addMessage('no offer found');
        } else {
            $this->addMessage('found offers: ' . count($offers));
            $offersIterator = new Offers($this->offers, $browser);
            // run though offers and check positive stock. First positive stock goes to production
            // check for import_fee, if import_fee and price is not lowest - get into next offer.
            $offersToConsider = $this->offers;

            // check allowed proxy groups, in order to make page request for product page properly
            $allowedGroups = $this->localeConfig['productPage']['proxy_groups'] ?? false;
            if ($allowedGroups) {
                $allowedGroups = explode(',', $allowedGroups);
                $browser->proxy->setAllowedGroups($allowedGroups);
                $browser->proxy->loadAvailableProxy();
            }

            foreach ($this->offers as $offerKey => $offer) {

                $productUrl = $this->compaundProductUrl($offer['merchantId'], $initialProductUrl);
//                pr($productUrl);
                $this->addMessage('processing offer: ' . $offerKey);
                $content = $browser->getAdvancedPage($productUrl)->getContent();
                $this->saveFile($content);
                $this->content = $content;
                // getting details from product page.
                $details = $productDetails->parse($content, $this->localeConfig);

                if (Helper::ifINN($this->localeConfig['offersPage'], 'sellerTag')) {
                    $sellerValue = str_replace('{seller}', $offer['merchantId'],
                        $this->localeConfig['offersPage']['sellerTag']);
                    $merchantOfferUrl = $offerUrl . $sellerValue;
                } else {
                    $merchantOfferUrl = null;
                }

                $data = [
                    'offerUrl' => $offerUrl,
                    'merchantOfferUrl' => $merchantOfferUrl,
                    'productUrl' => $productUrl,
                    'sync_log' => $this->getStringErrorMessages(),
                    'curl_code' => $browser->code,
                ];

                unset($offer['offerValue']);
                $data = array_merge($data, $offer, $details, $amazonDetails);
                $data = $this->calculateStockAndDelivery($data);
                // we have checked the offer, and if is stock - it is good. Else, try again
                if ($data['stock']) {
                    $this->addMessage('got positive stock');
                    if ($merchantId = $data['merchantId'] ?? null) {
                        // we should check merchant if there is a merhcant
                        $this->addMessage('starting merchant marker check');
                        $pm = new ProductMarker($content, $this->localeConfig);
                        [$marker, $merchantCheck] = $pm->checkMerchant($merchantId);
                        if (!$marker) {
                            $this->addMessage('missing merchant marker');
                        } elseif (!$merchantCheck) {
                            $this->addMessage('wrong merchant content received');
                        } elseif ($this->localeConfig['settings']['amazonMerchantId'] === $merchantId) {
                            $this->addMessage('merchant marker match');
                            $data['prime'] = true;
                        } else {
                            // define prime offers here and set prime status.
                            if ($this->primeTagForOffers) {
                                $primeOffers = $this->getPrimeOffers($browser, $offers);
                            } else {
                                $primeOffers = $this->getPrimeOffers($browser);
                            }
                            if ($primeOffers && isset($primeOffers[$merchantId])) {
                                $this->addMessage('merchant marker match prime offers');
                                $data['prime'] = true;
                            }
                        }
                    }
                    // offer checked, now recheck prices
                    $offersToConsider[$offerKey]['data'] = $data;
                } else {
                    unset($offersToConsider[$offerKey]);
                }
                if (!$offersIterator->getOffersToCheck($offersToConsider)) {
                    break;
                }
                // check next offer
                $this->offersInfo = $data['stockString'] ?? null;
            }
            if ($allowedGroups) {
                $browser->proxy->resetAllowedGroups();
                $browser->proxy->loadAvailableProxy();
            }

            if ($offersToConsider && ($properOfferData = $offersIterator->getProperOffer($offersToConsider))) {
                // need to extract data from processed offers
                $this->appendMessagesFromObject($offersIterator, false);
                return $properOfferData;
            }
            $this->addMessage('no stock offer found');
            $this->offersInfo = $this->offersInfo ?: 'no stock offer found';
        }


        // no offers or no stock offer found. we are out of stock.
        if (!$this->getProperty('title')) {
            // product didn't get a title ever, we need to parse product page.
            $this->addMessage('empty title, getting product details page without offer');
            $details = $this->getProductDataWithoutOffers();
            $details['stock'] = 0;
            $amazonDetails = array_merge($details, $amazonDetails);
        }
        $stockString = $this->offersInfo ?: 'no offer found';
        if (!isset($data)) {
            $data = [
                'offerUrl' => $offerUrl,
                'merchantOfferUrl' => null,
                'sync_log' => $this->getStringErrorMessages(),
                'curl_code' => $browser->code,
                'stock' => 0,
                'delivery' => null,
                'merchantId' => null,
                'merchantName' => null,
                'shipping' => null,
                'stockString' => $stockString,
            ];
            $data = array_merge($data, $amazonDetails);
        } else {
            $data['stockString'] = $stockString;
        }
        $obfuscateList = ['merchantName', 'model', 'made_by', 'brand', 'mpn'];
        foreach ($obfuscateList as $fieldId) {
            if (isset($data[$fieldId])) {
                $data[$fieldId] = Helper::stripDomains($data[$fieldId]);
            }
        }

        return $data;
    }

    public function getBrowser($url = '')
    {
        $browserConfig = $this->moduleConfig['captcha'];
        $browserConfig['cookie_file'] = $this->getCookieFile();
        $browserConfig['data_dir'] = '/data/parser/cookie';
        if ($debugMode = $this->config->getProperty('DebugMode')) {
            $browserConfig['debugMode'] = $this->config->getProperty('DebugMode');
            if ($debugMode == '2') {
                $browserConfig['mode'] = 'developer';
            }
        }

        $settings = $this->config->getConfig('settings');
        $timeout = $settings['curlTimeout'] ?? 30;
        $browserConfig['timeout'] = $timeout;
        $browser = new Browser($url, $this->proxy, $this->userAgent, $browserConfig);
        // adding a cookie file
        if (isset($this->localeConfig['settings']['browserHeader'])) {
            $browser->generateHeader($this->localeConfig['settings']['browserHeader']);
        }
        return $browser;
    }

    /**
     * @return string
     */
    public function getCookieFile(): string
    {
        return $this->asin . '_' . $this->locale . '_cookie';
    }

    /**
     * // general function to get offers of the product
     * @param $browser Browser
     * @return array
     * @throws \Exception
     */
    public function getOffers($browser): array
    {

        // debugging required file
        if ($this->config->getProperty('DebugMode') == 2 && $content = $this->getFile('offer')) {
            $browser->code = 200;
            $offerUrl = $this->compaundOfferUrl();
        } else {
            // getting offers page
            $offerUrl = $this->compaundOfferUrl();
            // proxy and user agent options are sensitive to errors.
            // first check if we need to get a cookie for the list
            $offerUrl = $this->getAlternativeOfferUrl($offerUrl, $browser);
            // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.
            $timeStart = time();
            $content = $browser->setTag($this->asin)
                ->setGroup('amzn-offer')
                ->getAdvancedPage($offerUrl)
                ->getContent();
            $delta = time() - $timeStart;
            $this->addMessage('Got offer page in ' . $delta . ' seconds');
            if ($browser->code === 400) {
                // no such product
                $this->addMessage('Got 400 response for offer');
                $browser->addError('no product found');
            }
        }
        $offers = [];
        $productUrl = '';

        if ($browser->code === 200) {
            if(strlen($content) > 30000) {
                $offerParser = new ProductOffer($this->logger, $this->asin);
                // parse offers from the html page
                $offers = $offerParser->parse($content, $this->localeConfig, $this->locale);
                $this->appendMessagesFromObject($offerParser);

                if ($this->config->getProperty('DebugMode')) {
                    pr('total offers');
                    pr($offerParser->returnUrl);
                    pr($offers);
                }

                // sort offers by value and skip useless offers according to the settings
                $offers = $this->offerQA($offers);
                $this->addMessage('offers left after QA:' . count($offers));

                $this->saveFile($content, 'offer');

                if ($this->config->getProperty('DebugMode')) {
                    // saving content
                    pr('acceptable offers');
                    pr($offers);
                }
                $productUrl = $offerParser->returnUrl;
            } else {
                // TODO new logic, amazon gives a redirect url if there is one offer in the page.
                // content length is ~20k
                $this->addMessage('got offers page redirect to product page');
                $this->offersPageRedirect = true;
                // a redirect page, we can not get any offer data here
                return [$offers, $offerUrl, $productUrl];
            }
        }
        return [$offers, $offerUrl, $productUrl];
    }

    /**
     * @param $asin
     * @param $locale
     * @param string $type
     * @return bool|string
     */
    public function getFile($type = 'product', $asin = '', $locale = '')
    {
        $asin = $asin ?: $this->asin;
        $locale = $locale ?: $this->locale;
        if (!$type) {
            return false;
        }
        $tag = $type . '/' . $locale . '/' . $asin . '.html';
        return $this->dataCollector->getFile($tag);
    }

    public function compaundOfferUrl()
    {
        $url = $this->getUrl($this->localeConfig['offersPage']['offerUrl']);
        $refList = [
            'ref=olp_f_freeShipping',
            'ref=olp_f_new',
            'ref=olp_f_used'
        ];
        $ref = $refList[array_rand($refList)];
        $url = str_replace('{ref}', $ref, $url);
        /*
         * primeTag is crucial here, if the prime tag is here, we need to put offers to prime.
         */
        foreach ($this->localeConfig['offersPage']['tags'] as $tagKey => $tag) {
            $settingTag = $this->locale . '_' . $tagKey;
//            pr($settingTag);
            $profileSetting = $this->config->getProfileSetting($settingTag);
//            pr($profileSetting === null ? 'null' : $profileSetting);
            // $profileSetting === null - means it is not in the profile settings
            if ($profileSetting || $profileSetting === null) {
                if ($settingTag === 'primeTag') {
                    // indicate, we have offers with prime tag already.
                    $this->primeTagForOffers = true;
                }
                $url .= $tag;
            }
        }
        return $url;
    }

    private function getUrl($url)
    {
        return $this->localeConfig['settings']['baseUrl'] . str_replace('{ASIN}', $this->asin, $url);
    }

    public function getAlternativeOfferUrl($offerUrl, $browser)
    {
        if (isset($this->localeConfig['settings']['checkOfferCookie']) && $this->localeConfig['settings']['checkOfferCookie']) {

            $initialProduct = $this->compaundProductUrl();
            $initialContent = $browser->getAdvancedPage($initialProduct)->getContent();
            // extract offer url
            if ($offerPath = $this->localeConfig['productPage']['paths']['offerUrl']) {
                $details = new ProductDetails($this->logger);
                $urlElement = $details->getFirstElementByXpath($initialContent, $offerPath);
                if ($urlElement && $offerUrl = $urlElement->value) {
                    $offerUrl = ltrim($offerUrl, '/');
                    // getting new offer url
                    if (($this->localeConfig['offersPage']['tags']['newTag'] ?? null) && strpos($offerUrl, $this->localeConfig['offersPage']['tags']['newTag']) === false) {
                        $offerUrl .= $this->localeConfig['offersPage']['tags']['newTag'];
                    }
                    if (($this->localeConfig['offersPage']['tags']['freeshippingTag'] ?? null) && strpos($offerUrl, $this->localeConfig['offersPage']['tags']['freeshippingTag']) === false) {
                        $offerUrl .= $this->localeConfig['offersPage']['tags']['freeshippingTag'];
                    }
                    if (($this->localeConfig['offersPage']['tags']['primeTag'] ?? null) && strpos($offerUrl, $this->localeConfig['offersPage']['tags']['primeTag']) === false) {
                        $settingTag = $this->locale . '_primeTag';
                        $profileSetting = $this->config->getProfileSetting($settingTag);
                        // $profileSetting === null - means it is not in the profile settings
                        if ($profileSetting || $profileSetting === null) {
                            $offerUrl .= $this->localeConfig['offersPage']['tags']['primeTag'];
                        }
                    }
                    $offerUrl = $this->getUrl($offerUrl);

                }
            }
        }

        return $offerUrl;
    }

    public function compaundProductUrl($merchantId = null, $productUrl = null)
    {
        $compoundProductUrl = $this->localeConfig['productPage']['combineProductUrl'] ?? false;
        if ($productUrl && $compoundProductUrl) {
            $url = $productUrl;
        } else {
            $url = $this->getUrl($this->localeConfig['productPage']['productUrl']);
        }
        // add merchant id tag if not added yet
        if ($merchantId && strpos($url, $merchantId) === false) {
            $merchantTag = str_replace('{MerchantId}', $merchantId,
                $this->localeConfig['productPage']['merchantUrlTag']);
            if (strpos($url, '?') === false) {
                $merchantTag = str_replace('&', '?', $merchantTag);
            }
            $url .= $merchantTag;
        }
        if (strpos($url, 'qid') === false) {
            $glue = (strpos($url, '?') === false) ? '?' : '&';
            $url .= $glue . 'qid=' . time();
        }
        return $url;
    }

    private function offerQA($offers)
    {
        $offers = $this->skipUselessOffers($offers);
        $this->addMessage('offers left after skipping useless: ' . count($offers));
        $paths = (object)$this->localeConfig['offersPage']['paths'];
        $primeWeight = $this->localeConfig['offersPage']['primeOfferWeight'] ?? 0;
        $fbaWeight = $this->localeConfig['offersPage']['fbaOfferWeight'] ?? 0;

        // return a number
        if (!count($offers)) {
            return [];
        }
        $values = [];
        foreach ($offers as $key => $offer) {
            $offerValue = 0;
            // skip the offer if the shipping string does not match the one we need
            if (isset($paths->shippingString) && $offer['shipping'] !== $paths->shippingString) {
//                $this->addMessage('Skipping offer:'. $offer['merchantId']. ' for shipping string: '.$offer['shipping']);
                continue;
            }
            if ($offer['prime']) {
                $offerValue += $primeWeight;
            }
            if ($offer['merchantName'] === 'Amazon' || strpos($offer['delivery'], $paths->fba) !== false) {
                $offerValue += $fbaWeight;
            }
            $offers[$key]['offerValue'] = $offerValue;
            $values[$key] = $offerValue;
        }

        if (count($values)) {
            $newOffersGroupped = [];
            arsort($values);
            foreach ($values as $key => $value) {
                $newOffersGroupped[$value][$offers[$key]['offer_page_price'] * 100 + 100][$key] = $offers[$key];
            }
            $newOffers = [];
            foreach ($newOffersGroupped as $priceGroup) {
                ksort($priceGroup);
                foreach ($priceGroup as $price => $items) {
                    $newOffers = array_merge($newOffers, $items);
                }
            }
            return $newOffers;
        }
        return [];
    }

    private function skipUselessOffers($offers)
    {
        pr($offers);
        if (!count($offers)) {
            return [];
        }

        $skippedOffers = Amazon\Seller::getBlockedSellerId($this->asin, $this->locale, $this->proxy->getDb());
        if (isset($skippedOffers['skipAll'])) {
            // product has to be blacklisted;
            $this->setSyncable(ProductSyncable::SYNCABLE_BLACKLISTED);
            $this->addMessage('asin blacklisted, skipping all offers');
            $this->syncMessage = 'asin blacklisted';
            return [];
        }

        $skip = $this->localeConfig['offersPage']['skip'] ?? [];
        //if(! $skip) return $offers;
        $pCountries = [];
        $toSkip = [];
        if (isset($this->localeConfig['offersPage']['preferredCountry'])) {
            // unset offer if country of delivery is not good
            $pCountries = explode(',', $this->localeConfig['offersPage']['preferredCountry']);
        }
        $offersMaxPrice = $this->config->getSettingOverride('offersMaxPrice');


        foreach ($offers as $key => $offer) {

            if ($skip) {
                foreach ($skip as $skipKey => $skipValue) {
                    if (Helper::compare($skipValue['value'], $offer[$skipKey], $skipValue['validator'])) {
                        $this->addMessage('skipping offer ' . $offer['merchantId'] . ' due too ' . $skipKey . '=' . $offer[$skipKey]);
                        unset($offers[$key]);
                        continue;
                    }
                }
            }
            if ($offersMaxPrice) {

                if ($offer['offer_page_price'] > $offersMaxPrice) {
                    $this->addMessage('remove offer due to maxPrice limit ' . $offer['offer_page_price'] . ' ' . $offer['merchantId']);
                    unset($offers[$key]);
                }
            }

            if (is_array($skippedOffers) && in_array($offer['merchantId'], $skippedOffers)) {
                // unset offer if merchant in the black list
                $this->addMessage('remove blacklisted merchant ' . $offer['merchantId']);
                unset($offers[$key]);
            } elseif ($pCountries) {
                // unset offer if country of delivery is not good
                $shipsFromTag = $this->localeConfig['offersPage']['shipsFromTag'] ?? '/Ships from ([A-Za-z\-, ]+)/';
                $check = Helper::getCountryDeliveryCheck($shipsFromTag, $offer['delivery'], $pCountries);
                if (!$check) {
                    // unset offer and put merchantId to the skippedOffers, so that all offers of this seller to be deleted
                    $this->addMessage('skipping merchant ' . $offer['merchantId'] . ' due to country delivery options');
                    $this->offersInfo = $this->syncMessage = 'skipping merchant ' . $offer['merchantId'] . ' due to country delivery options';
                    $toSkip[] = $offer['merchantId'];
                    unset($offers[$key]);
                }
            }
        }
        // delete all offers of the skipped merchants
        if (count($toSkip) && count($offers)) {
            foreach ($offers as $key => $offer) {
                if (in_array($offer['merchantId'], $toSkip)) {
                    unset($offers[$key]);
                }
            }
            if (!count($offers)) {
                $this->addMessage('skipped all offers due to preferred country options');
                $this->offersInfo = $this->syncMessage = 'skipped offers by preferred country';
            }
        }

        return $offers;
    }

    // get data related to third party systems - amazon api, camelcamel etc

    /**
     * @param $int
     * @return $this
     */
    public function setSyncable($int): self
    {
        $this->syncable = $int;
        return $this;
    }

    /**
     * @param string $content page content
     * @param string $asin ASIN
     * @param string $locale Locale
     * @param string $type
     * @return bool|void
     * @throws \Exception
     */
    public function saveFile($content, $type = 'product', $asin = '', $locale = '')
    {
        $asin = $asin ?: $this->asin;
        $locale = $locale ?: $this->locale;
        // possible type: product, offer, home etc
        if (!$type) {
            return false;
        }
        $tag = $type . '/' . $locale . '/' . $asin . '.html';
        return $this->dataCollector->saveFile($content, $tag);
    }

    public function getAmazonDetails($offers = [])
    {
        $settings = $this->config->getConfig('settings');
        $amRoduct = new Amazon\Product($this->localeConfig, $this->proxy->getDb());
        $columns = ['short_description', 'ean', 'upc', 'mpn', 'model', 'manufacturer', 'brand', 'ean_list', 'upc_list', 'list_price', 'amazon_highest', 'amazon_average', 'thirdparty_highest', 'thirdparty_average', 'last_amazon_product_sync'];
        $amazonDetails = $amRoduct->loadProductFromDbWithoutEmptyFields($this->asin, $this->locale, $columns);
        $checkCamel = $this->localeConfig['settings']['checkCamel'] ?? false;
        $ean = $amazonDetails['ean'] ?? null;
        $upc = $amazonDetails['upc'] ?? null;
        if ($checkCamel) {
            $camel = new Extractor($this->locale, $this->asin, $this->config, ['debugMode' => $this->config->getProperty('DebugMode')]);
            $data = $camel->getProductData();
            if (($data['ean'] ?? false) || ($data['upc'] ?? false)) {
                $amazonDetails = array_intersect_key($data['data']['fields'], array_flip($columns));
                // success!
                $amRoduct->simpleUpdate($this->asin, $this->locale, $amazonDetails);
            }
        }

        $amazonDetails = Helper::stripDomains($amazonDetails);
        if (isset($settings['storeOffersData']) && $settings['storeOffersData']) {
            $amazonDetails['offers_data'] = serialize($offers);
        }
        $amazonDetails['offers_qty'] = count($offers);
        return $amazonDetails;
    }

    public function calculateStockAndDelivery($data)
    {
        // we need to decide here if product is good for sale, if not, the stock is set to zero.
        $isAddon = $data['isAddon'];
        $shippingPrice = $data['shippingPrice'] ?? null;
        $deliveryText = $data['delivery'] ?? '';
        $merchantName = $data['merchantName'] ?? 'Amazon';
        $fba = strpos($deliveryText, $this->localeConfig['offersPage']['paths']['fba']) ? true : false;
        $requireZeroShipping =  $this->localeConfig['offersPage']['tags']['freeshippingTag'] ?? null;
        if ($isAddon) {
            $data['stock'] = 0;
            $data['stockString'] = 'is Addon';
            return $data;
        }
        if($shippingPrice && $requireZeroShipping) {
            $this->addMessage('shipping price not zero, set stock to 0');
            $data['stock'] = 0;
        }
        if(isset($this->localeConfig['offersPage']['preferredCountry']) && $deliveryText){
            $pCountries = explode(',', $this->localeConfig['offersPage']['preferredCountry']);
//            pr($pCountries);
//            pr($deliveryText);
            $shipsFromTag = $this->localeConfig['offersPage']['shipsFromTag'] ?? '/Ships from ([A-Za-z\-, ]+)/';
            $check = Helper::getCountryDeliveryCheck($shipsFromTag, $deliveryText, $pCountries);
            if (!$check) {
                $this->addMessage('skipping merchant ' . $data['merchantId'] . ' due to country delivery options');
                $data['stock'] = 0;
            }

        }

        return $data;
    }

    private function getPrimeOffers($browser, $offers = [])
    {
        // if offers here, the primeTag for offers is present, i.e. all offers are prime,
        // else - get offers page with primeTag.

        if ($offers && !$this->primeOffers) {
            $this->addMessage('prime offers is set by default');
            $this->primeOffers = $offers;
        }
        if ($this->primeOffers === null) {
            $offerUrl = $this->compaundOfferUrl();
            $timeStart = time();
            $content = $browser->setTag($this->asin)
                ->setGroup('amzn-offer')
                ->getAdvancedPage($offerUrl)
                ->getContent();
            $delta = time() - $timeStart;
            $this->addMessage('Got prime offer page in ' . $delta . ' seconds');
            if ($browser->code === 200) {
                $offerParser = new ProductOffer($this->logger, $this->asin);
                // parse offers from the html page
                $offers = $offerParser->parse($content, $this->localeConfig, $this->locale);
                $this->primeOffers = $offers;
//                $this->appendMessagesFromObject($offerParser, 1);
                $this->addMessage('got prime offers: ' . count($offers));
            }
        } else {
            $this->addMessage('prime offers already scraped');
        }
        $merchants = [];
        if ($this->primeOffers) {
            foreach ($offers as $offer) {
                $merchants[$offer['merchantId']] = $offer['merchantId'];
            }
        }
        return $merchants;
    }

    public function getProductDataWithoutOffers()
    {
        $productDetails = new ProductDetails($this->logger, $this->asin);
        $productUrl = $this->compaundProductUrl($this->localeConfig['settings']['amazonMerchantId']);
        $browser = $this->getBrowser($productUrl);
        $browser->setTag($this->asin)->setGroup('amzn-product-page');
        if ($this->config->getProperty('DebugMode') == 2 && $content = $this->getFile('product')) {
            $browser->code = 200;
        } else {
            $content = $browser->getAdvancedPage($productUrl)->getContent();
            $this->saveFile($content);

        }

        $this->content = $content;
        $browserData[] = $browser->getProperty('ResultHeader');
        $browserData[] = $browser->getProperty('CurlInfo');

        $details = $productDetails->parse($content, $this->localeConfig);
        //try_smid_to_m_tag
        $trySwitchTag = $this->localeConfig['productPage']['try_smid_to_m_tag'] ?? '';

        if ($trySwitchTag && !$details['stock'] && strpos($productUrl, '&smid') !== false) {
            // try alternative url
            $this->logger->add($this->asin, '&smid=>&m ' . $this->locale . ' ' . $productUrl);
            $productUrl = str_replace('&smid', '&m', $productUrl);
            $content = $browser->getAdvancedPage($productUrl)->getContent();
            $this->content = $content;
            $details = $productDetails->parse($content, $this->localeConfig);
            if ($details['stockString']) {
                $this->logger->add($this->asin, 'smid=>m stockHtml present');
            }
            if ($details['stock']) {
                $this->logger->add($this->asin, 'smid=>m positive stock');
            }
        }
        $pm = new ProductMarker($content, $this->localeConfig);
        // not always we have to check for the amazon merchant
        if ($this->config->getConfig('settings', 'checkAmazonSellerMarker')) {
            [$marker, $merchantCheck] = $pm->checkMerchant($this->localeConfig['settings']['amazonMerchantId']);
            if (!$marker) {
                $details['stock'] = 0;
                $this->addMessage('missing merchant marker');
                $details['StockString'] = 'missing merchant marker';
                $details['prime'] = false;

            } elseif (!$merchantCheck) {
                $details['stock'] = 0;
                $this->addMessage('wrong merchant content received');
                $details['StockString'] = 'wrong merchant content received';
                $details['prime'] = false;
            } else {
                $details['prime'] = true;
            }
        }
        $amazonDetails = $this->getAmazonDetails();


        $skipped = Amazon\Seller::getBlockedSellerId($this->asin, $this->locale, $this->proxy->getDb());
        $cond1 = isset($skipped['skipAll']);
        $cond2 = $this->localeConfig['settings']['amazonMerchantId']
            && in_array($this->localeConfig['settings']['amazonMerchantId'], $skipped);

        if ($cond1 || $cond2) {
            // product has to be blacklisted;
            $this->setSyncable(ProductSyncable::SYNCABLE_BLACKLISTED);
            $this->syncMessage = $cond1 ? 'asin blacklisted' : 'seller blacklisted';
            $details['stock'] = 0;
        }

        $data = [
            'offerUrl' => $this->compaundOfferUrl(),
            'productUrl' => $productUrl,
            'sync_log' => $this->getStringErrorMessages(),
            'curl_code' => $browser->code,
        ];
        $data = array_merge($data, $details, $amazonDetails);
        $data = $this->calculateStockAndDelivery($data);
        if (($cookieFile = $browser->getProperty('CookieFile')) && file_exists($cookieFile)) {
            @unlink($cookieFile);
        }
        $addImportFee = $this->config->getProfileSetting('addImportFee');
        $data = self::definePriceFromData($data, $addImportFee);

        return $data;
    }

    /**
     * we can get price from offer page and product page, and sometimes product page is not related to the selected offer, therefore prices might be different, also in this case - the stock can not be defined.
     * @param $data array
     * @param bool|null $addImportFee
     * @return array
     */
    public static function definePriceFromData($data, $addImportFee = null): array
    {
        // we may not get the correct price from the product page
        // offer page usually has a stable price nonbind to the proxy location
        // import fee is not shown on the offers page, i.e. has to be combined with the product page price

        $importFee = $addImportFee ? ($data['product_page_import_fee'] ?? 0) : 0;

        if (isset($data['product_page_price'], $data['offer_page_price'])
            && $data['product_page_price'] !== $data['offer_page_price'] && !$importFee) {
            // offer price has a higher prio in case if importFee is missing
            $data['price'] = $data['offer_page_price'];
            if (!$data['stock']) {
                $data['stock'] = 1;
            }
        } elseif (isset($data['product_page_price'])) {

            $data['price'] = $data['product_page_price'] + $importFee;

        } elseif (isset($data['offer_page_price'])) {

            $data['price'] = $data['offer_page_price'];
//            $data['stock'] = 1;
        }
        return $data;
    }

    /**
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    private function checkSyncStatus($data)
    {
        $syncStage = $this->getProperty('syncable');
        $conf = new Config($this->proxy->getDb());
        switch ($syncStage) {
            case ProductSyncable::SYNCABLE_PREFOUND:
                // the product were just found, need to check for variations and apply filters
                $crawl = new Crawl($conf, $this->locale);
                $parent = $crawl->getParentAndVariations($this->content);
                if ($parent) {
                    $data['parent_asin'] = $parent;
                    $variations = $crawl->variations;
                    if (count($variations)) {
                        $this->insertVariations($variations, $parent);
                    }
                    if (isset($variations[$this->asin])) {
                        $data['variation_attributes'] = Crawl::getStringVariationAttributes($variations[$this->asin]);
                    }
                }
                if (isset($data['title']) && $data['title']) {
                    // if proxy has failed to obtain content - variation extraction will fail,
                    // therefore, we keep the syncable status for the next sync
                    $data['syncable'] = ProductSyncable::SYNCABLE_PRESYNCED;
                }
            case ProductSyncable::SYNCABLE_PRESYNCED:
                // need to apply filters
                // let the user define if the product is ok
                //$data['syncable'] = ProductSyncable::SYNCABLE_YES;
            default:
                // do nothing here
        }
        return $data;

    }

    private function insertVariations($list, $parent)
    {
        if (!is_array($list) || !count($list)) {
            return;
        }
        // remove current asin from list;

        // check which products does not exist and add them
        foreach ($list as $asin => $attributes) {
            $small = new Product($this->config, $this->proxy, $this->userAgent, $asin, $this->locale);
            if (!$small->checkExist($asin, $this->locale)) {
                $small->add($asin, $this->locale, [
                    'syncable' => ProductSyncable::SYNCABLE_PRESYNCED,
                    'parent_asin' => $parent,
                    'variation_attributes' => Crawl::getStringVariationAttributes($attributes),
                ]);
            } else {
                $small->asin = $asin;
                $small->locale = $this->locale;
                $small->update([
                    'syncable' => ProductSyncable::SYNCABLE_PRESYNCED,
                    'parent_asin' => $parent,
                    'variation_attributes' => Crawl::getStringVariationAttributes($attributes),
                ]);
            }

        }


        return;
    }

    /**
     * Converting data from html or from amazon apai etc of weight and sizes to kg and cm
     * @param $data
     * @return array
     */
    public function getDimensions($data): array
    {
        // '1,2 Kg' or '200 g'
        // a:3:{s:6:"weight";s:5:"159 g";s:15:"shipping_weight";s:40:"159 g (View shipping rates and policies)";s:9:"dimension";s:20:"39.4 x 30.5 x 5.7 cm; 130 Grams";}
        // possible weight g, Kg, ounces, ounce, pounds, Grams
        // possible size cm inches
        $dimensionsFromHtml = isset($data['dimension_data']) ? unserialize($data['dimension_data']) : [];

        $amRoduct = new Amazon\Product($this->localeConfig, $this->proxy->getDb());
        // package_dimensions = 'w:12.9133858136; h:3.7795275552; l:17.3228346280; wei:2.5573622392'
        // measured in inches and pounds.
        $columns = ['item_dimensions', 'package_dimensions'];

        $amazonDetails = $amRoduct->loadProductFromDbWithoutEmptyFields($this->asin, $this->locale, $columns);
        $result = [];
        $resultItemsDim = [];
        if (isset($amazonDetails['item_dimensions'])) {
            $resultItemsDim = SimpleAttribute::getDimensionFromCentric($amazonDetails['item_dimensions']);
        }
        $resultPackageDim = [];
        if (isset($amazonDetails['package_dimensions'])) {
            $resultPackageDim = SimpleAttribute::getDimensionFromCentric($amazonDetails['package_dimensions']);
        }
        $result = array_merge($resultItemsDim, $resultPackageDim);

        if (!count($result)) {
            // try to get dimensions from html
            $weight = $dimensionsFromHtml['shipping_weight'] ?? $dimensionsFromHtml['weight'] ?? null;
            $dimension = $dimensionsFromHtml['dimension'] ?? '';
            if (!$weight && strpos($dimension, ';') !== false) {
                $weight = explode(';', $dimension)[1];
            }
            if ($weight) {
                $weightConverted = SimpleAttribute::getWeightFromHtml($weight);
                if (!$weightConverted) {
                    $this->logger->add($this->asin, 'ftewei ' . $weight);
                }
                $result['shipping_weight'] = (float)$weightConverted;
            }


            if ($dimension) {
                $dimensionConverted = SimpleAttribute::getDimensionFromHtml($dimension);
                if (count($dimensionConverted)) {
                    $result = array_merge($result, $dimensionConverted);
                } else {
                    $this->logger->add($this->asin, 'ftedim ' . $dimension);
                }
            }
        }
        return $result;
    }

    public function removeEmptyDescriptiveFields($data)
    {
        $list = [
            'title',
            'description',
            'short_description',
            'sku',
            'ean',
            'upc',
            'brand',
            'manufacturer',
            'model',
            'made_by',
            'category',
            'weight',
            'dimension',
            'dimension_data',
            'images',
        ];
        foreach ($list as $key) {
            if (isset($data[$key]) && !$data[$key]) {
                unset($data[$key]);
            } elseif (isset($data[$key])) {
                $string = $data[$key];
                $string = preg_replace('|^[^a-zA-Z0-9\- ]*$|', '', $string);
                //$string = imap_utf8($string);
                //$string = utf8_decode($string);
                $data[$key] = $string;

            }
        }
        return $data;
    }

    /**
     * @return array
     */
    public function getDataForMagento(): array
    {
        $id = $this->getProperty('product_id');
        if (!$id) {
            return $this->getProperties();
        }

        $custom = new ProductCustom($this->config->getDb());
        $flagged = $custom->getFlaggedAttributes($id);
        return array_merge($this->getProperties(), $flagged);
    }

    /**
     * @return $this
     */
    private function updateSyncLog(): self
    {
        $syncLog = "messages: \n" . $this->getStringMessages("\n");
        if ($errors = $this->getStringErrorMessages("\n")) {
            $syncLog .= "\nerrors: \n" . $errors;
        }

        $this->update(['sync_log' => $syncLog]);
        $this->setProperty('sync_log', $syncLog);
        return $this;
    }

    /**
     * @param $id
     * @param bool $forceReload
     * @return Product
     */
    public function loadById($id, $forceReload = false): Product
    {
        if ($this->loaded && !$forceReload) {
            return $this;
        }
        $sql = new Sql($this->proxy->getDb());
        $select = $sql->select('product')->where(['product_id' => $id]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $data = $result->current();
        $this->loadFromArray($data);
        $this->loaded = 1;
        return $this;
    }

    public function processProducts($filter = [])
    {

        $filter['per-page'] = 200;
        $filter['page'] = 1;
        $productList = $this->getList(['filter' => $filter]);
        $perPage = 100;
        $paging = new Paging($filter['page'], $this->getProperty('TotalProducts'), $perPage);
        $totalPages = $paging->getCountPages();
        foreach ($productList as $product) {
            if (strlen($product['title']) > 20 && strpos($product['title'], ' ') === false) {
                $this->deleteList(['product_id' => $product['product_id']]);
                pr('deleting ' . $product['product_id']);
            }
        }

        for ($i = 2; $i <= $totalPages; $i++) {
            $filter['page'] = $i;
            $productList = $this->getList(['filter' => $filter]);
            foreach ($productList as $product) {
                if (strlen($product['title']) > 20 && strpos($product['title'], ' ') === false) {
                    $this->deleteList(['product_id' => $product['product_id']]);
                    pr('deleting ' . $product['product_id']);
                }
            }
        }

        pr($totalPages);
        die();
    }

    public function getList($data)
    {
        /**
         * possible params:
         * page, asin, sku, locale
         */
        $sql = new Sql($this->proxy->getDb());
        $filter = $data['filter'] ?? [];
        $where = $this->getCondition($filter);
        //$sort = isset($data['sort']) ? $data['sort'] : [];
        $allowableOrder = [
            'product_id',
            'locale',
            'asin',
            'parent_asin',
            'title',
            'price',
            'stock',
            'syncable',
            'modified',
            'updated_date',
        ];
        if ($filter['sort_column'] !== '' && in_array($filter['sort_column'], $allowableOrder)) {
            $sortType = $filter['sort_type'] === 'desc' ? ' desc' : ' asc';
            $order = $filter['sort_column'] . $sortType;
        } else {
            $order = 'modified DESC';
        }
        $select = $sql->select('product')
            ->where($where)
            ->columns(['count' => new Expression('COUNT(*)')]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $num = $stmt->execute();
        if ($data = $num->current()) {
            $this->setProperty('TotalProducts', $data['count']);
        }

        $perPage = $filter['per-page'] ?: $this->moduleConfig['settings']['productPerPage'];

        $select = $sql->select('product')
            ->where($where)
            ->order($order);
        if (!isset($filter['no-limit'])) {
            $select->limit((int)$perPage);
            $offset = $filter['page'] > 1 ? ((int)$filter['page'] - 1) * $perPage : 0;
            if ($offset) {
                $select->offset($offset);
            }
        }

        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        if ($result->current()) {
            while ($item = $result->current()) {
                if ($item['variation_attributes']) {
                    $item['variation_attributes_data'] = Crawl::getVariationAttributesFromString($item['variation_attributes']);
                }
                $list[] = $item;
                $result->next();
            }
        }
        return $list;
    }

    /**
     * @param $where
     * @return Product
     */
    public function deleteList($where): Product
    {
        $sql = new Sql($this->proxy->getDb());

        $config = new Config($this->proxy->getDb());
        $storeList = ProductToStore::getStoreIdsByProductList($config->getDb(), $where,
            ['enable' => 1, 'delete_trigger' => 1]);
        if (count($storeList)) {
            foreach ($storeList as $storeId => $productIds) {
                $connector = new Connector($config, $storeId);
                if ($connector->isConnected() && count($productIds)) {
                    $connector->addRequestToQueue(Request::RequestDelete, ['list' => $productIds]);
                }

            }
        }
        $delete = $sql->delete('product')
            ->where($where);
        $stmt = $sql->prepareStatementForSqlObject($delete);
        $stmt->execute();
        ProductToStore::removeAssociation($config->getDb(), $where);
        return $this;

    }


}
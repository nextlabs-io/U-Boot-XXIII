<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.07.2020
 * Time: 20:26
 */

namespace Comparator\Model\Comparator;

use BestBuy\Model\BestBuy\KeepaAPI;
use BestBuy\Model\BestBuy\ProductKeepa;
use http\Exception\RuntimeException;
use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Html\Tag;
use yii\db\Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;

/**
 * Class Product products from comparator.ca
 * @package Comparator\Model\Comparator
 *
 *
 */
class Product extends DefaultTablePage
{
    public const CMP_STATUS_SCRAPED_CD = 1;
    public const CMP_NO_UPC_FOUND = 2;
    public const CMP_GOT_ASIN_FROM_AMAZON = 3;
    public const CMP_NOT_ASIN_FOUND_IN_AMAZON = 4;
    public const CMP_FAILED_TO_EXTRACT_JSON = 5;
    public $comparatorConfig;
    public $cdColumns = ['ean', 'price', 'shipping_price', 'stock', 'amazon_check', 'keepa_check', 'cdiscount_check', 'price_html', 'stock_html', 'amazon_product_id'];


    public function __construct(Config $globalConfig, $url = '')
    {
        $table = 'comparator_product';
        $tableKey = 'comparator_product_id';
        parent::__construct($url, $globalConfig, $table, $tableKey);
        array_push($this->fields, ...['title', 'upc', 'brand', 'url', 'description', 'specs', 'image', 'asin', 'locale', 'model', 'log'], ...$this->cdColumns);
        $this->comparatorConfig = $this->globalConfig->storeConfig['comparatorConfig'] ?? [];
    }

    public function scrapeAmazon()
    {
        $this->fixInProgressHangingItems();
        $qtyPerRun = $this->getConfig('processLimiter', 'productsQtyPerRun') ?: 10;
        $i = 0;
        $updated = [];

        while ($i++ < $qtyPerRun) {
            $where = new Where();
            $where->isNull('amazon_check');
            $where->isNotNull('locale');
            $where->nest()->isNotNull('ean')->or->isNotNull('upc')->unnest();
            $product = $this->getScrapeCandidate($where);
            if ($product[$this->tableKey] ?? null) {
                $this->setStatus(self::STATUS_CURRENTLY_IN_PROGRESS, $product[$this->tableKey]);
                $updated[] = $this->checkAmazonByEAN($product);
            } else {
                return $updated;
            }
        }
        return $updated;
    }

    /**
     * @param $productData
     * @return array
     * @throws \Exception
     */
    public function checkAmazonByEAN($productData): array
    {
        $ean = $productData['ean'];
        $upc = $productData['upc'];
        $productId = $productData[$this->tableKey];
        $data = [];
        if (!$ean && !$upc) {
            $data['log'] = 'no ean/upc found';
            $data['status'] = self::STATUS_IN_PROGRESS;
            $data['amazon_check'] = 1;
            $this->update($data, [$this->tableKey => $productId]);
            return $data;
        }
        $locale = $productData['locale'];
        if (!$locale) {
            $data['log'] = 'no locale specified';
            $data['status'] = self::STATUS_IN_PROGRESS;
            $data['amazon_check'] = 1;
            $this->update($data, [$this->tableKey => $productId]);
            return $data;
        }
        $localeConfig = $this->globalConfig->getLocaleConfig($locale);
        $baseUrl = $localeConfig['settings']['baseUrl'] ?? null;
        if (!$baseUrl) {
            throw new \Exception('no base url for $locale profile');
        }
        $amazonUrl = $baseUrl . 's?k={EAN}&ref=nb_sb_noss';
        $id = $ean ?: $upc;
        $this->url = str_replace('{EAN}', $id, $amazonUrl);


        $getPageOptions = $this->getCommonBrowserOptions(1);
        $getPageOptions['content_tag'] = 'cmp_amazon_' . $id;
        $this->setGroup('comparator-amazon');
        $this->setTag($ean);
        $this->getPage('', [], [], $getPageOptions);
        $this->resetXpath();
        $conf = $this->getConfig('settings');
        $amazonItemPath = $conf['amazonItem'];
        $amazonItemClassPath = $conf['amazonItemClass'];
//        $amazonItemSponsoredFlag = $conf['amazonItemSponsoredFlag'];
        $items = [];
        $this->extractField($items, $this->content, $amazonItemPath, 'asin');
        $this->extractField($items, $this->content, $amazonItemClassPath, 'class');

        if (count($items)) {
            foreach ($items as $k => $item) {
                if (strpos($item['class'], 'AdHolder') !== false) {
                    // this is a sponsored item
                    unset($items[$k]);
                }
            }
        }
        if (count($items)) {
            $items = array_values($items);
            if (isset($items[0]['asin'])) {

                $asin = $items[0]['asin'];
                $data['asin'] = $asin;
                $data['locale'] = $locale;
                $data['status'] = self::STATUS_SUCCESS;
                $data['log'] = 'got asin from amazon';
                $data['amazon_check'] = 1;
                $amazonProductId = $this->addAmazonProduct($asin, $locale);
                if ($amazonProductId) {
                    $data['amazon_product_id'] = $amazonProductId;
                }
                $this->update($data, [$this->tableKey => $productId]);
                return $data;
            }
        }
        $data['status'] = self::STATUS_SUCCESS;
        $data['log'] = 'no asin found on amazon';

        $data['amazon_check'] = 1;
        $this->update($data, [$this->tableKey => $productId]);
        unset($data['amazon_content']);
        return $data;
    }

    public function getCommonBrowserOptions($amazon = false)
    {
        $dt = new \DateTime();
        $getPageOptions['mode'] = $this->debugMode ? 'developer' : null;
        $getPageOptions['debugMode'] = $this->debugMode;

        if ($amazon) {
            $this->proxy->resetAllowedGroups();
            $getPageOptions['ContentMarkers'] = $this->getContentMarkers($amazon);
        } else {
            $allowedProxyGroups = $this->getConfig('settings', 'allowedProxyGroups');
            if ($allowedProxyGroups) {
                $allowedProxyGroups = explode(',', $allowedProxyGroups);
                $this->proxy->setAllowedGroups($allowedProxyGroups);
                $this->proxy->loadAvailableProxy();
            }
//            $getPageOptions['UserAgentGroups'] = ['default'];
            if ($seleniumChromeBinary = $this->getConfig('settings', 'seleniumChromeBinary')) {
                $getPageOptions['seleniumChromeFlag'] = 1;
                $getPageOptions['seleniumChromeExecutable'] = 'comparator';
                $getPageOptions['seleniumChromeBinary'] = $seleniumChromeBinary;
            }

        }

        return $getPageOptions;
    }

    private function getContentMarkers($amazon = false)
    {
        return [
            ['code' => 0, 'function' => 'strlen', 'size' => '2200'],
            ['code' => 503, 'function' => 'strpos', 'pattern' => 'Something Went Wrong'],
        ];
    }

    /**
     * @param $asin
     * @param $locale
     * @return int
     */
    private function addAmazonProduct($asin, $locale): int
    {
        if ($asin && $locale) {
            $product = new \Parser\Model\Product($this->globalConfig, $this->proxy, $this->userAgent, $asin, $locale);
            $product->add($asin, $locale);
            $product->load(true, $asin, $locale);
            $productId = $product->getProperty('product_id');
            return $productId;
        }
        return 0;
    }

    public function processKeepa()
    {
        $this->fixInProgressHangingItems();
        $qtyPerRun = $this->getConfig('processLimiter', 'productsQtyPerRun') ?: 10;
        $i = 0;
        $updated = [];

        while ($i++ < $qtyPerRun) {
            $where = new Where();
            $where->isNull('keepa_check');
            $where->isNotNull('locale');
            $where->nest()->isNotNull('ean')->or->isNotNull('upc')->unnest();
            $product = $this->getScrapeCandidate($where);
            if ($product[$this->tableKey] ?? null) {
                $this->setStatus(self::STATUS_CURRENTLY_IN_PROGRESS, $product[$this->tableKey]);
                $updated[] = $this->scrapeKeepa($product);
            } else {
                return $updated;
            }
        }
        return $updated;
    }

    /**
     * @param $productData
     * @throws \Exception
     */
    public function scrapeKeepa($productData)
    {
        /* keepa by code */
        $keepa = $this->getKeepaObject();
        $code = $productData['ean'] ?: $productData['upc'];
        $tokensLeft = $keepa->tokensLeft;
        if (!$tokensLeft) {
            $key = Helper::obfuscateString($keepa->getApiKey());
            throw new \Exception('no keepa key tokens left for key' . $key);
        }
        $keepaData = $keepa->getProductsByCode($code, $productData['locale']);

        $totalResults = count($keepaData['Data']['products'] ?? []);
        $productResponse = ($keepaData['Data']['products'][0] ?? []);
        $productData['keepa_check'] = 1;
        $productData['status'] = self::STATUS_SUCCESS;
        if ($totalResults) {
            $keepaExtractedData = \BestBuy\Model\BestBuy\Product::getFieldsFromKeepaResponse($productResponse);
            $productData = array_merge($productData, $keepaExtractedData);
            $productData = $this->fillProductDataFromKeepa($productData);

            if($productData['asin'] ?? null){
                $asin = $productData['asin'];
                $amazonProductId = $this->addAmazonProduct($asin, $productData['locale']);
                if ($amazonProductId) {
                    $productData['amazon_product_id'] = $amazonProductId;
                }

            }
        }
        return $this->updateById($productData);
    }

    public function getKeepaObject(): KeepaAPI
    {
        $pk = new ProductKeepa($this->globalConfig, 'comparatorKeepaApiKey');
        $keepa = $pk->keepa;
        return $keepa;
    }

    private function fillProductDataFromKeepa(array $productData)
    {
        $listToCheck = ['title' => 'keepa_title', 'asin' => 'keepa_asin', 'brand' => 'keepa_brand', 'ean' => 'keepa_ean', 'upc' => 'keepa_upc', 'model' => 'keepa_model', 'specs' => 'keepa_features', 'image' => 'keepa_images'];
        foreach ($listToCheck as $attribute => $keepaAttribute) {
            if (!($productData[$attribute] ?? null) && ($productData[$keepaAttribute] ?? null)) {
                $productData[$attribute] = $productData[$keepaAttribute];
            }
        }
        return $productData;
    }

    public function scrapeCDiscount(array $productData)
    {
        $this->fixInProgressHangingItems();

        $productId = $productData[$this->getTableKey()];
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'cmp_product' . $productId;
        $this->url = $productData['url'];
        $this->setGroup('comparator-product');
        $this->setTag($productId);
        $this->getPage($this->url, [], [], $getPageOptions);
        $this->msg->loadErrors($this->proxy);
//        $this->limiterDelete();
        $this->resetXpath();
        pr($this->url);
        $pricePath = $this->getConfig('settings', 'price');
        $stockPath = $this->getConfig('settings', 'stock');
        $shortDescPath = $this->getConfig('settings', 'shortDescription');
        $descPathPath = $this->getConfig('settings', 'description');
        $priceHtml = $this->getResourceByXpath($this->content, $pricePath);
//        pr($priceHtml);
        foreach ($priceHtml as $item) {
            pr($item->textContent);
        }
//        pr($this->content);
        $price = $priceHtml->item(0)->textContent ?? '0';
        $stockHtml = $this->getResourceByXpath($this->content, $stockPath);

        $stock = $stockHtml->item(0)->textContent ?? '';

        $stock = $this->getStockFromString($stock);
        $shortDescHtml = $this->getResourceByXpath($this->content, $shortDescPath);
        $shortDesc = trim($shortDescHtml->item(0)->textContent ?? '');

        $descHtml = $this->getResourceByXpath($this->content, $descPathPath);
        $desc = trim($descHtml->item(0)->textContent ?? '');
        $validInterval = $this->getConfig('settings', 'productSyncDelay') ?: 3600;
        $data = [
            'price' => $price,
            'price_html' => $price,
            'stock' => $stock,
            'stock_html' => $stock,
            'short_description' => $shortDesc,
            'description' => $desc,
            'next_update_date' => new Expression('DATE_ADD(NOW(), INTERVAL ' . $validInterval . ' SECOND)'),
            'updated' => new Expression('NOW()'),
            'status' => self::STATUS_SUCCESS
        ];

        $this->update($data, [$this->getTableKey() => $productId]);
        return $data;
    }

    public function getStockFromString(string $stock)
    {
        $fullStock = 'En Stock';
        if (strpos($stock, $fullStock) !== 'false') {
            return 30;
        } else {
            return -1;
        }
    }

    public function extractPrice()
    {

    }

    public function getUrlTableFilterFields(array $filter)
    {


        $td1 =  '<span> <strong>Url/Title</strong> <br>' . Tag::html('', 'input', ['value' => $filter['title'] ?? null, 'name' => 'filter[title]', 'type' => 'text', 'class' => 'form-control padd-top',], true) . '</span>';
        $td2 =  '<span> <strong>Dropship Provider</strong> <br>' . Tag::html('', 'input', ['value' => $filter['data_source'] ?? null, 'name' => 'filter[data_source]', 'type' => 'text', 'class' => 'form-control padd-top',], true) . '</span>';
        $string = '<table><tr><td>'.$td1.'</td><td>'.$td2.'</td></tr></table>';
        return $string;
    }

    public function refresh($list)
    {
        if ($list && count($list)) {
            $where = new Where();
            $where->in($this->getTableKey(), $list);
            $refreshData = ['status' => self::STATUS_NEVER_CHECKED, 'keepa_check' => null, 'amazon_check' => null];
            $this->update($refreshData, $where);
        }
    }

    public function deleteAllProducts($filter, $withProducts = false)
    {
        $list = $this->getProductList($filter, true);
        if ($list) {
            $ids = [];
            foreach ($list as $item) {
                $ids[] = $item[$this->getTableKey()];
            }
            if ($ids) {
                $this->deleteProducts($ids, $withProducts);
            }
        }
    }

    public function getProductList($filter, $ignorePaging = false)
    {
        $select = new Select(['l' => $this->getTable()]);
        $select->columns([$this->getTableKey(), 'status', 'url', 'created', 'updated', 'asin', 'locale', 'title', 'amazon_check', 'keepa_check', 'cdiscount_check', 'ean', 'upc', 'brand', 'model', 'price', 'stock', 'image', 'keepa_image','data_source', 'minimum_qty']);
        $select->join(['p' => 'product'], 'p.product_id = l.amazon_product_id', ['amazonPrice' => 'price', 'amazonStock' => 'stock', 'amazonUrl' => 'productUrl', 'amazonImage' => 'images', 'amazonPrime' => 'prime', 'amazonShipping' => 'shippingPrice'], Join::JOIN_LEFT);
        $where = $this->getCondition($filter);
        $select->where($where);
        $select = $this->addPaging($select, $filter, $ignorePaging);
        $rowSet = $this->selectWith($select);

        $data = [];
        while ($line = $rowSet->current()) {
            $data[] = (array)$line;
            $rowSet->next();
        }
        // getting total qty
        $this->totalResults = $this->getTotalQty();
        return $data;

    }

    public function getCondition($filter, $tablePrefix = 'l'): Where
    {
        $where = parent::getCondition($filter, $tablePrefix);

        if ($filter['search_comparator_category_id'] ?? null) {
            $field = 'comparator_category_id';
            $field = $tablePrefix ? $tablePrefix . '.' . $field : $field;
            $where->equalTo($field, $filter['search_comparator_category_id']);
        }
        if ($filter['show_asin'] ?? null) {
            $field = 'asin';
            $field = $tablePrefix ? $tablePrefix . '.' . $field : $field;
            $where->greaterThan($field, '');
        }
        if ($filter['show_amazon_product_id'] ?? null) {
            $field = 'amazon_product_id';
            $field = $tablePrefix ? $tablePrefix . '.' . $field : $field;
            $where->greaterThan($field, '');
        }
        if ($filter['show_amazon_prime'] ?? null) {
            $field = 'p.prime';
//            $field = $tablePrefix ? $tablePrefix . '.' . $field : $field;
            $where->greaterThan($field, '');
        }
        if($filter['data_source'] ?? null){
            $field = 'data_source';
            $field = $tablePrefix ? $tablePrefix . '.' . $field : $field;
            $where->like($field, '%'.$filter['data_source'].'%');
        }

        return $where;
    }

    public function deleteProducts($list, $withProducts = false)
    {
        $where = new Where();
        $where->in($this->getTableKey(), $list);
        return $this->delete($where);
    }

    /**
     * @param $filter
     */
    public function refreshAll($filter): void
    {
        $where = $this->getCondition($filter, null);
        $refreshData = ['status' => self::STATUS_NEVER_CHECKED, 'keepa_check' => null, 'amazon_check' => null];
        $this->update($refreshData, $where);
    }

    public function bindProductToAmazonProduct()
    {
        $query = 'UPDATE ' . $this->getTable() . ' cp INNER JOIN product p ON p.asin=cp.asin SET cp.amazon_product_id = p.product_id';
        $query = 'UPDATE product p INNER JOIN comparator_product cp ON p.product_id=cp.amazon_product_id SET p.comparator_category_id = cp.comparator_category_id';

    }

    public function getCategoryFilterField(array $filter)
    {
        return '<span><br>' . Tag::html('', 'input', ['value' => $filter['search_comparator_category_id'] ?? null, 'name' => 'filter[search_comparator_category_id]', 'type' => 'text', 'class' => 'from-to padd-top',], true) . '</span>';
    }

    public function prepareListFilter($filter)
    {
        // got only fields related to the model.
        $fields = [
            'page' => '1',
            'status' => '',
            'per-page' => 100,
            'title' => '',
            'show_asin' => '',
            'show_amazon_product_id' => '',
            'show_amazon_prime' => '',
            'data_source' => '',
        ];
        $filter = array_intersect_key($filter, $fields);
        $filter = array_merge($fields, $filter);
        return $filter;
    }

    public function getDescriptionFilterField(array $filter)
    {
        $options = ['value' => 1, 'name' => 'filter[show_asin]', 'type' => 'checkbox', 'class' => 'padd-top',];
        if ($filter['show_asin'] ?? null) {
            $options['checked'] = 'checked';
        }
        $string = '<br><span>with asin ' . Tag::html('', 'input', $options, true) . '</span>';

        $options = ['value' => 1, 'name' => 'filter[show_amazon_product_id]', 'type' => 'checkbox', 'class' => 'padd-top',];
        if ($filter['show_amazon_product_id'] ?? null) {
            $options['checked'] = 'checked';
        }
        $string .= '<span> | with amazon product ' . Tag::html('', 'input', $options, true) . '</span>';

        $options = ['value' => 1, 'name' => 'filter[show_amazon_prime]',
            'type' => 'checkbox', 'class' => 'padd-top',];
        if ($filter['show_amazon_prime'] ?? null) {
            $options['checked'] = 'checked';
        }
        $string .= '<span> | amazon prime ' . Tag::html('', 'input', $options, true) . '</span>';

        return $string;
    }

    public function extractItemsFromFile($tmp_name)
    {
        $fieldList = [
            'upc' => 'upc',
            'ean' => 'ean',
            'brand' => 'brand',
            'mpn' => 'mpn',
            'title' => 'title',
            'image' => 'image',
            'price' => 'price',
            'shipping_price' => 'shipping_cost',
            'minimum_qty' => 'minimum_qty',
            'data_source' => 'dropship_provider_name',
            'locale' => 'locale',
        ];
        $itemList = Helper::extractItemsFromFileWithTitleRow($tmp_name);
        $result = [];
        if (count($itemList)) {
            $itemList = Helper::filterCorrectFieldsFromCSV($itemList, $fieldList);
            foreach ($itemList as $item) {
                if (!$item['upc'] && !$item['ean']) {
                    $this->msg->addMessage('empty id for ' . implode(';', $item));
                } else {
                    if ($item['locale'] ?? null) {
                        $item['locale'] = $this->validateLocale($item['locale']);
                    }
                    $result[] = $item;
                }
            }
        }
        return $result;
    }

    /**
     * @param $locale
     * @return string
     */
    private function validateLocale($locale): string
    {
        $amRoduct = new \Parser\Model\Product($this->globalConfig, $this->proxy, $this->userAgent, '', '');
        if ($amRoduct->validateLocale($locale)) {
            return $locale;
        }
        return '';
    }

    public function processNewProductData($items = [], $eanList = '', $upcList = '', $locale = '')
    {
        $locale = $this->validateLocale($locale);
        if ($items) {
            foreach ($items as $item) {
                if (!$item['locale'] && $locale) {
                    $item['locale'] = $locale;
                }
                $this->add($item);
            }
        }
        if ($eanList) {
            $this->addEANList($eanList, $locale);
        }
        if ($upcList) {
            $this->addUPCList($upcList, $locale);
        }

    }

    public function add($data)
    {
        $ean = $data['ean'] ?? '';
        $upc = $data['upc'] ?? '';
        $locale = $data['locale'] ?? '';
        if (!$ean && !$upc) {
            $this->msg->addError('ean and/or upc cannot be empty');
            return false;
        }
        if(!$locale){
            $this->msg->addError('locale cannot be empty');
            return false;
        }
        $keys = ['locale' => $locale];
        if ($ean) {
            $keys['ean'] = $ean;
        }
        if ($upc) {
            $keys['upc'] = $upc;
        }
        $data['status'] = $data['status'] ?? self::STATUS_NEVER_CHECKED;
        $data['minimum_qty'] = $data['minimum_qty'] ?? 1;
        $data['shipping_price'] = $data['shipping_price'] ?? 0;
        $this->msg->addMessage('added/updated ' . implode(';', $keys));
        $this->insertOrUpdate($keys, $data);
        return true;
    }

    /**
     * @param string $eanList
     * @param string $locale
     */
    public function addEANList($eanList, $locale)
    {
        $list = Helper::extractRegularelySeparatedItemsFromString($eanList);
        $list = array_filter($list);
        if (count($list)) {
            foreach ($list as $ean) {
                if ($ean) {
                    $data = [];
                    $data['ean'] = $ean;
                    $data['locale'] = $locale;
                    $this->add($data);
                }
            }
        }
    }

    public function addUPCList($upcList, $locale)
    {
        $list = Helper::extractRegularelySeparatedItemsFromString($upcList);
        $list = array_filter($list);
        if (count($list)) {
            foreach ($list as $upc) {
                if ($upc) {
                    $data = [];
                    $data['upc'] = $upc;
                    $data['locale'] = $locale;
                    $this->add($data);
                }
            }
        }
    }
}
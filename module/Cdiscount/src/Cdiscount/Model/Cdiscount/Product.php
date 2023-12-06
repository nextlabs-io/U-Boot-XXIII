<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.07.2020
 * Time: 20:26
 */

namespace Cdiscount\Model\Cdiscount;

use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use Parser\Model\Html\Tag;
use yii\db\Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;

/**
 * Class Product products from cdiscount.ca
 * @package Cdiscount\Model\Cdiscount
 *
 *
 */
class Product extends DefaultTablePage
{
    public const CD_STATUS_SCRAPED_CD = 1;
    public const CD_NO_UPC_FOUND = 2;
    public const CD_GOT_ASIN_FROM_AMAZON = 3;
    public const CD_NOT_ASIN_FOUND_IN_AMAZON = 4;
    public const CD_FAILED_TO_EXTRACT_JSON = 5;
    public $cdiscountConfig;

    // note, images, name=>tittle, longdescription=>description are also bb fields.
    public $cdColumns = ['ean', 'cdiscount_category_id', 'price', 'stock', 'amazon_check', 'keepa_check', 'price_html', 'stock_html', 'amazon_product_id'];


    public function __construct(Config $globalConfig, $url = '')
    {
        $table = 'cdiscount_product';
        $tableKey = 'cdiscount_product_id';
        parent::__construct($url, $globalConfig, $table, $tableKey);
        array_push($this->fields, ...['title', 'upc', 'url', 'description', 'specs', 'images', 'asin', 'locale', 'model', 'log'], ...$this->cdColumns);
        $this->cdiscountConfig = $this->globalConfig->storeConfig['cdiscountConfig'] ?? [];
    }

    public static function getEanFromUrl($url)
    {
        // we have url like this  https://www.cdiscount.com/electromenager/aspirateurs-nettoyeurs/nouveaute-dyson-v11-absolute-extra-aspirateur-ba/f-1101410-dys5025155046470.html?idOffre=586250268
        //here dys5025155046470.html
        // ean is  5025155046470
        $data = explode('.html', $url);
        $data = $data[0];
        preg_match_all('!\d+!', $data, $matches);
        if ($matches[0] ?? null) {
            $ean = end($matches[0]);
//            pr($ean);
            if (strlen($ean) >= 11) {
                return $ean;
            }
        }
        return null;

    }

    public function addProductsFromHtml($items, $categoryId)
    {
        foreach ($items as $item) {
            if (!($item['url'] ?? null)) {
                // skipping product without url
                continue;
            }
            $message = '';
            $data = $item;
            $data['cdiscount_category_id'] = $categoryId;
            $data['locale'] = 'fr';
            $data['status'] = self::STATUS_NEVER_CHECKED;
            $data['updated'] = new Expression('NOW()');
            if ($ean = ($item['ean'] ?? null)) {
                $key = ['ean' => $ean];
                $message = 'adding by ean ' . $ean;

            } else {
                // by url
                $key = ['url' => $item['url']];
                $message = 'adding by url ' . $item['url'];
            }
            $result = $this->select($key);
            if (!$result->current()) {
                $message = 'new one ' . $message;
                $data['created'] = new Expression('NOW()');
                $this->insert($data);
            } else {
                $message = 'already exist ' . $message;
                $this->update($data, $key);
            }

            $this->msg->addMessage($message);
        }
    }

    public function scrapeAmazon()
    {
        $this->fixInProgressHangingItems();
        $qtyPerRun = $this->cdiscountConfig['productsQtyPerRun'] ?? 10;
        $i = 0;
        $updated = [];

        while ($i++ < $qtyPerRun) {
            $where = new Where();
//            $where->equalTo('status', self::STATUS_NEVER_CHECKED);
            $where->isNull('amazon_check');
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
        $productId = $productData[$this->tableKey];
        $data = [];
        if (!$ean) {
            $data['log'] = 'no ean found';
            $data['cd_status'] = self::CD_NO_UPC_FOUND;
            $data['status'] = self::STATUS_IN_PROGRESS;
            $data['amazon_check'] = 1;
            $this->update($data, [$this->tableKey => $productId]);

            return $data;
        }

        $amazonUrl = 'https://www.amazon.fr/s?k={EAN}&ref=nb_sb_noss';
        $this->url = str_replace('{EAN}', $ean, $amazonUrl);


        $getPageOptions = $this->getCommonBrowserOptions(1);
        $getPageOptions['content_tag'] = 'cd_amazon_' . $ean;
        $this->setGroup('cdiscount-amazon');
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
                $data['locale'] = 'fr';
                $data['status'] = self::STATUS_SUCCESS;
                $data['log'] = 'got asin from amazon';
                $data['cd_status'] = self::CD_GOT_ASIN_FROM_AMAZON;
                $data['amazon_check'] = 1;
                $category = new Category($this->globalConfig);
                $amazonProductId = $this->addAmazonProduct($data, $productData[$category->getTableKey()]);
                if ($amazonProductId) {
                    $data['amazon_product_id'] = $amazonProductId;
                }
                $this->update($data, [$this->tableKey => $productId]);
                return $data;
            }
        }
        $data['status'] = self::STATUS_FAILED_TO_EXTRACT_FIELDS;
        $data['log'] = 'no asin found on amazon';
        $data['cd_status'] = self::CD_NOT_ASIN_FOUND_IN_AMAZON;
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
            if ($maxRetries = $this->getConfig('proxy', 'maxRetries')) {
                $this->proxy->maxRetries = $maxRetries;
            }
            if ($maxProxyRetries = $this->getConfig('proxy', 'maxProxyRetries')) {
                $this->proxy->maxProxyRetries = $maxProxyRetries;
            }
//            $getPageOptions['UserAgentGroups'] = ['default'];
            if ($seleniumChromeBinary = $this->getConfig('settings', 'seleniumChromeBinary')) {
                $getPageOptions['seleniumChromeFlag'] = 1;
                $getPageOptions['seleniumChromeExecutable'] = 'cdiscount';
                $getPageOptions['seleniumChromeBinary'] = $seleniumChromeBinary;
            }
            if ($puppeteerChromeBinary = $this->getConfig('settings', 'puppeteerChromeBinary')) {
                $getPageOptions['puppeteerFlag'] = 1;
                $getPageOptions['puppeteerExecutableScript'] = 'cdiscount.ts';
                $getPageOptions['puppeteerBinary'] = $puppeteerChromeBinary;
                $getPageOptions['puppeteerDevice'] = $this->getConfig('settings', 'puppeteerDevice');
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
     * @param array $data
     * @param $categoryId
     * @return int
     * @throws \Exception
     */
    private function addAmazonProduct(array $data, $categoryId): int
    {
        $category = new Category($this->globalConfig);
        $asin = $data['asin'] ?? null;
        $locale = $data['locale'] ?? null;
        if ($asin && $locale) {
            $product = new \Parser\Model\Product($this->globalConfig, $this->proxy, $this->userAgent, $asin, $locale);
            $product->add($asin, $locale);
            $product->load(true, $asin, $locale);
            $productId = $product->getProperty('product_id');
            $product->update([$category->getTableKey() => $categoryId]);
            return $productId;
        }
        return 0;
    }

    public function scrapeCDiscount(array $productData)
    {
        $this->fixInProgressHangingItems();

        $productId = $productData[$this->getTableKey()];
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'cd_product' . $productId;
        $this->url = $productData['url'];
        $this->setGroup('cdiscount-product');
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
        return '<span> <strong>Url/Title</strong><br>' . Tag::html('', 'input', ['value' => $filter['title'] ?? null, 'name' => 'filter[title]', 'type' => 'text', 'class' => 'form-control padd-top',], true) . '</span>';
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
        $select->columns([$this->getTableKey(), 'cdiscount_category_id', 'status', 'url', 'created', 'updated', 'asin', 'locale', 'title', 'amazon_check', 'keepa_check', 'ean', 'price', 'stock']);
        $select->join(['p' => 'product'], 'p.product_id = l.amazon_product_id', ['amazonPrice' => 'price', 'amazonStock' => 'stock', 'amazonUrl' => 'productUrl'], Join::JOIN_LEFT);
        $select->join(['c' => 'cdiscount_category'], 'c.cdiscount_category_id = l.cdiscount_category_id', ['categoryTitle' => 'title'], Join::JOIN_LEFT);
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

        if ($filter['search_cdiscount_category_id'] ?? null) {
            $field = 'cdiscount_category_id';
            $field = $tablePrefix ? $tablePrefix . '.' . $field : $field;
            $where->equalTo($field, $filter['search_cdiscount_category_id']);
        }
        if ($filter['show_asin'] ?? null) {
            $field = 'asin';
            $field = $tablePrefix ? $tablePrefix . '.' . $field : $field;
            $where->greaterThan($field, '');
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
        $query = 'UPDATE product p INNER JOIN cdiscount_product cp ON p.product_id=cp.amazon_product_id SET p.cdiscount_category_id = cp.cdiscount_category_id';

    }

    public function getCategoryFilterField(array $filter)
    {
        return '<span><br>' . Tag::html('', 'input', ['value' => $filter['search_cdiscount_category_id'] ?? null, 'name' => 'filter[search_cdiscount_category_id]', 'type' => 'text', 'class' => 'from-to padd-top',], true) . '</span>';
    }

    public function prepareListFilter($filter)
    {
        // got only fields related to the model.
        $fields = [
            'page' => '1',
            'status' => '',
            'per-page' => 100,
            'title' => '',
            'search_cdiscount_category_id' => '',
            'show_asin' => '',
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
        return '<span><br>with asin ' . Tag::html('', 'input', $options, true) . '</span>';
    }
}
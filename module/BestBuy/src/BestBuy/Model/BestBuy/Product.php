<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.07.2020
 * Time: 20:26
 */

namespace BestBuy\Model\BestBuy;

use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use yii\db\Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;

/**
 * Class Product products from bestbuy.ca
 * @package BestBuy\Model\BestBuy
 *
 *
 */
class Product extends DefaultTablePage
{
    public const BB_STATUS_SCRAPED_BB = 1;
    public const BB_NO_UPC_FOUND = 2;
    public const BB_GOT_ASIN_FROM_AMAZON = 3;
    public const BB_NOT_ASIN_FOUND_IN_AMAZON = 4;
    public const BB_FAILED_TO_EXTRACT_JSON = 5;
    public $bestBuyConfig;

    // note, images, name=>tittle, longdescription=>description are also bb fields.
    public $bbColumns = [
        'customerRatingCount', 'isMarketplace', 'primaryParentCategoryId', 'seller_id',
        'ehf', 'customerRating', 'sku', 'priceWithoutEhf', 'priceWithEhf', 'brandName',
        'modelNumber', 'productImage', 'seller_name', 'seller_rating_reviewsCount', 'seller_rating_score',
        'shortDescription',
        'seoText', 'altLangSeoText', 'seller_description',
    ];
    public $keepaColumns = [
        'keepa_asin',
        'keepa_brand',
        'keepa_product_group',
        'keepa_category',
        'keepa_manufacturer',
        'keepa_model',
        'keepa_local',
        'keepa_ean',
        'keepa_upc',
        'keepa_mpn',
        'keepa_part_number',
        'keepa_label',
        'keepa_type',
        'keepa_rootCategory',
        'keepa_publisher',
        'keepa_description',
        'keepa_title',
        'keepa_features',
        'keepa_image',
        'keepa_data',
        'keepa_check'
    ];


    public function __construct($url, Config $globalConfig)
    {
        $table = 'product_best_buy';
        $tableKey = 'product_best_buy_id';
        parent::__construct($url, $globalConfig, $table, $tableKey);
        array_push($this->fields, ...['bb_category', 'bb_product', 'title', 'upc', 'url', 'description', 'specs', 'images', 'asin', 'locale', 'model', 'log'], ...$this->bbColumns, ...$this->keepaColumns);
        $this->bestBuyConfig = $this->globalConfig->storeConfig['bestBuyConfig'] ?? [];
    }

    /**
     * @param array $list
     */
    public function addList(array $list): void
    {
        foreach ($list as $item) {
            if ($bbProduct = ($item['bb_product'] ?? null)) {
                $result = $this->select(['bb_product' => $bbProduct]);
                if ($exist = $result->current()) {
                    // product is already there.
                    if ($exist['asin']) {
                        // we have an asin - no need to do anything. TODO finalize code
                    }
                }
                $this->insertOrUpdate(['bb_product' => $bbProduct], $item);
            }
        }
    }

    /**
     * @return array|null
     * @throws \Exception
     */
    public function scrape(): ?array
    {
        $this->fixInProgressHangingItems();
        $qtyPerRun = $this->bestBuyConfig['productsQtyPerRun'] ?? 10;
        $i = 0;
        $updated = [];

        while ($i++ < $qtyPerRun) {
//            $this->setStatus(self::STATUS_NEVER_CHECKED, 7);
            $product = $this->getScrapeCandidate(['status' => self::STATUS_NEVER_CHECKED]);
            if ($product[$this->tableKey] ?? null) {
                $this->setStatus(self::STATUS_CURRENTLY_IN_PROGRESS, $product[$this->tableKey]);
                $updated[] = $this->scrapeSingleProduct($product);
            } else {
                return $updated;
            }
        }
        return $updated;
    }

    protected function scrapeSingleProduct($productData)
    {
        // get product details.
        /** Status flow 1. never-checked
         * 2. in progress (bb data extracted)
         * 3. success (is asin found)
         * 4. not found (if asin not found)
         */
        $productId = $productData[$this->tableKey];
        $this->url = $productData['url'];
        $data = [];
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'bb_product_' . $productId;
        $this->getPage('', [], [], $getPageOptions);
        // the url may be given after redirects.
        $content = $this->content;
        $items = [];
        $this->resetXpath();
        // get products data
        $conf = $this->getConfig('settings');

        $titlePath = $conf['title'] ?? '';
        $title = $this->extractSingleField($content, $titlePath);
        $data['title'] = $title;

        $jsonPath = $conf['json'];
        $json = $this->extractSingleField($content, $jsonPath);
        $jsonData = $this->parseJsonData($json);

        //        pr($jsonData['product']['product']['specs']);

        if ($jsonData) {
            $data = array_merge($data, $this->getFieldsFromJSON($jsonData));

            $data['log'] = 'scraped bb';
            $data['bb_status'] = self::BB_STATUS_SCRAPED_BB;
            $data['status'] = self::STATUS_IN_PROGRESS;
        } else {
            $data['status'] = self::STATUS_UNKNOWN_ERROR;
            $data['bb_status'] = self::BB_FAILED_TO_EXTRACT_JSON;
            $data['log'] = 'failed to extract json';
        }
        $data['content'] = gzcompress($content);
        $this->itemUpdate($data, [$this->tableKey => $productId]);
        return ['status' => $data['status'] ?? null, 'product' => $productId];
    }


    public function getCommonBrowserOptions()
    {
        $dt = new \DateTime();
        $getPageOptions['cookie_file'] = md5($this->url) . $dt->getTimestamp();
        $getPageOptions['mode'] = $this->debugMode ? 'developer' : null;
        $getPageOptions['debugMode'] = $this->debugMode;
        return $getPageOptions;
    }

    public function parseJsonData($jsonData)
    {
        $jsonData = trim(str_replace('window.__INITIAL_STATE__ =', '', $jsonData));
        if ($jsonData) {
            $json = substr($jsonData, 0, -1);
            $jsonData = json_decode($json, 1);
            return $jsonData;
        }
        return [];
    }

    /**
     * @param array $jsonData
     * @return array
     */
    public function getFieldsFromJSON(array $jsonData): array
    {
        $data = [];
        $product = $jsonData['product']['product'] ?? [];
        $linearJsonData = $this->getLinearJSON($product);
        foreach ($this->bbColumns as $column) {
            $data[$column] = $linearJsonData[$column] ?? '';
        }
        $upcList = $product['upcs'] ?? [];
        if (count($upcList)) {
            $upc = array_shift($upcList);
            if (is_string($upc)) {
                $data['upc'] = $upc;
            }
        }

        $images = $product['additionalImages'] ?? [];
        $data['images'] = implode('|', $images);
        $specs = $product['specs'] ?? [];
        $data['specs'] = serialize($specs);
        $data['description'] = $product['longDescription'] ?? '';

        $categories = $jsonData['product']['category']['categoryBreadcrumb'] ?? [];
        if ($categories && count($categories)) {
            $categoryData = $this->getCategoryFields($categories);
            if ($categoryData) {
                $data = array_merge($data, $categoryData);
            }
        }

        return $data;
    }

    protected function getLinearJSON($jsonData, $arrayKey = [])
    {
        $linearArray = [];
        foreach ($jsonData as $key => $field) {
            $levelArrayKey = $arrayKey;
            $levelArrayKey[$key] = $key;
            if (is_array($field)) {
                $linearFields = $this->getLinearJSON($field, $levelArrayKey);
                $linearArray = array_merge($linearArray, $linearFields);
            } else {
                $linearArray[implode('_', $levelArrayKey)] = $field;
            }
        }
        return $linearArray;
    }

    public function scrapeAmazon()
    {
        $this->fixInProgressHangingItems();
        $qtyPerRun = $this->bestBuyConfig['productsQtyPerRun'] ?? 10;
        $i = 0;
        $updated = [];

        while ($i++ < $qtyPerRun) {
            $product = $this->getScrapeCandidate(['status' => self::STATUS_IN_PROGRESS, 'bb_status' => self::BB_STATUS_SCRAPED_BB]);
            if ($product[$this->tableKey] ?? null) {
                $this->setStatus(self::STATUS_CURRENTLY_IN_PROGRESS, $product[$this->tableKey]);
                $updated[] = $this->checkAmazonByUPC($product);
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
    public function checkAmazonByUPC($productData): array
    {
        $upc = $productData['upc'];
        $productId = $productData[$this->tableKey];
        $data = [];
        if (!$upc) {
            $data['log'] = 'no upc found';
            $data['bb_status'] = self::BB_NO_UPC_FOUND;
            $data['status'] = self::STATUS_IN_PROGRESS;
            $data['amazon_check'] = 1;
            $this->update($data, [$this->tableKey => $productId]);
            return $data;
        }

        $amazonUrl = 'https://www.amazon.ca/s?k={UPC}&ref=nb_sb_noss';
        $this->url = str_replace('{UPC}', $upc, $amazonUrl);


        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'bb_amazon' . $upc;
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
                $data['locale'] = 'ca';
                $data['status'] = self::STATUS_SUCCESS;
                $data['log'] = 'got asin from amazon';
                $data['bb_status'] = self::BB_GOT_ASIN_FROM_AMAZON;
                $data['amazon_check'] = 1;
                $this->update($data, [$this->tableKey => $productId]);
                return $data;
            }
        }
        $data['status'] = self::STATUS_FAILED_TO_EXTRACT_FIELDS;
        $data['log'] = 'no asin found on amazon';
        $data['bb_status'] = self::BB_NOT_ASIN_FOUND_IN_AMAZON;
        $data['amazon_content'] = gzcompress($this->content);
        $data['amazon_check'] = 1;
        $this->update($data, [$this->tableKey => $productId]);
        unset($data['amazon_content']);
        return $data;
    }

    public function processFieldChange($tFrom = 5, $tFail = 2, $tTo = 100, $qty = 100): void
    {
        // changing upc
        for ($i = 0; $i < $qty; $i++) {

            $data = $this->getTechnicalProduct($tFrom);

            $productId = $this->getIdFromArray($data);
            if (!$productId) {
                continue;
            }
            pr($productId);
            $content = gzuncompress($data['content']);
            $this->resetXpath();
            $conf = $this->getConfig('settings');
            $jsonPath = $conf['json'];
            $json = $this->extractSingleField($content, $jsonPath);
            $jsonData = $this->parseJsonData($json);
            $data = [];
            $data['technical'] = $tFail;

//            if ($jsonData) {
////                pr($this->getFieldsFromJSON($jsonData));die();
//                $data = array_merge($data, $this->getFieldsFromJSON($jsonData));
//
//                $images = $jsonData['product']['product']['additionalImages'] ?? [];
//                $data['images'] = implode('|', $images);
//                $data['technical'] = $tTo;
//            }

            if ($jsonData) {
                // extracting categories
                $categories = $jsonData['product']['category']['categoryBreadcrumb'] ?? [];
                if ($categories && count($categories)) {
                    $categoryData = $this->getCategoryFields($categories);
                    if ($categoryData) {
                        pr($categoryData);
                        $data = $categoryData;
                        $data['technical'] = $tTo;
                    }
                }
            }

//            if ($jsonData) {
//                $upcList = $jsonData['product']['product']['upcs'] ?? [];
//                if (count($upcList)) {
//                    $upc = array_shift($upcList);
//                    pr($upc);
//                    if (is_string($upc)) {
//                        $data['upc'] = $upc;
//                        $data['technical'] = $tTo;
//                    }
//                }
//                $images = $jsonData['product']['product']['additionalImages'] ?? [];
//                $data['images'] = implode(' ', $images);
//            }
            $this->update($data, [$this->tableKey => $productId]);
        }
    }

    public function getTechnicalProduct($technicalFrom)
    {
        $select = new Select($this->getTable());
        $select->where(['technical' => $technicalFrom]);
        $select->limit(1);
        $rowset = $this->selectWith($select);
        if ($data = $rowset->current()) {
            $productId = $this->getIdFromArray($data);
            $this->update(['technical' => -10], [$this->tableKey => $productId]);
        }
        return $rowset->current();
    }

    public function processKeepaFieldChange($tFrom = 5, $tFail = 2, $tTo = 100, $qty = 100): void
    {
        for ($i = 0; $i < $qty; $i++) {
            $select = new Select($this->getTable());
            $select->where(['technical' => $tFrom, 'keepa_check' => 1]);
            $select->limit(1);
            $rowset = $this->selectWith($select);
            $data = $rowset->current();
            $productId = $this->getIdFromArray($data);
            if (!$productId) {
                pr('no products found');
                break;
            }
            $keepaData = unserialize($data['keepa_data']);
            pr($productId);
            $data = [];
            if (isset($keepaData['productSearch'])) {
                // first verstion
                $totalResults = ($keepaData['productSearch']['Data']['totalResults'] ?? null);
                $productResponse = ($keepaData['productDetails']['Data']['products'][0] ?? []);
            } else {
                // second version
                $totalResults = count($keepaData['Data']['products'] ?? []);
                $productResponse = ($keepaData['Data']['products'][0] ?? []);
            }

            if ($totalResults) {
                // extract data from keepa response
                $data = self::getFieldsFromKeepaResponse($productResponse);
            }
            $data['technical'] = $tTo;
            $this->update($data, [$this->tableKey => $productId]);
        }
    }

    public static function getFieldsFromKeepaResponse(array $productResponse, $associations = [])
    {
        if (!$associations) {
            $associations = [
                'keepa_asin' => 'asin',
                'keepa_brand' => 'brand',
                'keepa_product_group' => 'productGroup',
                'keepa_category' => 'categoryTree',
                'keepa_manufacturer' => 'manufacturer',
                'keepa_model' => 'model',
                'keepa_ean' => 'eanList',
                'keepa_upc' => 'upcList',
                'keepa_mpn' => 'mpn',
                'keepa_part_number' => 'partNumber',
                'keepa_label' => 'label',
                'keepa_type' => 'type',
                'keepa_rootCategory' => 'rootCategory',
                'keepa_publisher' => 'publisher',
                'keepa_description' => 'description',
                'keepa_title' => 'title',
                'keepa_features' => 'features',
                'keepa_image' => 'imagesCSV',
                'keepa_local' => 'locale'
            ];
        }
        $data = [];
        foreach ($associations as $keepaCode => $fieldId) {
            if (isset($productResponse[$fieldId])) {
                if (is_array($productResponse[$fieldId])) {
                    if ($fieldId === 'categoryTree') {
                        $data[$keepaCode] = '';
                        $list = array_map(static function ($v) {
                            return $v['name'] ?? '';
                        }, $productResponse[$fieldId]);
                        $data[$keepaCode] = implode('|', $list);

                    } elseif ($fieldId === 'eanList' || $fieldId === 'upcList') {
                        $data[$keepaCode] = $productResponse[$fieldId][0] ?? null;
                    }
                    elseif($fieldId === 'features'){
                        $symbol = '&#8226; ';
                        $data[$keepaCode] = $symbol.= implode("\n". $symbol, $productResponse[$fieldId]);
                    }
                    else {
                        $data[$keepaCode] = implode('|', $productResponse[$fieldId]);
                    }
                } else {
                    if ($fieldId === 'imagesCSV' && $productResponse[$fieldId]) {
                        $imagePath = 'https://images-na.ssl-images-amazon.com/images/I/';
                        $data[$keepaCode] = $imagePath
                            .str_replace(',', '|' .$imagePath,$productResponse[$fieldId]);
                    } else {
                        $data[$keepaCode] = $productResponse[$fieldId];
                    }
                }
            }
        }
        return $data;
    }

    public function getJson($content)
    {
        $conf = $this->getConfig('settings');
        $jsonPath = $conf['json'];
        $json = $this->extractSingleField($content, $jsonPath);
        return $this->parseJsonData($json);
    }

    /**
     * @return bool|null
     */
    public function checkKeepaForSingleProduct(): bool
    {
        $select = new Select($this->getTable());
        $where = new Where();
//        $where->notIn('keepa_check', [1,-1,2]);
        $where->isNull('keepa_check');
        $where->isNotNull('upc');
//        $where->isNotNull('brandName');
//        $where->isNotNull('modelNumber');

        $select->where($where);
        $select->limit(1);
        $rowset = $this->selectWith($select);
        $dataToSave['keepa_check'] = -1;
        if ($data = $rowset->current()) {
            $productId = $this->getIdFromArray($data);
            $this->update(['keepa_check' => 2], [$this->tableKey => $productId]);
            /* keepa by code */
            $pk = new ProductKeepa($this->globalConfig, 'bestBuyKeepaApiKey');
            $keepaApiKey = $pk->getApiKey('bestBuyKeepaApiKey') ?: $this->getConfig('settings', 'keepaApi');
            //            $keepaApiKey = $this->getConfig('settings', 'keepaApi');
            $keepa = new KeepaAPI($this->globalConfig, $keepaApiKey);
            $keepaData = $keepa->getProductsByCode($data['upc']);

            $totalResults = count($keepaData['Data']['products'] ?? []);
            $productResponse = ($keepaData['Data']['products'][0] ?? []);
            if ($totalResults) {
                $keepaExtractedData = self::getFieldsFromKeepaResponse($productResponse);
                $dataToSave = array_merge($dataToSave, $keepaExtractedData);
            }
            $this->msg->addMessage("\r\nstarting to sync\r\n");
            $dataToSave['keepa_data'] = serialize($keepaData);
            $requestCode = $keepaData['Code'] ?? null;
            /**  if no tokens left
             * Array
             * (
             * [Code] => 429
             * [Data] => Array
             * (
             * [timestamp] => 1596206350314
             * [tokensLeft] => -7
             * [refillIn] => 2280
             * [refillRate] => 5
             * [tokenFlowReduction] => 0
             * [tokensConsumed] => 0
             * [processingTimeInMs] => 0
             * )
             *
             * )
             */
            if ($requestCode == 200) {
                // a good result
                $dataToSave['keepa_check'] = 1;
                $this->update($dataToSave, [$this->tableKey => $productId]);
                $this->msg->addMessage('scrape success, product: ' . $productId);
                if ($tokensLeft = ($keepaData['Data']['tokensLeft'] ?? '')) {
                    $this->msg->addMessage('tokens left: ' . $tokensLeft);
                }

                return true;
            }

//            $productSearchCode = $keepaData['productSearch']['Code'] ?? null;
//            if (isset($keepaData['productDetails'])) {
//                $detailsSearchCode = $keepaData['productDetails']['Code'] ?? null;
//            } else {
//                // product not found probably
//                $detailsSearchCode = 200;
//            }
//            if ($productSearchCode == 200 && $detailsSearchCode == 200) {
//                // a good result
//                $dataToSave['keepa_check'] = 1;
//                $this->update($dataToSave, [$this->tableKey => $productId]);
//                $this->msg->addMessage('scrape success, product: ' . $productId);
//                return true;
//            }
            $this->msg->addError('scrape error, productId ' . $productId);
            $this->update($dataToSave, [$this->tableKey => $productId]);
            return false;
        }
        $this->msg->addMessage('product not found for scrape');
        return true;
    }

    public function getCategoryFields(array $categories)
    {
        /**
         * Array
         * (
         * [0] => Array
         * (
         * [categoryId] => Departments
         * [isSelected] =>
         * [name] => All Categories
         * [seoText] => departments
         * )
         *
         * [1] => Array
         * (
         * [categoryId] => 20001
         * [isSelected] =>
         * [name] => Computers & Tablets
         * [seoText] => computers-tablets
         * )
         */

        if (is_array($categories) && count($categories) > 1) {
            unset($categories[0]);
            $data = ['category_tree' => '', 'category_tree_seo' => '', 'category_tree_id' => ''];
            $categoryTree = [];
            $categoryTreeSeo = [];
            $categoryId = [];
            foreach ($categories as $category) {
                $categoryTree[] = $category['name'];
                $categoryTreeSeo[] = $category['seoText'];
                $categoryId[] = $category['categoryId'];
            }
            $data = ['category_tree' => implode('>', $categoryTree),
                'category_tree_seo' => implode('>', $categoryTreeSeo),
                'category_tree_id' => implode('>', $categoryId)];
            return $data;
        }
        return [];
    }
}
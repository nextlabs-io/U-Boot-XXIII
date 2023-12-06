<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.07.2020
 * Time: 20:25
 */

namespace BestBuy\Model\BestBuy;

// class to save and process category scraping
use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class Category extends DefaultTablePage
{
    public $bestBuyConfig = [];

    /**
     * @var int $totalResults
     */
    public $totalResults;


    public function __construct($url, Config $globalConfig)
    {
        $table = 'category_best_buy';
        $tableKey = 'category_best_buy_id';
        parent::__construct($url, $globalConfig, $table, $tableKey);
        array_push($this->fields, ...['bb_category', 'title', 'page', 'last_page', 'url']);
        $this->bestBuyConfig = $this->globalConfig->storeConfig['bestBuyConfig'] ?? [];
    }

    /**
     * @param array $categoryIds
     */
    public function processList(array $categoryIds): void
    {
        $updateSuccess = [];
        $createSuccess = [];
        if (count($categoryIds)) {
            foreach ($categoryIds as $categoryId) {
                $url = $this->generateUrl($categoryId);
                if ($url) {
                    $this->add($categoryId);
                    if ($this->getLastInsertValue()) {
                        $createSuccess[] = $categoryId;
                    } else {
                        $updateSuccess[] = $categoryId;
                    }
                }
            }
        }
        if (!$createSuccess && !$updateSuccess) {
            $this->msg->addMessage('not items found');
        }
        if ($createSuccess) {
            $this->msg->addMessage(' created: ' . implode(', ', $createSuccess));

        }
        if ($updateSuccess) {
            $this->msg->addMessage(' updated: ' . implode(', ', $updateSuccess));
        }

    }

    public function generateUrl($categoryId)
    {
        if ($categoryId && strpos($categoryId, 'BB_') !== false) {
            $category = str_replace('BB_', '', $categoryId);
            return $this->bestBuyConfig['baseUrl'] . $this->bestBuyConfig['categoryTag'] . $category;
        }
        return null;
    }

    /**
     * @param $categoryId
     * @return Category
     */
    public function add($categoryId): Category
    {
        $url = $this->generateUrl($categoryId);
        $dt = new \DateTime();
        $getPageOptions['cookie_file'] = md5($this->url) . $dt->getTimestamp();
//        $getPageOptions['mode'] = $this->debugMode ? 'developer' : null;
//        $getPageOptions['mode'] = 'developer';
//        $getPageOptions['debugMode'] = $this->debugMode;
//        $getPageOptions['content_tag'] = 'category_initial_' . md5($this->url);
//        $this->getPage('', [], [], $getPageOptions);
//        $this->getPage($url);
        $status = self::STATUS_NEVER_CHECKED;
        $this->insertOrUpdate(['bb_category' => $categoryId],
            ['url' => $url, 'bb_category' => $categoryId, 'status' => $status]);
        return $this;
    }

    /**
     * @return array
     */
    public function getCategoryList($filter): array
    {
        $select = new Select(['l' => $this->getTable()]);
        $select->join(['p' => 'product_best_buy'], 'l.bb_category = p.bb_category',
            ['product_qty' => new Expression('COUNT( p.product_best_buy_id )'),
                'asin_qty' => new Expression('SUM(IF(p.asin > \'\' , 1, 0))'),
                'amazon_qty' => new Expression('SUM(p.amazon_check)'),
                'keepa_qty' => new Expression('SUM(p.keepa_check)'),
                'scraped_best_buy_qty' => new Expression('SUM(IF(p.status NOT IN(' . self::STATUS_CURRENTLY_IN_PROGRESS . ',' . self::STATUS_NEVER_CHECKED . ' ), 1, 0))'),
            ], Join::JOIN_LEFT)
            ->group('l.bb_category');

        $where = $this->getCondition($filter);
        $select->where($where);

        $page = (int)($filter['page'] ?? 1);
        if ($page) {
            $limit = $filter['per-page'] ?? 100;
            $select->limit($limit);
            $select->offset(($page - 1) * $limit);
        }
        $select->quantifier(new Expression('SQL_CALC_FOUND_ROWS'));

        $rowSet = $this->selectWith($select);
        $data = [];
        while ($line = $rowSet->current()) {
            $data[] = (array)$line;
            $rowSet->next();
        }
        $this->totalResults = $this->getTotalQty();

        return $data;
    }

    /**
     * @param array $filter
     * @param string $tablePrefix
     * @return Where
     */
    public function getCondition($filter, $tablePrefix = 'l'): Where
    {
        $where = parent::getCondition($filter);
        if($filter['bb_category'] ?? null){
                $where = $this->setWhere($filter, 'bb_category', 'like', $where, [], 'l');
        }
        return $where;
    }
    /**
     * @param null $categoryId
     * @return array|\ArrayObject|null
     * @throws \Exception
     */
    public function scrape($categoryId = null)
    {
        $this->fixInProgressHangingItems();

        if ($categoryId) {
            $categoryData = (array) $this->select(['category_best_buy_id' => $categoryId])->current();
        } else {
            $categoryData = $this->getScrapeCandidate();
        }
        if ($categoryId = ($categoryData['category_best_buy_id'] ?? null)) {
            // setting currently in progress status
            $this->setStatus(self::STATUS_CURRENTLY_IN_PROGRESS, $categoryId);

            if ((int)$categoryData['status'] === self::STATUS_NEVER_CHECKED) {
                // first time to check
                $this->scrapeNeverChecked($categoryData);
                return $categoryData;
            } else {
                // get another pages
                $categoryData = $this->scrapePages($categoryData);
                return $categoryData;

            }
        }
        return [];
    }


    /**
     * @param $categoryData
     * @throws \Exception
     */
    public function scrapeNeverChecked($categoryData)
    {
        $this->url = $categoryData['url'];
        $categoryId = $categoryData[$this->tableKey];
        $bbCategory = $categoryData['bb_category'];
        $getPageOptions = $this->getCommonBrowserOptions($bbCategory);
        $getPageOptions['content_tag'] = 'bb_category_initial_' . $categoryId;
        $this->getPage('', [], [], $getPageOptions);
        // the url may be given after redirects.
        $content = $this->content;
        if (($url = $this->browser->getFinalUrl()) && $url !== $this->url) {
            $categoryData['url'] = $url;
        }
        $titlePath = $this->getConfig('settings', 'title');
        $totalResultsPath = $this->getConfig('settings', 'totalResults');
        $this->resetXpath();
        $totalResults = $this->extractSingleField($content, $totalResultsPath);
        $totalResults = $this->parseTotalResults($totalResults);
        if ((int)$totalResults) {
            $categoryData['last_page'] = ceil($totalResults / 24);
        }
        // we can define last page based on the total results, however, this is not certain,
        $title = $this->extractSingleField($content, $titlePath);
        if ($title) {
            $categoryData['title'] = $title;
        }
        $categoryData['page'] = 0;
        if ($categoryData) {
            $categoryData['updated'] = new Expression('NOW()');
            pr('updating the category');
            pr($categoryData);
            $this->itemUpdate($categoryData, [$this->tableKey => $categoryId]);
            $categoryData = $this->scrapePages($categoryData);

        }
        return $categoryData;

//        $items = $this->getProductsFromPage($content, $categoryData);
//        if (count($items)) {
//            // the category exists and products are there, adding the products
//            $data['page'] = 1;
//
//            $this->addProductsFromHtml($items);
//            $data['status'] = self::STATUS_IN_PROGRESS;
//        } else {
//            // nothing found
//            $data['status'] = self::STATUS_NOT_FOUND;
//        }

    }

    protected function getCommonBrowserOptions($categoryTag = null)
    {
        $dt = new \DateTime();

        $getPageOptions['cookie_file'] = $categoryTag ?: md5($this->url) . $dt->getTimestamp();
        $getPageOptions['mode'] = $this->debugMode ? 'developer' : null;
        $getPageOptions['debugMode'] = $this->debugMode;
        return $getPageOptions;
    }

    /**
     * @param $string
     * @return int
     */
    public static function parseTotalResults($string): int
    {
        return (int)(str_replace([',', '.'], '', $string));
    }

    /**
     * @param array $categoryData
     * @return mixed
     * @throws \Exception
     */
    public function scrapePages($categoryData)
    {
        $currentPage = $categoryData['page'];
        $categoryId = $categoryData[$this->tableKey];
        $maxPages = $this->bestBuyConfig['pagesQtyPerRun'] ?? 10;
        $data = [];
        for ($i = 0; $i < $maxPages; $i++) {
            // take up to 10
            $currentPage++;
            $data = $this->scrapeSinglePage($categoryData, $currentPage);
            $this->itemUpdate(['page' => $currentPage], [$this->tableKey => $categoryId]);
            if ($data['status'] !== self::STATUS_IN_PROGRESS) {
                // finalize status and return
                $this->itemUpdate($data, [$this->tableKey => $categoryId]);
                return $data;
            }
        }
        $this->itemUpdate($data, [$this->tableKey => $categoryId]);
        return $data;
    }

    public function scrapeSinglePage($categoryData, $page)
    {
        $categoryId = $categoryData[$this->tableKey];
//        $pagingTag = str_replace('{page}', $page, $this->bestBuyConfig['pagingTag']);
//        $this->url = $categoryData['url'] . '?' . $pagingTag;
        pr('starting single page');
        pr($categoryData);

        $pageUrl = $this->getConfig('settings', 'jsonUrl');
        $pageUrl = str_replace('{page}', $page, $pageUrl);
        $category = str_replace('BB_', '', $categoryData['bb_category']);
        $this->url = str_replace('{category}', $category, $pageUrl);
//        pr($this->url);

        $data = [];
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'bb_category_initial_' . $categoryId . '_page_' . $page;
        $this->getPage('', [], [], $getPageOptions);
        // the url may be given after redirects.
        $content = $this->content;
        $json = json_decode($content, 1);
        if ($json) {
            // TODO add a check if the category is right
            /*
             * Array
(
    [Brand] => BestBuyCanada
    [currentPage] => 5
    [total] => 227
    [totalPages] => 10
    [pageSize] => 24
    [products] => Array*/
            if (($json['total'] ?? 0) > 500000) {
                // no category found
                $data['status'] = self::STATUS_NOT_FOUND;
                return $data;
            }
            if($totalPages = ($json['totalPages'] ?? 0)){
                $data['last_page'] = $totalPages;
                $categoryData['last_page'] = $totalPages;
            }
        }

        $items = [];
        $this->resetXpath();
        // we can define last page based on the total results, however, this is not certain,
        $items = $this->getProductsFromJson($json, $categoryData);
        $data['page'] = $page;
        if (count($items)) {
            // the category exists and products are there, adding the products
            $this->addProductsFromHtml($items);
            $data['status'] = self::STATUS_IN_PROGRESS;
        } else if(($categoryData['last_page'] ?? 0) && $page < $categoryData['last_page']){
            // just failed this page.
            $data['status'] = self::STATUS_IN_PROGRESS;
        } else {
            // nothing found, i.e. reached the limit probably
            // TODO there might be diffent reasons, need to investigate
            $data['status'] = self::STATUS_SUCCESS;
        }

        return $data;
    }

    public function getProductsFromJson($json, $categoryData)
    {
        $bbCategory = $categoryData['bb_category'];
        $items = [];
        $list = $json['products'] ?? [];
        if (count($list)) {
            $host = $this->getBestBuyConfigField('host');
            $items = array_map(static function ($v) use ($bbCategory, $host) {
                $item['title'] = $v['name'];
                $item['sku'] = $v['sku'];
                $url = $v['productUrl'];
                if (strpos($url, '.aspx') !== false) {
                    $url = explode('.aspx', $url)[0];
                }
                $item['url'] = $host . $url;
                $item['bb_category'] = $bbCategory;
                return $item;
            }, $list);

        }
        return $items;

    }

    public function getBestBuyConfigField($field)
    {
        return $this->globalConfig->storeConfig['bestBuyConfig'][$field] ?? null;
    }

    /**
     * @param array $list
     * @throws \Exception
     */
    public function addProductsFromHtml($list)
    {
        /*
         *$list = [(
            [0] => [(
                    [url] => /en-ca/product/otterbox-commuter-fitted-hard-shell-ca/13863823
                    [title] => OtterBox Commuter Fitted Hard Shell Case for iPhone Pro Max - Black
                    [price] => 59.99
         */
        // from Html means - need to parser prepend price and get bb_product
        $list = array_map(static function ($v) {
            $v['price'] = $v['price'] ? str_replace(',', '', $v['price']) : null;
            if ($v['url'] ?? null) {
                $bbProduct = Helper::getBBProductFromUrl($v['url']);
                $v['bb_product'] = $bbProduct;
                $v['status'] = Product::STATUS_NEVER_CHECKED;
            }
            return $v;
        }, $list);
        $product = new Product('', $this->globalConfig);
        $product->addList($list);

    }

    public function getProductsFromPage($content, $categoryData)
    {
        $bbCategory = $categoryData['bb_category'];
        $conf = $this->getConfig();
        $urlListPath = $conf['settings']['itemListLink'] ?? '';
        $titleListPath = $conf['settings']['itemListTitle'] ?? '';
        $priceListPath = $conf['settings']['itemListPrice'] ?? '';
        $items = [];
        $this->extractField($items, $content, $urlListPath, 'url');
        $this->extractField($items, $content, $titleListPath, 'title');
        $this->extractField($items, $content, $priceListPath, 'price');

        if (count($items)) {
            $host = $this->getBestBuyConfigField('host');
            $items = array_map(static function ($v) use ($bbCategory, $host) {
                $v['url'] = $host . $v['url'];
                $v['bb_category'] = $bbCategory;
                return $v;
            }, $items);

        }
        return $items;
    }

    /**
     * @param array $array
     * @return int
     */
    public function refresh(array $array): int
    {
        if($array && count($array)) {
            $where = new Where();
            $where->in($this->tableKey, $array);
            $status = self::STATUS_NEVER_CHECKED;
            return $this->update(
                ['page' => null, 'status' => $status], $where);
        }
        return 0;
    }
    public function refreshAll($filter)
    {
        $where = $this->getCondition($filter);
        $status = self::STATUS_NEVER_CHECKED;
        $refreshData = ['page' => null, 'status' => $status];
        $this->update($refreshData, $where);
    }

    public function prepareListFilter($filter)
    {
        // got only fields related to the model.
        $fields = [
            'page' => '1',
            'status' => '',
            'per-page' => 100,
            'title' => '',
            'bb_category' => '',
            'zero-products' => '',
        ];
        $filter = array_intersect_key($filter, $fields);
        $filter = array_merge($fields, $filter);
        return $filter;
    }

    public function deleteCategories($list, $withProducts = false)
    {

        if ($list && $withProducts) {
            $product = new Product('', $this->globalConfig);
            $query = 'delete from '. $product->getTable(). ' WHERE bb_category IN(SELECT bb_category FROM '.$this->getTable().' WHERE '.$this->tableKey .' in('.implode(',', $list).'))';
            $sql = new Sql($this->getAdapter());
            /* first delete old data */
            $stmt = $sql->getAdapter()->getDriver()->createStatement();
            $stmt->setSql($query);
            $stmt->execute();
        }
        $where = new Where();
        $where->in($this->tableKey, $list);
        return $this->delete($where);
    }

    public function deleteAllCategories($filter, $withProducts = false)
    {
        $list = $this->getCategoryList($filter);
        if ($list) {
            $ids = [];
            foreach ($list as $item) {
                $ids[] = $item[$this->tableKey];
            }
            if ($ids) {
                $this->deleteCategories($ids, $withProducts);
            }
        }
    }

}
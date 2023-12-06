<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.07.2020
 * Time: 20:25
 */


namespace Cdiscount\Model\Cdiscount;

// class to save and process category scraping

use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Html\Dropdown;
use Parser\Model\Html\Tag;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class Category extends \Parser\Model\Amazon\Category
{

    public function __construct(Config $globalConfig, $url = null)
    {
        $table = 'cdiscount_category';
        $tableKey = 'cdiscount_category_id';
        parent::__construct($globalConfig, $url, $table, $tableKey);
        $this->host = $this->getConfig('settings', 'baseUrl');
        $allowedProxyGroups = $this->getConfig('settings', 'allowedProxyGroups');
        if ($allowedProxyGroups) {
            $allowedProxyGroups = explode(',', $allowedProxyGroups);
            $this->proxy->setAllowedGroups($allowedProxyGroups);
            $this->proxy->loadAvailableProxy();
        }
    }

    /**
     * @param null $categoryId
     * @throws \Exception
     */
    public function scrape($categoryId = null): void
    {
        $this->fixInProgressHangingItems();

        if ($categoryId) {
            $categoryData = $this->select([$this->getTableKey() => $categoryId])->current();
            if ($categoryId = ($categoryData[$this->getTableKey()] ?? null)) {
                $this->setStatus(self::STATUS_NEVER_CHECKED, $categoryId);
                $categoryData['status'] = self::STATUS_NEVER_CHECKED;
            }
        } else {
            $categoryData = $this->getScrapeCandidate(['status' => self::STATUS_NEVER_CHECKED]);
            // trying to get never checked first
        }


        if ($categoryId = ($categoryData[$this->getTableKey()] ?? null)) {
            // setting currently in progress status

            $this->categoryId = $categoryId;

            if ((int)$categoryData['status'] === self::STATUS_NEVER_CHECKED) {
                if ($this->setStatus(self::STATUS_CURRENTLY_IN_PROGRESS, $categoryId)) {
                    // first time to check,
//                    $this->setStatus(self::STATUS_NEVER_CHECKED, $categoryId);
//                    $this->limiterDelete();
                    $this->scrapeNeverChecked($categoryData);
//                    $this->setStatus(self::STATUS_NEVER_CHECKED, $categoryId);
                    $this->msg->addMessage('processed never checked ' . $categoryId);
                } else {
                    // category was taken by someone else
                    $this->msg->addMessage('ups, missed never checked ' . $categoryId);
                }
            }
        }
        $cp = new CategoryPage($this->getAdapter());
        $this->scrapePages($categoryId, $cp);
    }

    /**
     * first time check, it is important here to reliably extract data from the page. changes the status upon completion
     * @param $categoryData
     *
     * @throws \Exception
     */
    public function scrapeNeverChecked($categoryData)
    {
        $this->url = $categoryData['url'];
        $categoryId = $categoryData[$this->getTableKey()];
        $this->categoryId = $categoryId;
        $data = [];
        $dt = new \DateTime();
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'cdiscount_category_initial_' . $categoryId;
        $this->setGroup('cdiscount-cat-ini');
        $this->getPage('', [], [], $getPageOptions);
        // the url may be given after redirects.

        $content = $this->prependContent(self::STATUS_FAILED_TO_EXTRACT_FIELDS);


        $this->getPaging();
        // checking navigation
        $this->getNavigationBlock($categoryData);
        $conf = $this->getConfig();
        $titlePath = $conf['settings']['title'] ?? '';
        $items = [];
        $this->resetXpath();
        // we can define last page based on the total results, however, this is not certain,
        $title = $this->extractSingleField($content, $titlePath);
        if ($title) {
            $data['title'] = $title;
        }


        $items = $this->getProductsFromPage($content, $categoryData);

        if (count($items)) {
            // the category exists and products are there, adding the products
            $data['page'] = 1;
            $data['last_page'] = count($items);
            $this->addProductsFromHtml($items, $categoryData);
            $data['status'] = self::STATUS_IN_PROGRESS;
            $this->json['pages'][1]['found'] = count($items);
        } else {
            // nothing found
            $data['status'] = self::STATUS_NOT_FOUND;
        }
        $data['json'] = serialize($this->json);
        $data['content'] = $this->content;
        $data['updated'] = new Expression('NOW()');
        $data['profile'] = $this->type;
        pr('updating the category');
//            pr($data);
//        $this->devReset();
        $this->itemUpdate($data, [$this->getTableKey() => $categoryId]);
        // adding pages to the queue table in order to scrape them with multi threads
        if ($data['status'] === self::STATUS_IN_PROGRESS && ($pages = $this->json['pages'] ?? [])) {
            $this->addPagesToQueue($pages, $this->categoryId);
        }

    }

    protected function getCommonBrowserOptions()
    {
        $dt = new \DateTime();
        // timebased cookie file, means no repeat cookie, or not?
//        $getPageOptions['cookie_file'] = md5($this->url) . $dt->getTimestamp();
//        $getPageOptions['cookie_file'] = null;
        $getPageOptions['mode'] = $this->debugMode ? 'developer' : null;
        $getPageOptions['debugMode'] = $this->debugMode;
        if ($maxRetries = $this->getConfig('proxy', 'maxRetries')) {
            $this->proxy->maxRetries = $maxRetries;
        }
        if ($maxProxyRetries = $this->getConfig('proxy', 'maxProxyRetries')) {
            $this->proxy->maxProxyRetries = $maxProxyRetries;
        }
        if ($seleniumChromeBinary = $this->getConfig('settings', 'seleniumChromeBinary')) {
            $getPageOptions['seleniumChromeFlag'] = 1;
            $getPageOptions['seleniumChromeBinary'] = $seleniumChromeBinary;
        }
//        $getPageOptions['UserAgentGroups'] = ['default'];

        return $getPageOptions;
    }

    /**
     * @param $failStatus
     * @return string
     * @throws \Exception
     */
    private function prependContent($failStatus)
    {
        $profiles = $this->getConfig('categoryProfiles');
        if (!$profiles) {
            throw new \RuntimeException('no scraping profiles for ' . $this->locale);
        }
        foreach ($profiles as $type => $profile) {
            if (strpos($this->content, $profile['profileMarker']) !== false) {
                $this->type = $type;
                $this->profile = $profile;
                break;
            }
        }
        if (!$this->type) {
            $this->setStatus($failStatus, [$this->getTableKey() => $this->categoryId]);
            throw new \RuntimeException('no profile found for ' . $this->url);
        }
        // we have now type and profile
        $startContent = $this->profile['productBeginTag'] ?? null;
        $endContent = $this->profile['productEndTag'] ?? null;
        $content = Helper::getJsonObjectFromHtml($this->content, $startContent, $endContent);
        if ($content) {
            $this->content = $content;
        }

        return $this->content;
    }

    public function getPaging()
    {
        $content = $this->content;
        $url = '';
        $nextPage = $this->profile['pagingTag'];
        $pageTag = $this->profile['pageTag'];
        $maxPage = $this->profile['maxCategoryPage'] ?? 10;
        $res = Helper::getResourceByXpath($content, $nextPage);
        $pages = [];

        if ($res) {
            // we got li nodes
            foreach ($res as $li) {
                $page = ['page' => $li->textContent];
                $link = Helper::getLinkFromNode($li);
                $page['class'] = '';
                if ($link) {
                    $page['url'] = $link->getAttribute('href');
                    $page['class'] = $link->getAttribute('class');
                    $pages[] = $page;
                }

            }
        }

        $lastPage = 0;
        $currentPage = 1;
        $sampleUrl = '';
        $samplePage = '';
        if ($pages) {
            $lastPageElem = $pages[] = array_pop($pages);
            $lastPage = $lastPageElem['page'] ?? 1;
            foreach ($pages as $page) {
                if ($page['class'] === 'current') {
                    $currentPage = $page['page'];
                }
                if (($page['url'] ?? null) && ((int)$page['page'])) {
                    $sampleUrl = $page['url'];
                    $samplePage = (int)$page['page'];
                }
            }
            if (!$lastPage) {
                $lastPage = 1;
            }
        }
        if (!$sampleUrl) {
            // no paging found at all.
            $this->msg->addError('no paging found for category ' . $this->categoryId);
        }
        $this->json = [];
        pr($sampleUrl);
        pr($samplePage);
        $this->json['sampleUrl'] = $sampleUrl ? $this->checkPageTagUrl($sampleUrl, $samplePage) : '';

        $lastPage = $lastPage > $maxPage ? $maxPage : $lastPage;

        $this->json['samplePage'] = (int)$samplePage;
        $this->json['currentPage'] = (int)$currentPage;
        $this->json['lastPage'] = $lastPage;

        $cleanPages = [];
        if ($lastPage && $sampleUrl) {
            // we can generate paging block
            for ($i = 1; $i <= $lastPage; $i++) {
                $cleanPages[$i] = ['page' => $i,
                    'url' => $this->generatePageUrl($this->json['sampleUrl'], $i, $pageTag),
                    'checked' => ($this->json['currentPage'] === $i) ? 1 : 0,
                    'found' => 0
                ];
            }
        }
        $this->json['pages'] = $cleanPages;
//        pr($this->json);
//        pr($pages);
        return $pages;

    }

    public function checkPageTagUrl($sampleUrl, $samplePage)
    {
        // somestring-page.html
        // somestring_page.html

        $pageTag1 = '_{page}.html';
        $pageTag2 = '-{page}.html';
        $pageString1 = str_replace('{page}', $samplePage, $pageTag1);
        $pageString2 = str_replace('{page}', $samplePage, $pageTag2);
//        pr($pageString1);
        if (strpos($sampleUrl, $pageString1) !== false) {
            //all good, we found what we need.

            return str_replace($pageString1, $pageTag1, $sampleUrl);
        } elseif (strpos($sampleUrl, $pageString2) !== false) {
            //all good, we found what we need.

            return str_replace($pageString2, $pageTag2, $sampleUrl);
        } elseif ((int)$samplePage === 1) {
            // we have a single page category, also ok.
            return $sampleUrl;
        } else {
            // we have an issue, throw exception for now
            throw new \Exception('no paging tag found for ' . $sampleUrl);
        }
    }

    // todo refactor

    private function generatePageUrl(string $sampleUrl, int $i, $pageTag): string
    {
        if (strpos($sampleUrl, $this->host) === false) {
            $sampleUrl = $this->host . $sampleUrl;
        }
        if ($i === 1) {
            return str_replace($pageTag, '', $sampleUrl);
        }
        return str_replace('{page}', $i, $sampleUrl);
    }

    // todo refactor


    private function getNavigationBlock($categoryData): array
    {
        // blocked for now.
        return [];
        $categoryId = $categoryData[$this->getTableKey()];
        $productFields = $categoryData['product_fields'];
        if ($productFields) {
            $productFields = unserialize($productFields);
        }
        $autoScrapeCategories = $productFields['autoScrapeCategories'] ?? null;
        if (!$autoScrapeCategories) {
            return [];
        }

        $navigationPath = $this->profile['navigationContainer'] ?? null;
        if ($navigationPath) {
            $navigationLinkPath = $navigationPath . 'a';
            $navigationSelectedPath = $navigationPath . 'span[@class=\'zg_selected\']';
            $res = Helper::getResourceByXpath($this->content, $navigationLinkPath);
            $tree = $this->getElementTreeFromXml($res);
//        pr($tree);
            $res = Helper::getResourceByXpath($this->content, $navigationSelectedPath);
            $treeSelected = $this->getElementTreeFromXml($res);
//        pr($treeSelected);
            $subCategories = [];

            if ($treeSelected) {
                $selectedLevel = $treeSelected[0]['level'];
                foreach ($tree as $item) {
                    if ($item['level'] > $selectedLevel) {
                        $this->add($item['link'], $productFields);
                        $subCategories[] = $item['link'];
                    }
                }

            }
            return $subCategories;

            pr('found subcategories');
            pr($subCategories);
        }

        return [];
    }

    /**
     * @param $categoryData
     * @return array
     * @throws \Exception
     */
    // todo refactor
    private function getElementTreeFromXml($elemList)
    {
        $tree = [];
        $parentId = $this->profile['navigationContainerId'] ?? null;
        if ($elemList && count($elemList)) {
            foreach ($elemList as $key => $elem) {
                $type = $elem->nodeName;
                $item = ['type' => $elem->nodeName];
                if ($type === 'a') {
                    $item['value'] = trim($elem->textContent);
                    $item['link'] = $elem->getAttribute('href');
                    if ($parentId) {
                        $item['level'] = $this->getLevel(0, $elem, $parentId);
                    }
                }
                if ($type === 'span') {
                    $item['value'] = trim($elem->textContent);
                    if ($parentId) {
                        $item['level'] = $this->getLevel(0, $elem, $parentId);
                    }
                }
                $tree[$key] = $item;
            }
        }
        return $tree;
    }

    // todo refactor

    private function getLevel($level, $elem, $parentId)
    {
        $level++;
        if (isset($elem->parentNode)) {
            $id = $elem->parentNode->getAttribute('id');
            if ($id != $parentId) {
                return $this->getLevel($level, $elem->parentNode, $parentId);
            }
        }
        return $level;
    }

    public function getProductsFromPage($content, $categoryData)
    {
        // need to get product title and url
        $conf = $this->getConfig();
        $containerPath = $this->profile['productContainer'] ?? '';
        $urlPath = $this->profile['productUrlPath'] ?? '';
        $titlePath = $this->profile['productTitlePath'] ?? '';
        $items = [];
        $list = [];
        if ($containerPath) {
            $res = $this->getResourceByXpath($this->content, $containerPath);
            if ($res) {
                foreach ($res as $key => $element) {
                    $node = $this->xpath->query($urlPath, $element);
                    if ($node && $node->item(0) ?? null) {
                        $url = $node->item(0)->textContent;
                        if ($url) {
                            $items[$key]['url'] = $url;
                            $items[$key]['ean'] = Product::getEanFromUrl($items[$key]['url']);
                            $titleNode = $this->xpath->query($titlePath, $element);
                            if ($titleNode && $titleNode->item(0) ?? null) {
                                $items[$key]['title'] = $titleNode->item(0)->textContent;
                            }
                        }
                    }
                }
            }
        }
        pr('found ' . count($items) . ' items');
        return $items;
    }

    public function addProductsFromHtml($items, $categoryData)
    {
        $categoryId = $categoryData[$this->getTableKey()];
        // stop if category is missing (deleted)
        if (!$this->checkExistById($categoryId)) {
            throw new \Exception('no category found, probably deleted');
        }

        $product = new Product($this->globalConfig);
        $product->addProductsFromHtml($items, $categoryId);

//        $productFields = $categoryData['product_fields'];
//        if ($productFields) {
//            $productFields = unserialize($productFields);
//        }
//        $syncStatus = $productFields['syncable'];
//        $addOptions = $productFields['addOptions'];
//        $magentoList = $productFields['magentoList'];

//        $product = new Gen($this->globalConfig, $this->proxy, $this->userAgent, "aaa", $this->locale);
//
//        $product->addMessage(count($items) . ' Found for processing');
//        $data = ['syncable' => $syncStatus ?: ProductSyncable::SYNCABLE_YES, $this->getTableKey() => $categoryId];
//        if ($addOptions) {
//            $data = array_merge($data, $addOptions);
//        }
//        $product->addNewProducts($items, $data);
//
//        // process magento store associations
//        $where = new Where();
//        $where->in('asin', $items);
//        $where->equalTo('locale', $this->locale);
//        ProductToStore::associateProducts($this->globalConfig->getDb(), $where, $magentoList);
//
//        // change sync status of existing products
//        $product->updateList($where, ['syncable' => $syncStatus]);
        $this->msg->appendMessagesFromObject($product->msg);
    }

    private function addPagesToQueue(array $cleanPages, $categoryId)
    {
        $cp = new CategoryPage($this->getAdapter());
        return $cp->addPages($cleanPages, $categoryId);
    }

    /**
     * Note, nasty for multiple usage, since updates this->categoryId, this->json etc. better to use for new object always, redundant data delivery, all required data is within the $categoryData array
     * @param array $categoryData
     * @param array $pageToProcess
     * @return mixed
     * @throws \Exception
     */
    public function scrapePageFromQueue($categoryData, $pageToProcess)
    {

        $this->url = $categoryData['url'];
        $this->categoryId = $categoryId = $categoryData[$this->getTableKey()];

        $cp = new CategoryPage($this->getAdapter());
        $qtyFound = $this->scrapeSinglePage($categoryData, $pageToProcess['url'], $pageToProcess['page']);
        $cp->update(['found' => $qtyFound], [$cp->tableKey => $pageToProcess[$cp->tableKey]]);
        $qtyLeft = $cp->getPagesQty($this->categoryId);
        if (!$qtyLeft) {
            // no pages to scrape
            $this->setStatus(self::STATUS_SUCCESS, [$this->getTableKey() => $categoryId]);
        }
        return $this;
    }

    /**
     * Simply extract single page, no savings to category object
     * @param array $categoryData
     * @param string $url
     * @param int $page
     * @return int
     * @throws \Exception
     */
    public function scrapeSinglePage($categoryData, $url, $page)
    {

        pr('taking url ' . $url);
        $data = [];
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'cdiscount_category_initial_' . $this->categoryId . '_page_' . $page;
        $this->setGroup('cdiscount-cat-ini');
        $this->setTag($this->categoryId);
        $this->getPage($url, [], [], $getPageOptions);
        // the url may be given after redirects.
        $this->prependContent(self::STATUS_IN_PROGRESS);
        $content = $this->content;

        $items = [];
        $this->resetXpath();
        // we can define last page based on the total results, however, this is not certain,
        $items = $this->getProductsFromPage($content, $categoryData);

        if (count($items)) {
            // the category exists and products are there, adding the products
            $this->addProductsFromHtml($items, $categoryData);
        }
        return count($items);

    }

    public function getAsinFromUrl($url)
    {
        if (!$url) return null;
        $url = str_replace('?', '/', $url);
        $chunks = explode('/', $url);
        if ($key = array_search('dp', $chunks)) {
            return $chunks[$key + 1] ?? null;
        }
        return null;
    }

    /**
     * @param array $pages
     * @return int
     */
    public function calculateScrapedPages($pages): int
    {
        if (!$pages) {
            return 1;
        }
        $qty = 1;
        foreach ($pages as $page) {
            if ($page['checked'] ?? null) {
                $qty++;
            }
        }
        return $qty;
    }

    public function calculateFoundProducts($pages)
    {
        if (!$pages) {
            return 1;
        }
        $qty = 0;
        foreach ($pages as $page) {
            if ($page['checked'] ?? null) {
                $qty += $page['found'];
            }
        }
        return $qty;
    }

//    /**
//     * @param $categoryId
//     * @param CategoryPage $categoryPage
//     * @return mixed
//     * @throws \Exception
//     */
//    public function scrapePages($categoryId, $categoryPage)
//    {
//        $maxPages = (int) ($this->getConfig('settings', 'pagesQtyPerRun') ?? 10);
//        // get a page to process
//        for ($i = 0; $i < $maxPages; $i++) {
//            $pageToProcess = $categoryPage->loadPageCandidate($categoryId);
//            if (!$pageToProcess) {
//                $pageToProcess = $categoryPage->loadPageCandidate();
//                if (!$pageToProcess) {
//                    //                    no pages in the queue
//                    // check if there are categories which are still in not finished state
//                    return;
//                }
//            }
//            $categoryId = $pageToProcess[$this->getTableKey()];
//            $categoryData = $this->select([$this->getTableKey() => $categoryId])->current();
//            $this->scrapePageFromQueue($categoryData, $pageToProcess);
//            $this->msg->addMessage('processed page ' . $pageToProcess['page'] . ' for category ' . $categoryId);
//        }
//    }

    public function getScrapingProfile($categoryData)
    {
        // this array contains a list of pages scraped and other info related to the category.
        // $json = ['pages' => [], 'log' => []]
        $json = $categoryData['json'] ?? '';
        $json = $json ? unserialize($json) : [];
        if (!$json) {
            $json = ['pages' => [], 'log' => ['initializing ' . time()]];
        }
        $this->json = $json;
        return $json;
    }

    public function refresh($list)
    {
        if ($list && count($list)) {
            $where = new Where();
            $where->in($this->getTableKey(), $list);
            $refreshData = ['status' => self::STATUS_NEVER_CHECKED, 'json' => null, 'page' => null, 'next_page_url' => null];
            $this->update($refreshData, $where);

            $where = new Where();
            $where->in($this->getTableKey(), $list);
            $cp = new CategoryPage($this->getAdapter());
            $cp->delete($where);
        }
    }

    public function deleteAllCategories($filter, $withProducts = false)
    {
        $list = $this->getCategoryList($filter, true);
        if ($list) {
            $ids = [];
            foreach ($list as $item) {
                $ids[] = $item[$this->getTableKey()];
            }
            if ($ids) {
                $this->deleteCategories($ids, $withProducts);
            }
        }
    }

    public function deleteCategories($list, $withProducts = false)
    {
        if ($list && $withProducts) {
            $product = new Product($this->globalConfig);
            $where = new Where();
            $where->in($this->getTableKey(), $list);
            $product->delete($where);
        }

        $where = new Where();
        $where->in($this->getTableKey(), $list);
        $cp = new CategoryPage($this->getAdapter());
        $cp->delete($where);

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
        $refreshData = ['status' => self::STATUS_NEVER_CHECKED, 'json' => null, 'page' => null, 'next_page_url' => null];
        $this->update($refreshData, $where);
        $list = $this->getCategoryList($filter, true);
        if ($list) {
            $ids = [];
            foreach ($list as $item) {
                $ids[] = $item[$this->getTableKey()];
            }
            if ($ids) {
                $where = new Where();
                $where->in($this->getTableKey(), $ids);
                $cp = new CategoryPage($this->getAdapter());
                $cp->delete($where);
            }
        }

    }

    public function getCondition($filter, $tablePrefix = 'l'): Where
    {
//        pr($filter);die();
        $where = parent::getCondition($filter, $tablePrefix);

        if ($filter['profile'] && $filter['profile'] != -1) {
            $where->equalTo('profile', $filter['profile']);
        }

        return $where;
    }

    public function testContent($url)
    {
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'cdiscount_category_initial_test_url';
//        $proxyDataArray = $this->proxy->loadProxyByIpPort('127.0.0.1', '9050');
//        $this->proxy->loadFromArray($proxyDataArray);
        $this->getPage($url, [], [], $getPageOptions);
        // the url may be given after redirects.
        $content = $this->content;
        Helper::deleteContentFileByPath($getPageOptions['content_tag']);
//        $content = $this->prependContent();
        return $content;
    }

    public function getListFilter($filterKey, $requestData, $resetFilterFlag = false)
    {
        $requestData['zero-products'] = $requestData['zero-products'] ?? '';
        return parent::getListFilter($filterKey, $requestData, $resetFilterFlag);
    }

    public function getPagesStats($pages)
    {
        $data = ['checked' => 0, 'found' => 0, 'notChecked' => 0, 'total' => 0];
        if (!$pages) {
            return $data;
        }
        $data['total'] = count($pages);
        foreach ($pages as $page) {
            if ($page['checked'] ?? null) {
                $data['checked']++;
                $data['found'] += $page['found'] ?? 0;
            } else {
                $data['notChecked']++;
            }
        }
        return $data;
    }

    public function getPossibleProfiles($filter)
    {
        $data = $this->getPossibleFieldValues($filter, 'profile');
        return implode(',', $data);
    }

    public function getPossibleFieldValues($filter, $field)
    {
        $select = new Select(['l' => $this->getTable()]);
        $select->columns([$field]);
        $select->group($field);
        $where = $this->getCondition($filter);
//        $select->where($where);
        $rowSet = $this->selectWith($select);
        $data = [];
        while ($line = $rowSet->current()) {
            if ($line[$field] ?? null) {
                $data[] = $line[$field];
            }
            $rowSet->next();
        }
        return $data;
    }

    public function processRoutines()
    {
        $filter = ['page' => 1, 'per-page' => 500];
        $filter = $this->prepareListFilter($filter);
//        pr($filter);
        $list = $this->getCategoryList($filter);
        $this->applyRoutines($list);
        $totalResults = $this->totalResults;
        if (($totalResults / 500) > 1) {
            $maxPage = (int)$totalResults / 500;
            for ($i = 2; $i <= $totalResults; $i++) {
                $filter['page'] = $i;
                $list = $this->getCategoryList($filter);
                $this->applyRoutines($list);
            }
        }
    }

    public function prepareListFilter($filter)
    {
        // got only fields related to the model.
        $fields = [
            'page' => '1',
            'status' => '',
            'per-page' => 100,
            'title' => '',
            'zero-products' => '',
            'profile' => '',
            'marketplace_category' => '',
            'web_hierarchy_location_codes' => ''
        ];
        $filter = array_intersect_key($filter, $fields);
        $filter = array_merge($fields, $filter);
        return $filter;
    }

    public function applyRoutines($list)
    {
        foreach ($list as $item) {
            $data = $this->getProductOptionsForCategory($item);
            $unique = [$this->getTableKey() => $data[$this->getTableKey()]];
            unset($data[$this->getTableKey()]);
            $data = $this->processData($data);
            $this->update($data, $unique);
        }
    }

    public function getUrlTableFilterFields(array $filter)
    {
        return '<span> <strong>Url/Title</strong><br />' . Tag::html('', 'input', ['value' => $filter['title'] ?? null, 'name' => 'filter[title]', 'type' => 'text', 'class' => 'col-lg-12 form-control padd-top',], true) . '</span>';
    }

    public function getSelectDropDown($selected, $filter, $field)
    {
        $list = $this->getPossibleFieldValues($filter, $field);
        $data = [-1 => ''];
        if (count($list)) {
            foreach ($list as $value) {
                $data[$value] = $value;
            }
        }
        return Dropdown::getHtml($data, $selected,
            [
                'name' => 'filter[' . $field . ']',
                'aria-controls' => 'datatable-responsive',
                'class' => 'col-lg-12 form-control padd-top',
                'id' => 'filter-' . $field,
            ], ['no-default-value' => 1]);
    }

    /**
     * @param array $newCategoryList
     * @param array $categoryData
     * @return int
     */
    private function addChildCategories($newCategoryList, $categoryData): int
    {
        $parentId = $categoryData[$this->getTableKey()];
        if (!$this->checkExistById($parentId)) {
            throw new \Exception('no parent category found');
        }
        $productFields = $categoryData['product_fields'];
        if ($productFields) {
            $productFields = unserialize($productFields);
        } else {
            $productFields = [];
        }
        //$productFields['addOptions']['phone_compatibility'];
        $i = 0;
        if ($newCategoryList) {
            foreach ($newCategoryList as $newCategory) {
                if (isset($newCategory['resultLink']) && $newCategory['resultLink']) {
                    $url = $newCategory['resultLink'];
                    $dataToAdd = $productFields;
                    $dataToAdd['addOptions']['phone_compatibility'] = $newCategory['title'];
                    $this->add($url, $dataToAdd, $parentId);
                    $i++;
                }
            }
        }
        return $i;
    }

    private function devReset($dieMess = null)
    {
        pr($this->categoryId);
        $this->setStatus(self::STATUS_NEVER_CHECKED, $this->categoryId);
        $this->limiterDelete();
        die($dieMess);
    }


}
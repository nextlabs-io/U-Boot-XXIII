<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.07.2020
 * Time: 20:25
 */


namespace Parser\Model\Amazon;


// class to save and process category scraping
use Parser\Model\Amazon\Category\CategoryFilterSelector;
use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Html\Dropdown;
use Parser\Model\Html\Tag;
use Parser\Model\Magento\ProductToStore;
use Parser\Model\Product as Gen;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class Category extends DefaultTablePage
{
    public $locale;
    public $localeConfig;


    /**
     * @var string
     */
    public $host;
    public $json;
    public $type;
    public $profile;
    public $categoryId;


    public function __construct(Config $globalConfig, $url = null, $table = null, $tableKey = null)
    {
        $table = $table ?: 'amazon_category';
        $tableKey = $tableKey ?: 'amazon_category_id';
        parent::__construct($url, $globalConfig, $table, $tableKey);
        array_push($this->fields, ...['product_fields', 'title', 'page', 'last_page', 'url', 'parent_id', 'marketplace_category', 'web_hierarchy_location_codes']);
    }

    public static function getCategoryIndex($url)
    {
        // generally. the category id is a part of the url, need to get a unique index
        // TODO failed to extract the index for now.

        $info = parse_url($url);
        pr($info);
        if ($query = $info['query'] ?? null) {
            pr($query);
            $chunks = explode('&', $query);
            pr($chunks);
        }
        return null;

    }

    /**
     * @param null $categoryId
     * @throws \Exception
     */
    public function scrape($categoryId = null): void
    {
        $this->fixInProgressHangingItems();
        $this->fixCompletedPagesInProgressStatus();
        if ($categoryId) {
            $categoryData = $this->select([$this->getTableKey() => $categoryId])->current();
        } else {
            $categoryData = $this->getScrapeCandidate(['status' => self::STATUS_NEVER_CHECKED]);
            // trying to get never checked first
        }
        $cp = new CategoryPage($this->getAdapter());
        if ($categoryId = ($categoryData[$this->getTableKey()] ?? null)) {
            // setting currently in progress status
            $this->categoryId = $categoryId;

            if ((int)$categoryData['status'] === self::STATUS_NEVER_CHECKED) {
                if ($this->setStatus(self::STATUS_CURRENTLY_IN_PROGRESS, $categoryId)) {
                    // first time to check,
                    $this->scrapeNeverChecked($categoryData);
                    $this->msg->addMessage('processed never checked ' . $categoryId);
                } else {
                    // category was taken by someone else
                    $this->msg->addMessage('ups, missed never checked ' . $categoryId);
                }
            }
        }
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
        $this->getConfigByUrl();
        $categoryId = $categoryData[$this->tableKey];
        $this->categoryId = $categoryId;
        $data = [];
        $dt = new \DateTime();
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'amazon_category_initial_' . $categoryId;


        $this->getPage('', [], [], $getPageOptions);
        // the url may be given after redirects.
//        $content = $this->content;
        $content = $this->prependContent(self::STATUS_FAILED_TO_EXTRACT_FIELDS);
        $conf = $this->getConfig();
        $titlePath = $conf['settings']['title'] ?? '';

        $categorySelector = new CategoryFilterSelector($content, $this->host);
        $productFields = $categoryData['product_fields'];
        if ($productFields) {
            $productFields = unserialize($productFields);
        }
        $autoScrapeCategories = $productFields['autoScrapeCategories'] ?? null;
        if (!$categoryData['parent_id'] && $categorySelector->checkMarker() && $autoScrapeCategories) {
            // we found a category with filter selector, and can create more categories; and parent category is automatically set to success without loading any products
            $newCategoryList = $categorySelector->process();
            $qtyAdded = 0;

            if ($newCategoryList) {
                $qtyAdded = $this->addChildCategories($newCategoryList, $categoryData);
            }
            $data['status'] = self::STATUS_SUCCESS;
            $data['log'] = 'found child ' . count($newCategoryList) . ' categories, added ' . $qtyAdded;
            $data['profile'] = $this->type;
            $title = $this->extractSingleField($content, $titlePath);
            if ($title) {
                $data['title'] = $title;
            }
            $this->itemUpdate($data, [$this->tableKey => $categoryId]);
            return;
        }


        $paging = $this->getPaging();

        // checking navigation
        $this->getNavigationBlock($categoryData);


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
            $pages = $this->json['pages'] ?? [];
            if(count($pages) === 1){
                $data['status'] = self::STATUS_SUCCESS;
            } else {
                $data['status'] = self::STATUS_IN_PROGRESS;
            }
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
        $this->itemUpdate($data, [$this->tableKey => $categoryId]);
        // adding pages to the queue table in order to scrape them with multi threads
        if ($data['status'] === self::STATUS_IN_PROGRESS && ($pages = $this->json['pages'] ?? [])) {
            $this->addPagesToQueue($pages, $this->categoryId);
        }

    }

    /**
     * @param null $url
     * @return $this
     * @throws \Exception
     */
    public function getConfigByUrl($url = null)
    {
        if (!$url) {
            $url = $this->url;
        }
        $urlData = parse_url($url);
        $urlDom = $urlData['scheme'] . '://' . $urlData['host'];

        $this->host = $urlDom;
        $locale = $this->globalConfig->getLocaleByUrl($url);
        $this->locale = $locale;
        $this->localeConfig = $this->globalConfig->getCrawlConfig($locale);
        return $this;
    }

    protected function getCommonBrowserOptions()
    {
        $dt = new \DateTime();
        // timebased cookie file, means no repeat cookie, or not?
//        $getPageOptions['cookie_file'] = md5($this->url) . $dt->getTimestamp();
//        $getPageOptions['cookie_file'] = null;
        $getPageOptions['mode'] = $this->debugMode ? 'developer' : null;
        $getPageOptions['debugMode'] = $this->debugMode;

//        if ($phantomBinary = $this->getConfig('settings', 'phantomBinary')) {
//            $getPageOptions['phantomFlag'] = 1;
//            $getPageOptions['phantomBinary'] = $phantomBinary;
//        }
        $getPageOptions['UserAgentGroups'] = ['default'];
        $getPageOptions['ContentMarkers'] = $this->getContentMarkers();
        if ($maxRetries = $this->getConfig('proxy', 'maxRetries')) {
            $getPageOptions['proxyMaxRetries'] = $maxRetries;
        }
        if ($maxProxyRetries = $this->getConfig('proxy', 'maxProxyRetries')) {
            $getPageOptions['proxyMaxProxyRetries'] = $maxProxyRetries;
        }
        $allowedProxyGroups = $this->getConfig('proxy', 'allowedProxyGroups');
        if ($allowedProxyGroups) {
            $allowedProxyGroups = explode(',', $allowedProxyGroups);
            $this->proxy->setAllowedGroups($allowedProxyGroups);
        }
        return $getPageOptions;
    }

    /**
     * @param $failStatus
     * @return string
     * @throws \Exception
     */
    private function prependContent($failStatus)
    {
        // TODO add mobile content handling
        $content = $this->content;
        $profiles = $this->localeConfig['categoryProfiles'];
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
            $this->setStatus($failStatus, [$this->tableKey => $this->categoryId]);
            // TODO add an easy way to explore the issue, save the content and provide a control to check the flow
            $this->registerError('cateogry_'. $this->categoryId.'_'. md5($this->url),$content, 'check_profile');
            throw new \RuntimeException('no profile found for category '. $this->categoryId. ' url ' . $this->url);
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

    /**
     * @param array $newCategoryList
     * @param array $categoryData
     * @return int
     */
    private function addChildCategories($newCategoryList, $categoryData): int
    {
        $parentId = $categoryData[$this->tableKey];
        if(!$this->checkExistById($parentId)){
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

    /**
     * @param $url
     * @param $data
     * @param string $parentId
     * @return self
     */
    public function add($url, $data, $parentId = null): self
    {
        $status = self::STATUS_NEVER_CHECKED;
        $dataToAdd = ['url' => $url, 'status' => $status, 'json' => null, 'page' => null, 'next_page_url' => null];

        if ($parentId) {
            $dataToAdd['parent_id'] = $parentId;
        }
        if ($data) {
            $dataToAdd['product_fields'] = serialize($data);
        }

        $dataToAdd = $this->getProductOptionsForCategory($dataToAdd);

        $this->insertOrUpdate(['url' => $url], $dataToAdd);
        if ($this->getLastInsertValue()) {
            $this->msg->addMessage('create success');
        } else {
            $this->msg->addMessage('update success');
        }
        return $this;
    }

    public function getProductOptionsForCategory($data)
    {
        $details = $data['product_fields'] ?? null;
        if ($details) {
            $details = unserialize($details);
            $options = $details['addOptions'] ?? [];
            $data['marketplace_category'] = $options['marketplace_category'] ?? null;
            $data['web_hierarchy_location_codes'] = $options['web_hierarchy_location_codes'] ?? null;
        }
        return $data;
    }

    public function getPaging()
    {
        $content = $this->content;
        $url = '';
        $nextPage = $this->profile['pagingTag'];
        $pageTag = $this->profile['pageTag'];
//        if (strpos($content, '<ul class="a-pagination">') !== false) {
//            $cut = explode('<ul class="a-pagination">', $content);
//            $newContent = $cut[1];
//            $newContent = explode('</ul>', $newContent);
//            $newContent = "<html><body><ul class=\"a-pagination\">" . $newContent[0] . "</ul></body></html>";
        $res = Helper::getResourceByXpath($content, $nextPage);
        $pages = [];
        if ($res) {
            // we got li nodes
            foreach ($res as $li) {
                $page = ['page' => $li->textContent, 'class' => $li->getAttribute('class')];
//                pr($li->childNodes);
                $link = Helper::getLinkFromNode($li);

                if ($link) {
                    $page['url'] = $link->getAttribute('href');
                }
                $pages[] = $page;
            }
        }

        $lastPage = 0;
        $currentPage = 1;
        $sampleUrl = '';
        $samplePage = '';
        if ($pages) {
            foreach ($pages as $page) {
                if ($page['class'] === 'a-disabled' && strpos($page['page'], 'Previous') === false) {
                    // this is a last page indicator
                    $lastPage = $page['page'];
                }
                if ($page['class'] === 'a-selected') {
                    $currentPage = $page['page'];
                }
                if (($page['url'] ?? null) && ((int)$page['page'])) {
                    $sampleUrl = $page['url'];
                    $samplePage = (int)$page['page'];
                }
            }
            if (!$lastPage) {
                // taking a page value of the second element from the end
                if (count($pages) > 2) {
                    $item = $pages[count($pages) - 2];
                    $lastPage = (int)$item['page'];
                } else {
                    $lastPage = 2;
                }
            }
        } else {
            // single page category
            $lastPage = 1;
            $sampleUrl = $this->url;
            $samplePage = 1;

        }
        if (!$sampleUrl) {
            // no paging found at all.
            $this->msg->addError('no paging found for category ' . $this->categoryId);
        }
        $this->json = [];

        $this->json['sampleUrl'] = $sampleUrl ? self::clearUrl($sampleUrl, $samplePage, $pageTag) : '';

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
//        pr($cleanPages);
        return $pages;

    }

    public static function clearUrl($sampleUrl, $samplePage, $pageTag)
    {
//        if (strpos($sampleUrl, '&qid=') !== false) {
//            $sampleUrl = substr($sampleUrl, 0, strpos($sampleUrl, '&qid='));
//        }
        $pageString = str_replace('{page}', $samplePage, $pageTag);

        if (strpos($sampleUrl, $pageString) !== false) {
            //all good, we found what we need.
            $concat = strpos($sampleUrl, '?') ? '&' : '?';
            return str_replace($concat . $pageString, '', $sampleUrl);
        } elseif ((int)$samplePage === 1) {
            // we have a single page category, also ok.
            return $sampleUrl;
        } else {
            // we have an issue, throw exception for now
            throw new \Exception('no paging tag found for ' . $sampleUrl);
        }
    }

    private function generatePageUrl(string $sampleUrl, int $i, $pageTag): string
    {
        if (strpos($sampleUrl, $this->host) === false) {
            $sampleUrl = $this->host . $sampleUrl;
        }
        $concat = strpos($sampleUrl, '?') ? '&' : '?';
        return $sampleUrl . $concat . str_replace('{page}', $i, $pageTag);
    }

    /**
     * @param $categoryData
     * @return array
     * @throws \Exception
     */
    private function getNavigationBlock($categoryData): array
    {
        $categoryId = $categoryData[$this->tableKey];
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
        $locale = $this->locale;
        $conf = $this->getConfig();
        $urlListPathContainer = $this->profile['productTag2Container'] ?? '';
        $urlListPath = $this->profile['productTag2'] ?? '';
        $urlListPath2 = $this->profile['productTag'] ?? '';
        $items = [];
        $list = [];
        if ($urlListPath && $urlListPathContainer) {
            $this->extractFieldWithContainer($items, $content,$urlListPathContainer, $urlListPath, 'url');
            if (count($items)) {
                foreach ($items as $item) {
                    $asin = $this->getAsinFromUrl($item['url']);
                    if (Helper::validateAsin($asin)) {
                        $list[$asin] = $asin;
                    }
                }
                $list = array_values($list);
            }
        }
//        pr($list, 'product-list');
//        $this->setStatus(self::STATUS_NEVER_CHECKED, $this->categoryId);
//        $this->limiterDelete();
//        die();
        if (!$list) {
            $items = [];
            $this->extractField($items, $content, $urlListPath2, 'url');
            $list = [];
            if (count($items)) {
                foreach ($items as $item) {
                    $asin = $this->getAsinFromUrl($item['url']);
                    if (Helper::validateAsin($asin)) {
                        $list[$asin] = $asin;
                    }
                }
                $list = array_values($list);
            }
        }
        pr('found ' . count($list) . ' items');
        return $list;
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

    public function addProductsFromHtml($items, $categoryData)
    {
        $categoryId = $categoryData[$this->tableKey];
        if(!$this->checkExistById($categoryId)){
            throw new \Exception('no category found, probably deleted');
        }
        $productFields = $categoryData['product_fields'];
        if ($productFields) {
            $productFields = unserialize($productFields);
        }
        $syncStatus = $productFields['syncable'];
        $addOptions = $productFields['addOptions'];
        $magentoList = $productFields['magentoList'];

        $product = new Gen($this->globalConfig, $this->proxy, $this->userAgent, "aaa", $this->locale);

        $product->addMessage(count($items) . ' Found for processing');
        $data = ['syncable' => $syncStatus ?: ProductSyncable::SYNCABLE_YES, $this->getTableKey() => $categoryId];
        if ($addOptions) {
            $data = array_merge($data, $addOptions);
        }
        $product->addNewProducts($items, $data);

        // process magento store associations
        $where = new Where();
        $where->in('asin', $items);
        $where->equalTo('locale', $this->locale);
        ProductToStore::associateProducts($this->globalConfig->getDb(), $where, $magentoList);

        // change sync status of existing products
        $product->updateList($where, ['syncable' => $syncStatus]);
        $this->msg->appendMessagesFromObject($product);
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
        $this->getConfigByUrl();
        $this->categoryId = $categoryId = $categoryData[$this->tableKey];

        $cp = new CategoryPage($this->getAdapter());
        $qtyFound = $this->scrapeSinglePage($categoryData, $pageToProcess['url'], $pageToProcess['page']);
        $cp->update(['found' => $qtyFound], [$cp->tableKey => $pageToProcess[$cp->tableKey]]);
        $qtyLeft = $cp->getPagesQty($this->categoryId);
        if (!$qtyLeft) {
            // no pages to scrape
            $this->setStatus(self::STATUS_SUCCESS, [$this->tableKey => $categoryId]);
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
        $getPageOptions['content_tag'] = 'amazon_category_initial_' . $this->categoryId . '_page_' . $page;
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

    /**
     * @param $categoryId
     * @param CategoryPageInterface $cp
     * @return mixed
     * @throws \Exception
     */
    public function scrapePages($categoryId, $cp)
    {
        $maxPages = (int) ($this->getConfig('settings', 'pagesQtyPerRun') ?? 10);
        // get a page to process
        for ($i = 0; $i < $maxPages; $i++) {
            $pageToProcess = $cp->loadPageCandidate($categoryId);
            if (!$pageToProcess) {
                $pageToProcess = $cp->loadPageCandidate();
                if (!$pageToProcess) {
                    //                    no pages in the queue
                    // check if there are categories which are still in not finished state
                    return;
                }
            }
            $categoryId = $pageToProcess[$this->getTableKey()];
            $categoryData = $this->select([$this->getTableKey() => $categoryId])->current();
            $this->scrapePageFromQueue($categoryData, $pageToProcess);
            $this->msg->addMessage('processed page ' . $pageToProcess['page'] . ' for category ' . $categoryId);
        }
    }

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
            $where->in($this->tableKey, $list);
            $refreshData = ['status' => self::STATUS_NEVER_CHECKED, 'json' => null, 'page' => null, 'next_page_url' => null];
            $this->update($refreshData, $where);

            $where = new Where();
            $where->in($this->tableKey, $list);
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
                $ids[] = $item[$this->tableKey];
            }
            if ($ids) {
                $this->deleteCategories($ids, $withProducts);
            }
        }
    }

    /**
     * @param $filter array
     * @param bool $ignorePaging
     * @return array
     */
    public function getCategoryList($filter, $ignorePaging = false): array
    {
        // calculate total count
        //        $totalCount =
        $select = new Select(['l' => $this->getTable()]);
        $select->columns([$this->getTableKey(), 'url', 'status', 'json', 'created', 'updated', 'page', 'last_page', 'title', 'log', 'profile', 'product_fields', 'marketplace_category', 'web_hierarchy_location_codes']);

        $joinExpression = '(SELECT SUM(`found`) as `found`, SUM(`checked`) as `checked`, count(`{table}_page_id`) as `totalPages`, `{tableKey}` FROM `{table}_page` GROUP BY {tableKey})';
        $joinExpression = str_replace(['{tableKey}', '{table}'], [$this->getTableKey(), $this->getTable()], $joinExpression);
        $joinOnExpression = 'l.{tableKey} = acp.{tableKey}';
        $joinOnExpression = str_replace(['{tableKey}', '{table}'], [$this->getTableKey(), $this->getTable()], $joinOnExpression);

        $select->join(['acp' => new Expression($joinExpression)], $joinOnExpression ,
            ['found', 'checked', 'totalPages'], Join::JOIN_LEFT);

        $joinPExpression = '(SELECT COUNT(product_id) as qty, {tableKey} FROM product GROUP BY {tableKey})';
        $joinPExpression = str_replace(['{tableKey}', '{table}'], [$this->getTableKey(), $this->getTable()], $joinPExpression);

        $joinPONExpression = 'l.{tableKey} = p.{tableKey}';

        $joinPONExpression = str_replace(['{tableKey}', '{table}'], [$this->getTableKey(), $this->getTable()], $joinPONExpression);

        $select->join(['p' => new Expression($joinPExpression)], $joinPONExpression,
            ['product_qty' => 'qty'], Join::JOIN_LEFT);
        $where = $this->getCondition($filter);
        $select->where($where);

        $page = (int)($filter['page'] ?? 1);
        if ($page && !$ignorePaging) {
            $limit = $filter['per-page'] ?? 100;
            $select->limit($limit);
            $select->offset(($page - 1) * $limit);
        }
        $select->quantifier(new Expression('SQL_CALC_FOUND_ROWS'));

        $rowSet = $this->selectWith($select);
//        $sql = new Sql($this->getAdapter());
//        $stmt = $sql->prepareStatementForSqlObject($select);
//        print_r($stmt->getSql());die();


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
//        pr($filter);die();
        $where = parent::getCondition($filter, $tablePrefix);
        if ($filter['zero-products']) {
            $pageId = $tablePrefix ? $tablePrefix.'.last_page' : 'last_page';
            $where->nest()
                ->lessThan($pageId, 1)
                ->or
                ->isNull($pageId)
                ->unnest();
        }

        if ($filter['profile'] && $filter['profile'] != -1) {
            $where->equalTo('profile', $filter['profile']);
        }

        if ($filter['marketplace_category'] && $filter['marketplace_category'] != -1) {
            $where->equalTo('marketplace_category', $filter['marketplace_category']);
        }

        if ($filter['web_hierarchy_location_codes'] && $filter['web_hierarchy_location_codes'] != -1) {
            $where->equalTo('web_hierarchy_location_codes', $filter['web_hierarchy_location_codes']);
        }

        return $where;
    }

    public function deleteCategories($list, $withProducts = false)
    {
        if ($list && $withProducts) {
            $product = new \Parser\Model\Product($this->globalConfig, $this->proxy, $this->userAgent, '', 'ca');
            $where = new Where();
            $where->in($this->getTableKey(), $list);
            $product->deleteList($where);
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
                $ids[] = $item[$this->tableKey];
            }
            if ($ids) {
                $where = new Where();
                $where->in($this->getTableKey(), $ids);
                $cp = new CategoryPage($this->getAdapter());
                $cp->delete($where);
            }
        }

    }

    public function testContent($url)
    {
        $getPageOptions = $this->getCommonBrowserOptions();
        $getPageOptions['content_tag'] = 'amazon_category_initial_test_url';
//        $proxyDataArray = $this->proxy->loadProxyByIpPort('127.0.0.1', '9050');
//        $this->proxy->loadFromArray($proxyDataArray);
        $this->getPage($url, [], [], $getPageOptions);
        // the url may be given after redirects.
        $content = $this->content;
        Helper::deleteContentFileByPath($getPageOptions['content_tag']);
//        $content = $this->prependContent();
        return $content;
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
            'web_hierarchy_location_codes'=> ''
        ];
        $filter = array_intersect_key($filter, $fields);
        $filter = array_merge($fields, $filter);
        return $filter;
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
                'name' => 'filter['.$field.']',
                'aria-controls' => 'datatable-responsive',
                'class' => 'col-lg-12 form-control padd-top',
                'id' => 'filter-'.$field,
            ], ['no-default-value' => 1]);
    }

    public function processRoutines()
    {
        $filter = ['page' => 1, 'per-page' => 500];
        $filter = $this->prepareListFilter($filter);
//        pr($filter);
        $list = $this->getCategoryList($filter);
        $this->applyRoutines($list);
        $totalResults = $this->totalResults;
        if (($totalResults/500) > 1){
            $maxPage = (int) $totalResults/500;
            for($i=2; $i<=$totalResults;$i++){
                $filter['page'] = $i;
                $list = $this->getCategoryList($filter);
                $this->applyRoutines($list);
            }
        }
    }

    public function applyRoutines($list)
    {
        foreach($list as $item) {
            $data = $this->getProductOptionsForCategory($item);
            $unique = [$this->tableKey=> $data[$this->tableKey]];
            unset($data[$this->tableKey]);
            $data = $this->processData($data);
            $this->update($data, $unique);
        }
    }

    public function getUrlTableFilterFields(array $filter)
    {
        $string = '<table class="table">';
        $string.= '<tr><td>Url</td>';
        $string.= '<td>Marketplace Cat</td><td>Web Hierarchy LC</td>
</tr>';
        $string.= '<tr><td>' . Tag::html('', 'input', ['value' => $filter['title'] ?? null, 'name' => 'filter[title]', 'type' => 'text', 'class' => 'col-lg-12 form-control padd-top',], true). '</td>';
        $string.= '<td>&nbsp;' . $this->getSelectDropDown($filter['marketplace_category'] ?? null, $filter, 'marketplace_category'). '</td>';
        $string.= '<td>&nbsp;' . $this->getSelectDropDown($filter['web_hierarchy_location_codes'] ?? null, $filter, 'web_hierarchy_location_codes'). '</td>';
        $string .= '</tr>';

        $string .= '</table>';

        return $string;
    }

    public function extractCategoriesFromFile($fileName)
    {
        // remove bom
        $file = new \SplFileObject($fileName);
        $file->setCsvControl(';');
        $file->setFlags(\SplFileObject::READ_CSV);
        // format Marketplace Category;Marketplace Category Name;Web Hierarchy Location Codes;Category Url
        $categoryList = [];
        $syncables = ProductSyncable::getOptionsStrToLower();

        foreach ($file as $key => $row) {
            if ($row[0] && $key == 0 && strpos(strtolower($row[0]), 'marketplace') !== false) {
                // skip first row
                continue;
            }
            $string = trim($row[0]);
            if ($key == 0) {
                // remove bom if it is there
                $bom = pack('H*', 'EFBBBF');
                $string = preg_replace("/^$bom/", '', $string);
                $row[0] = $string;
            }
//            $row = explode(';',$string);
            if(count($row) >= 5){
                // valid row
                $addOptions = [
                    'marketplace_category' => $row[0],
                    'marketplace_category_name' => $row[1],
                    'web_hierarchy_location_codes' => $row[2],
                    'web_hierarchy_location_name' => $row[3],
                ];
                $data = ['addOptions' => $addOptions, 'url' => trim($row[4])];
                if(isset($row[5])){
                    $autoscrape = strtolower($row[5]);
                    $data['autoScrapeCategoriesO'] = $row[5];
                    if($autoscrape === 'yes'){
                        $autoscrape = 1;
                    } elseif($autoscrape === 'no') {
                        $autoscrape = 0;
                    } else {
                        $autoscrape = (int) $autoscrape;
                    }
                    $data['autoScrapeCategories'] = $autoscrape;
                }
                if(isset($row[6])){
                    $syncable = strtolower($row[6]);
                    $data['syncableO'] = $row[6];
                    if(isset($syncables[$syncable])){
                        $data['syncable'] = $syncable;
                    } elseif(in_array(strtolower($syncable), $syncables)){
                        $syncable = strtolower($syncable);
                        $syncable = array_search($syncable, $syncables);
                        $data['syncable'] = $syncable;
                    }
                }

                $categoryList[] = $data;
            } else {
                if(count($row) > 1) {
                    $this->msg->addError('row has missing fields :' . implode(';', $row));
                }
            }

        }
        return $categoryList;
    }

    private function devReset()
    {
        pr($this->categoryId);
        $this->setStatus(self::STATUS_NEVER_CHECKED, $this->categoryId);
        $this->limiterDelete();
        die();
    }

    /**
     * @return array
     */
    public function getContentMarkers(): array
    {
        return [
            ['code' => 0, 'function' => 'strlen', 'size' => '1500'],
            ['code' => 503, 'function' => 'strpos', 'pattern' => 'Something Went Wrong'],
// no need to catch captcha, it is cought by default for amazn, but if you set it here - it will not attemt to solve it, if set to solve
//            ['code' => 505, 'function' => 'strpos', 'pattern' => 'Type the characters you see in this image'],
        ];
    }

    public function fixCompletedPagesInProgressStatus(){
        // some categories may remain in progress however, all pages are scraped (this occures, if some page gave a fatal error)
        $query = 'SELECT ac.amazon_category_id, COUNT(acp.amazon_category_page_id) totalQty, SUM(IF(acp.checked=1, 1, 0)) as checkedQty FROM `amazon_category`  ac LEFT JOIN `amazon_category_page` acp ON ac.amazon_category_id = acp.amazon_category_id WHERE ac.status=' . self::STATUS_IN_PROGRESS . ' HAVING totalQty > 0  AND totalQty = checkedQty';

        $sql = new Sql($this->getAdapter());
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        $listToUpdate = [];
        while ($item = $result->current()) {
            $listToUpdate[] = $item['amazon_category_id'];
            $result->next();
        }
        if (count($listToUpdate)) {
            $this->msg->addMessage('reseting to checked for categories '. implode(',', $listToUpdate));
            $where = new Where();
            $where->in($this->getTableKey(), $listToUpdate);
            $this->update(['status' => self::STATUS_SUCCESS], $where);
        }
    }
}
<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2018
 * Time: 23:39
 */

namespace Parser\Controller;

use Parser\Model\Amazon\Category;
use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Form\SearchForm;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\Html\Paging;
use Parser\Model\Html\Tag;
use Parser\Model\Magento\Store;
use Parser\Model\Product;
use Parser\Model\Profile;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\Console\Request as ConsoleRequest;
use Laminas\Db\Sql\Where;
use Laminas\View\Model\ViewModel;
use Parser\Model\Amazon\Html\CategoryList;
use Parser\Model\Amazon\Html\Helper as HtmlCategoryHelper;

class CrawlerController extends AbstractController
{
    private $db;
    private $proxy;
    private $userAgent;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = $config->getDb();

        $this->proxy = new Proxy($this->db, $config);
        $this->userAgent = new UserAgent($this->db);
        $this->authActions = ['search', 'list'];

    }


    public function listAction()
    {

        $category = new Category($this->config);

        $asin = $this->params()->fromQuery('asin', 'asin');
        $syncable = $this->params()->fromPost('syncable', ProductSyncable::SYNCABLE_PRESYNCED);
        $locale = '';

        $product = new Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $product->getLocalesForForm();

        /** @var Store $magento */
        $magentoListSelected = $this->params()->fromPost('magentoStore', []);
        $uploadFormFields = $customFields['UploadForm'] ?? [];

        $filterNew = $this->params()->fromPost('filter', []) + $this->params()->fromQuery('get', []);
        $resetFilter = $this->params()->fromPost('resetFilter', false);
        $filter = $category->getListFilter('amazon-category-list', $filterNew, $resetFilter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $requestData = $request->getPost()->toArray();
            $list = $requestData['filter']['amazon_category_id'] ?? [];
            $deleteProducts = $requestData['delete-product'] ?? null;
            if ($list) {
                $list = array_keys($list);
            }
            if ($requestData['category_to_delete'] ?? false) {
                // delete category
                $res = $category->delete(['amazon_category_id' => $requestData['category_to_delete']]);
                if ($res) {
                    $category->msg->addMessage('category has been removed');
                }
                $filter['page'] = 1;
            } else if ($requestData['category_to_refresh'] ?? false) {

                $category->refresh([$requestData['category_to_refresh']]);
            } else if ($requestData['refresh-all'] ?? false) {
                $category->refreshAll($filter);
            } else if ($requestData['delete-all'] ?? false) {
                $category->deleteAllCategories($filter, $deleteProducts);
                $filter = $category->getListFilter('amazon-category-list', $filterNew, 1);
            } else if ($requestData['refresh-selected'] ?? false) {
                if ($list) {
                    $category->refresh($list);
                    $category->msg->addMessage('refreshed ' . count($list) . ' categories');
                } else {
                    $category->msg->addError('no items selected');
                }

            } else if ($requestData['delete-selected'] ?? false) {
                if ($list) {
                    $category->deleteCategories($list, $deleteProducts);
                    $category->msg->addMessage('categories has been removed');
                } else {
                    $category->msg->addError('no items selected');
                }
            }
        }
        $categoryList = new CategoryList(1, 100, 100);
        $perPage = $filter['per-page'] ?? 100;
        $page = $filter['page'] ?? 1;
        $categoryItems = $category->getCategoryList($filter);
        $paging = new Paging($filter['page'], $category->getTotalResults(), $perPage);
        $pagingView = $paging->getAsHTML();
        $perPageSelect = $paging->getPerPageSelectorDropdown($perPage);
        // nasty look.
//        pr($filter);die();

        $categoryList->filterFields = ['status' => '<br>' . $category->getStatusDropdown($filter['status']),
            'profile' => '<br>possible profiles: ' . $category->getPossibleProfiles($filter) . '<br>' . $category->getSelectDropDown($filter['profile'] ?? null, $filter, 'profile'),
            'url' => $category->getUrlTableFilterFields($filter),
            'descritpion' => '<br> zero products ' . HtmlCategoryHelper::getCheckbox($filter, 'zero-products')
        ];
        $table = $categoryList->getTable($categoryItems, [
            'scripts' => HtmlCategoryHelper::getSimpleOnchangeSubmit('filter-status', 'category-form')
                . HtmlCategoryHelper::getSimpleOnchangeSubmit('per-page', 'category-form')
                . HtmlCategoryHelper::getPagingScript('page-input', 'category-form'),
            'inputs' => HtmlCategoryHelper::getPageInput('page-input', 'filter[page]', $page)
        ]);

//        pr($requestData);die();
        if (($this->getRequest()->isPost() || $this->params()->fromQuery('resetFilter', '')) && !($requestData['filter-button'] ?? null)) {
            return $this->redirect()->toRoute('crawler', ['action' => 'list']);
        }
        $view = new ViewModel([
            'table' => $table,
            'message' => $category->msg->getStringMessages('<br />'),
            'errors' => $category->msg->getStringErrorMessages('<br />'),
            'perPageSelect' => $perPageSelect,
        ]);
        $view->addChild($pagingView, 'paging');
        return $view;

    }

    public function addurlAction()
    {
        $category = new Category($this->config);
        $url = 'https://www.amazon.it/b/ref=dp_bc_aui_C_3?ie=UTF8&node=2892901031';
        $category->getCategory($url);
        die('a');
    }

    /**
     * @return ViewModel
     */
    public function searchAction(): ViewModel
    {
        $data = [];
        $category = new Category($this->config);

        $asin = $this->params()->fromQuery('asin', 'asin');
        $syncable = $this->params()->fromPost('syncable', ProductSyncable::SYNCABLE_PRESYNCED);
        $locale = '';

        $product = new Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $product->getLocalesForForm();

        /** @var Store $magento */
        $magentoListSelected = $this->params()->fromPost('magentoStore', []);

        $magento = new Store($this->db);
        $magentoList = $magento->getOptionsArray($magentoListSelected);

        $autoPaging = $this->params()->fromPost('autoPaging', 0);
        $autoScrapeCategories = $this->params()->fromPost('autoScrapeCategories', 0);
        $customFields = $this->config->getConfig('customFields');
        $uploadFormFields = $customFields['UploadForm'] ?? [];

        $uploadForm = new SearchForm('', ['uploadFormFields' => $uploadFormFields]);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $requestData = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            $uploadForm->setData($requestData);

            if ($requestData['category_to_delete'] ?? false) {
                // delete category
                $category->delete(['amazon_category_id' => $requestData['category_to_delete']]);
            } elseif ($uploadForm->setData($requestData) && $uploadForm->isValid()) {
                $data = $uploadForm->getData();
                $url = $uploadForm->get('category_url')->getValue();
                $fileErrors = $uploadForm->get('category_list')->getMessages();
                $tempFile = $uploadForm->get('category_list')->getValue();
                $categories = [];
                if (!$tempFile['tmp_name'] && !$url) {
                    $category->msg->addError('No url specified and/or no csv file is placed');
                }
                if ($tempFile['tmp_name']) {
                    $categories = $category->extractCategoriesFromFile($tempFile['tmp_name']);
                }
                if ($tempFile['tmp_name'] && $fileErrors) {
                    $category->msg->addError('failed to upload file ' . implode(',', $fileErrors));
                }

                if (!$category->msg->hasErrors()) {
                    // adding custom fields from the config, very nasty.
                    $addOptions = [];
                    if (!empty($uploadFormFields)) {
                        foreach ($uploadFormFields as $customField => $fieldSet) {
                            if ($value = $uploadForm->get($customField)->getValue()) {
                                $addOptions[$customField] = $value;
                            }
                        }
                    }
                    try {
                        $dataToAdd = [
                            'syncable' => $syncable,
                            'magentoList' => $magentoListSelected,
                            'autoPaging' => $autoPaging,
                            'addOptions' => $addOptions,
                            'autoScrapeCategories' => $autoScrapeCategories
                        ];
                        if ($url) {
                            $category->add($url, $dataToAdd);
                        }

                        if ($categories) {
                            foreach ($categories as $item) {
                                $itemData = $dataToAdd;
                                if ($item['addOptions'] ?? null) {
                                    $itemData['addOptions'] = array_merge($dataToAdd['addOptions'], $item['addOptions']);
                                }
                                if(isset($item['autoScrapeCategories'])){
                                    $itemData['autoScrapeCategories'] = $item['autoScrapeCategories'];
                                }
                                if(isset($item['syncable'])){
                                    $itemData['syncable'] = $item['syncable'];
                                }
                                $category->add($item['url'], $itemData);
                            }
                        }
                    } catch (\Exception $e) {
                        Helper::logException($e, 'categoryCrawler.error.log');
                    }
                }
            }
        }
//        $categoryList = new CategoryList(1, 100, 100);
//        $table = $categoryList->getTable($category->getCategoryList(), []);

        return new ViewModel([
            'form' => $uploadForm,
            'items' => $data,
            'syncableList' => ProductSyncable::getOptions(),
            'syncable' => $syncable,
            'storeList' => $magentoList,
            'autoPaging' => $autoPaging,
            'autoScrapeCategories' => $autoScrapeCategories,
//            'table' => $table,
            'message' => $category->msg->getStringMessages('<br />'),
            'errors' => $category->msg->getStringErrorMessages('<br />'),
        ]);

    }

    /**
     *
     * @throws \Exception
     *
     * Main function to start scraping process.
     * Function checks for the process limit
     *
     */
    public
    function scrapeAction()
    {
        $request = $this->getRequest();

        if (!$request instanceof ConsoleRequest) {
            $verbose = $request->getQuery('verbose') || $request->getQuery('v') || $request->getQuery('debug');
            $delay = $request->getQuery('delay') || $request->getQuery('d');
        } else {
            $verbose = $request->getParam('verbose') || $request->getParam('v') || $request->getParam('debug');
            $delay = $request->getParam('delay');
        }
        if ((int)$delay) {
            sleep((int)$delay);
        }
        $this->config->setProperty('DebugMode', $verbose);
        $category = new Category($this->config);

        $processExpireDelay = $category->getConfig('settings', 'processExpireDelay') ?: 300;
        $activeConnectionsLimit = $category->getConfig('settings', 'activeConnectionsLimit') ?: 5;

        $regularSyncPath = $category->getConfig('settings', 'processId') ?: 'amazon_category';

        $limiter = new ProcessLimiter($this->config, [
            'path' => $regularSyncPath,
            'expireTime' => $processExpireDelay,
            'processLimit' => $activeConnectionsLimit,
        ]);
        $productsUpdated = [];
        $categoryData = [];
        if (($limiterID = $limiter->initializeProcess()) && $this->proxy->loadAvailableProxy()) {
            $category->setLimiter($limiter);

            try {
                $category->scrape();
            } catch (\Exception $e) {
                pr($e->getMessage());
                Helper::logException($e, 'scrapeAmazonCategory.error.log');
            }

            $limiter->delete(['process_limiter_id' => $limiterID]);
            $message = $category->msg->getStringMessages();
        } else {
            $message = 'Active Connections limit reached, try to start sync later';
            $categoryData = [];
        }
        if (!$request instanceof ConsoleRequest) {
            return $this->scrapeTemplate([
                'items' => $categoryData,
                'products' => $productsUpdated,
                'message' => $message,
            ]);
        }
        return print_r([
            'products' => $productsUpdated,
            'message' => $message,
        ], 1);
    }


    public
    function testAction()
    {
        $layout = $this->layout();
        $layout->setVariable('action', 'test');
        $children = $layout->getChildren();
        $result = new ViewModel([
            'children' => $children,
        ]);
        return $result;

    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public
    function testScrapeAction(): ViewModel
    {
        $debugMode = 1;
        $this->config->setProperty('DebugMode', $debugMode);
        $category = new Category($this->config);
        $categoryId = $this->params()->fromQuery('cat', '');
        $category->setStatus(Category::STATUS_NEVER_CHECKED, ['amazon_category_id' => $categoryId]);
        $category->scrape($categoryId);
        return $this->zeroTemplate();
    }

    public
    function testurlAction()
    {
//        $url = 'https://www.amazon.ca/s?k=iphone%2BXR%2Bcases&i=electronics&bbn=13542515011&rh=n%3A667823011%2Cn%3A13542515011%2Cp_85%3A5690392011%2Cp_36%3A-2000%2Cp_72%3A11192170011%2Cp_90%3A11828088011%2Cp_n_feature_nine_browse-bin%3A21219659011&dc&pf_rd_i=3379552011&pf_rd_m=A3DWYIK6Y9EEQB&pf_rd_p=afeaa7cc-fe8c-4767-90d5-74e18ff9cd09%2Cafeaa7cc-fe8c-4767-90d5-74e18ff9cd09&pf_rd_r=T96GB09SZWWJ9831JEZJ%2CT96GB09SZWWJ9831JEZJ&pf_rd_s=merchandised-search-leftnav&pf_rd_t=101&qid=1598973604&rnid=8884049011&ref=sr_nr_p_n_feature_nine_browse-bin_2';
//        $url = 'https://www.amazon.ca/Best-Sellers-Computer-Video-Games-PC-Gamepads-Standard-Controllers/zgbs/videogames/403532011/ref=zg_bs_pg_2?_encoding=UTF8&pg=2';
        $url = 'https://www.amazon.ca/s?i=electronics&bbn=3379560011&rh=n%3A667823011%2Cn%3A677211011%2Cn%3A3379552011%2Cn%3A3379553011%2Cn%3A3379560011%2Cp_85%3A5690392011&dc&fst=as%3Aoff&qid=1599670480&rnid=5690384011&ref=sr_nr_p_85_1';
        $url = 'https://www.amazon.ca/s?i=electronics&bbn=3379560011&rh=n%3A667823011%2Cn%3A677211011%2Cn%3A3379552011%2Cn%3A3379553011%2Cn%3A3379560011%2Cp_85%3A5690392011&dc&fst=as%3Aoff';
        $url = 'https://www.amazon.ca/s?i=electronics&bbn=8929975011&rh=n%3A8929975011%2Cn%3A667823011%2Cn%3A3379552011%2Cn%3A3379553011&dc&fst=as%3Aoff&rnid=8929975011&ref=sr_nr_n_1';
        pr(urldecode($url));
//        die();
        $this->config->setProperty('DebugMode', 1);
        $category = new Category($this->config);
        $content = $category->testContent(urldecode($url));
        print_r($content);
        return $this->zeroTemplate();
    }

    public
    function testcodeAction()
    {
        $string = '&rh=n%3A8929975011%2Cn%3A667823011%2Cn%3A3379552011%2Cn%3A3379553011';
        pr(urldecode($string));
        return $this->zeroTemplate();
    }

    public
    function processCategoriesAction()
    {
        $cat = new Category($this->config);
        $cat->processRoutines();
        return $this->zeroTemplate();
    }


    public function sampleFileCategoryListAction(){
        $filePath = '/files/sampleCategoryList.csv';
        return $this->redirect()->toUrl($filePath);
    }
    public function sampleAsinListAction(){
        $filePath = '/files/sampleAsinList.csv';
        return $this->redirect()->toUrl($filePath);

    }
    public function sampleComparatorItemListAction(){
        $filePath = '/files/sampleComparatorItemList.csv';
        return $this->redirect()->toUrl($filePath);
    }

}


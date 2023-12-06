<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 23:39
 */

namespace BestBuy\Controller;

use BestBuy\Model\BestBuy\Category;
use BestBuy\Model\BestBuy\CategoryList;
use BestBuy\Model\BestBuy\KeepaAPI;
use BestBuy\Model\BestBuy\Product;
use BestBuy\Model\Form\CategoryForm;
use Parser\Controller\AbstractController;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\Html\Paging;
use Parser\Model\Html\Tag;
use Parser\Model\ProductSync;
use Parser\Model\SimpleObject;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Parser\Model\Amazon\Html\Helper as HtmlCategoryHelper;
use Laminas\View\Model\ViewModel;


/**
 * Class ListController
 * @package Parser\Controller
 * @inheritdoc
 */
class ListController extends AbstractController
{
    private $db;
    /* @var $proxy Proxy */
    private $proxy;
    private $userAgent;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = $this->config->getDb();
        /**
         * @var $proxy Proxy
         */
        $this->proxy = new Proxy($this->db, $config);
        /**
         * @var $userAgent UserAgent
         */
        $this->userAgent = new UserAgent($this->db);
        $this->authActions = ['list', 'index', 'import', 'category'];
    }

    public function checkActionForAuth($action): bool
    {
        $auth = $this->getAuth();
        if ($auth->hasIdentity()) {
            $this->identity = $auth->getIdentity();
        }
        if ($this->identity === 'Store Owner') {
            return false;
        }
        return parent::checkActionForAuth($action);
    }

    public function listAction()
    {
        $result = new ViewModel([]);
        $result->setTemplate('zero');
        return $result;
    }


    /**
     * small todo list for form like action
     * 1. create form class with required fields in /model/form
     * 2. create /view/bestbuy/controller/action phtml file for this form
     * 3. create controller/action
     *
     * @return ViewModel
     * @throws \Exception
     */
    public function categoryAction(): ViewModel
    {
        $filePath = '';
        $uploadForm = new CategoryForm('category-form');

//        $debugMode = $this->params()->fromPost('debug');
        $this->config->setDebugMode(1);
        /** @var Category $category */
        $category = new Category('', $this->config);

        $filterNew = $this->params()->fromPost('filter', []) + $this->params()->fromQuery('get', []);
        $resetFilter = $this->params()->fromPost('resetFilter', false);

        $filter = $category->getListFilter('bestbuy-category-list', $filterNew, $resetFilter);

        $request = $this->getRequest();
        $itemList = [];
        $showItems = false;
        if ($request->isPost()) {
            $requestData = $request->getPost()->toArray();
            $list = $requestData['filter']['category_best_buy_id'] ?? [];
            if ($list) {
                $list = array_keys($list);
            }
            $deleteProducts = $requestData['delete-product'] ?? null;
            if ($requestData['category_to_refresh'] ?? false) {
                // delete category
                $category->refresh(['category_best_buy_id' => $requestData['category_to_refresh']]);
            } elseif ($requestData['category_to_delete'] ?? false) {
                // delete category
                $category->delete(['category_best_buy_id' => $requestData['category_to_delete']]);
            } else if ($requestData['refresh-all'] ?? false) {
                $category->refreshAll($filter);
            } else if ($requestData['delete-all'] ?? false) {
                $category->deleteAllCategories($filter, $deleteProducts);
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
        $categoryList->filterFields = ['status' => '<br>' . $category->getStatusDropdown($filter['status']),
            'url' => '<br>' . Tag::html('', 'input', ['value' => $filter['title'] ?? null, 'name' => 'filter[title]', 'type' => 'text', 'class' => 'form-control',], true),
            'bb_category' => '<br>' . Tag::html('', 'input', ['value' => $filter['bb_category'] ?? null, 'name' => 'filter[bb_category]', 'type' => 'text', 'class' => 'form-control input-sm',], true),

        ];

        $perPage = $filter['per-page'] ?? 100;
        $page = $filter['page'] ?? 1;
        $categoryItems = $category->getCategoryList($filter);
        $paging = new Paging($filter['page'], $category->getTotalResults(), $perPage);
        $pagingView = $paging->getAsHTML();
        $perPageSelect = $paging->getPerPageSelectorDropdown($perPage);

        $table = $categoryList->getTable($categoryItems,
            [
                'scripts' => HtmlCategoryHelper::getSimpleOnchangeSubmit('filter-status', 'category-form')
                    . HtmlCategoryHelper::getSimpleOnchangeSubmit('per-page', 'category-form')
                    . HtmlCategoryHelper::getPagingScript('page-input', 'category-form'),
                'inputs' => HtmlCategoryHelper::getPageInput('page-input', 'filter[page]', $page)
            ]);

        $view =  new ViewModel([
            'form' => $uploadForm,
            'itemList' => $itemList,
            'filePath' => $filePath,
            'showItems' => $showItems,
            'table' => $table,
            'message' => $category->msg->getStringMessages('<br />'),
            'errors' => $category->msg->getStringErrorMessages('<br />'),
            'perPageSelect' => $perPageSelect,

        ]);

        $view->addChild($pagingView, 'paging');
        return $view;
    }

    public function uploadAction()
    {
        $filePath = '';
        $uploadForm = new CategoryForm('category-form');

        /** @var Category $category */
        $category = new Category('', $this->config);
        $request = $this->getRequest();
        $itemList = [];
        $showItems = false;
        if ($request->isPost()) {
            $requestData = $request->getPost()->toArray();
            if ($uploadForm->setData($requestData) && $uploadForm->isValid()) {
                $categoryId = $uploadForm->get('category_id')->getValue();
                $categoryIds = Helper::extractRegularelySeparatedItemsFromString($categoryId);
                $category->processList($categoryIds);
            }
        }
        return new ViewModel([
            'form' => $uploadForm,
            'itemList' => $itemList,
            'filePath' => $filePath,
            'showItems' => $showItems,
            'message' => $category->msg->getStringMessages('<br />'),
            'errors' => $category->msg->getStringErrorMessages('<br />'),
        ]);

    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function scrapeAction(): ViewModel
    {
        $debugMode = $this->params()->fromQuery('debug', '');
        $this->config->setProperty('DebugMode', $debugMode);

        $category = new Category('', $this->config);

        $processExpireDelay = $category->getConfig('settings', 'processExpireDelay') ?: 240;
        $activeConnectionsLimit = $category->getConfig('settings', 'activeConnectionsLimit') ?: 5;

        $regularSyncPath = $category->getConfig('processLimiter', 'processId') ?: 'bb_scrape';

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
                $categoryData = $category->scrape();
                $product = new Product('', $this->config);
                $product->setLimiter($limiter);
                $productsUpdated = $product->scrape();
                $productsUpdated = $product->scrapeAmazon();
            } catch (\Exception $e) {
                Helper::logException($e, 'scrapeBB.error.log');
            }


            $limiter->delete(['process_limiter_id' => $limiterID]);
            if ($categoryData['status'] ?? null) {
                $message = 'scrape success.';
            } elseif ($productsUpdated) {
                $message = 'products found';
            } else {
                $message = 'no category found';
            }
        } else {
            $message = 'Active Connections limit reached, try to start sync later';
            $categoryData = [];
        }

        $result = new ViewModel([
            'items' => $categoryData,
            'products' => $productsUpdated,
            'message' => $message,
        ]);
        $result->setTerminal(true);
        return $result;
    }


    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function scrapeKeepaAction(): ViewModel
    {
        $debugMode = $this->params()->fromQuery('debug', '');
        $this->config->setProperty('DebugMode', $debugMode);

        $category = new Category('', $this->config);
        $processExpireDelay = $category->getConfig('settings', 'processExpireDelay') ?: 240;
        $activeConnectionsLimit = $category->getConfig('settings', 'activeConnectionsLimitKeepa') ?: 10;

        $regularSyncPath = $category->getConfig('processLimiter', 'processId') ?: 'bb_keepa_scrape';
        $limiter = new ProcessLimiter($this->config, [
            'path' => $regularSyncPath,
            'expireTime' => $processExpireDelay,
            'processLimit' => $activeConnectionsLimit,
        ]);
        $product = new Product('', $this->config);
        if ($limiterID = $limiter->initializeProcess()) {

            $product->setLimiter($limiter);
            try {
                for ($i = 0; $i < 5; $i++) {
                    $result = $product->checkKeepaForSingleProduct();
                    if (!$result) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                Helper::logException($e, 'scrapeKeepa.error.log');
            }
            $limiter->delete(['process_limiter_id' => $limiterID]);
        } else {
            $product->msg->addMessage('Active Connections limit reached, try to start sync later');

        }
        $result = new ViewModel([
            'message' => $product->msg->getStringMessages(),
            'error' => $product->msg->getStringErrorMessages(),
        ]);
        $result->setTerminal(true);
        return $result;
    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function testAction(): ViewModel
    {
        $this->config->setProperty('DebugMode', 1);


        $product = new Product('', $this->config);
//        $rowset = $product->select([$product->getTableKey() => '6431']);
//        $data = $rowset->current();
//        // product data
//        $content = gzuncompress($data['content']);
//        $json = $product->getJson($content);

//        $productData = $json['product']['product'];

//        $data = $product->getFieldsFromJSON($json);
//        pr($data);
//        unset($productData['seller']['policies']);
//        unset($json['product']['product']['seller']['policies']);
//        // categories
//        pr($json['product']['category']['categoryBreadcrumb']);

// amazon content
//        $content = gzuncompress($data['amazon_content']);
//        pr($content);

//      currently adding category fields to product_best_buy
        $product->processFieldChange(5, 2, 100, 10000);
//

//        $items = $product->checkAmazonByUPC('840981118895');
//        pr($items);

        return $this->zeroTemplate();
    }

    public function catTestAction()
    {
        $this->config->setProperty('DebugMode', 1);
        try {
            $cat = new Category('', $this->config);
            $data = $cat->scrape(14);
            pr($data);
        } catch (\Exception $e) {
            pr('failed');
        }
        return $this->zeroTemplate();


    }

    public function keepaAction()
    {
        $apiKey = 'c4gh0qps38f7c0u7ec8hkvjstgqu90ar44i750t4caadaft72akhbb3q844r3edi';
        $keepa = new KeepaAPI($this->config, $apiKey);

        $resp = $keepa->getProducts('OTTERBOX', '77-21912');
        pr($resp);
        return $this->zeroTemplate();
    }

    public function processKeepaProductsAction()
    {
        $this->config->setProperty('DebugMode', 1);
        $product = new Product('', $this->config);
        $product->processKeepaFieldChange(3, 2, 100, 1000);

        return $this->zeroTemplate();
    }

}
<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 23:39
 */

namespace Cdiscount\Controller;

use Cdiscount\Model\Cdiscount\Category;
use Cdiscount\Model\Cdiscount\CategoryList;
use Cdiscount\Model\Cdiscount\Product;
use Cdiscount\Model\Cdiscount\ProductList;
use Cdiscount\Model\Form\CategoryForm;
use Cdiscount\Model\Form\SearchForm;
use Parser\Controller\AbstractController;
use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Html\Paging;
use Parser\Model\Magento\Store;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\View\Model\ViewModel;
use Parser\Model\Amazon\Html\Helper as HtmlCategoryHelper;
use Parser\Model\Helper\ProcessLimiter;


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
        $this->authActions = ['list', 'index', 'import', 'search', 'products'];
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


    /**
     * small todo list for form like action
     * 1. create form class with required fields in /model/form
     * 2. create /view/cdiscount/controller/action phtml file for this form
     * 3. create controller/action
     *
     * @return ViewModel
     * @throws \Exception
     */
    public function categoryAction(): ViewModel
    {
        return $this->returnMessageTemplate(['errors' => 'no route here']);
        $filePath = '';
        $uploadForm = new CategoryForm('category-form');

//        $debugMode = $this->params()->fromPost('debug');
        $this->config->setDebugMode(1);


        $request = $this->getRequest();
        $itemList = [];
        $showItems = false;
        $category = new Category('', $this->config);
        if ($request->isPost()) {
            $requestData = $request->getPost()->toArray();

            if ($requestData['category_to_refresh'] ?? false) {
                // delete category
//                die("a");
                $category->refresh(['cdiscount_category_id' => $requestData['category_to_refresh']]);
            } elseif ($requestData['category_to_delete'] ?? false) {
                // delete category
                $category->delete(['cdiscount_category_id' => $requestData['category_to_delete']]);
            } elseif ($uploadForm->setData($requestData) && $uploadForm->isValid()) {
                $categoryId = $uploadForm->get('category_id')->getValue();
                $categoryIds = Helper::extractRegularelySeparatedItemsFromString($categoryId);
                $category->processList($categoryIds);
            }
        }
        $categoryList = new CategoryList(1, 100, 100);
        $table = $categoryList->getTable($category->getCategoryList(), []);

        return new ViewModel([
            'form' => $uploadForm,
            'itemList' => $itemList,
            'filePath' => $filePath,
            'showItems' => $showItems,
            'table' => $table,
            'list' => $category->getCategoryList(),
            'message' => $category->msg->getStringMessages('<br />'),
            'errors' => $category->msg->getStringErrorMessages('<br />'),
        ]);
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

        $product = new \Parser\Model\Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $product->getLocalesForForm();

        /** @var Store $magento */
        $magentoListSelected = $this->params()->fromPost('magentoStore', []);

        $magento = new Store($this->db);
//        $magentoList = $magento->getOptionsArray($magentoListSelected);
        $magentoList = [];

        $autoPaging = $this->params()->fromPost('autoPaging', 0);
        $autoScrapeCategories = $this->params()->fromPost('autoScrapeCategories', 0);
        $customFields = $this->config->getConfig('customFields');
//        $uploadFormFields = $customFields['UploadForm'] ?? [];
        $uploadFormFields = [];

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
                                $category->add($item['url'], $itemData);
                            }
                        }
                    } catch (\Exception $e) {
                        Helper::logException($e, 'categoryCDiscount.error.log');
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

    public function listAction()
    {

        $category = new Category($this->config);

        $asin = $this->params()->fromQuery('asin', 'asin');
        $syncable = $this->params()->fromPost('syncable', ProductSyncable::SYNCABLE_PRESYNCED);
        $locale = '';

        $product = new \Parser\Model\Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $product->getLocalesForForm();

        /** @var Store $magento */
        $magentoListSelected = $this->params()->fromPost('magentoStore', []);
        $uploadFormFields = $customFields['UploadForm'] ?? [];

        $filterNew = $this->params()->fromPost('filter', []) + $this->params()->fromQuery('get', []);
        $resetFilter = $this->params()->fromPost('resetFilter', false);
        $filter = $category->getListFilter('cdiscount-category-list', $filterNew, $resetFilter);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $requestData = $request->getPost()->toArray();

            $list = $requestData['filter']['cdiscount_category_id'] ?? [];
            $deleteProducts = $requestData['delete-product'] ?? null;
            if ($list) {
                $list = array_keys($list);
            }
            if ($requestData['category_to_delete'] ?? false) {
                // delete category
                $res = $category->delete(['cdiscount_category_id' => $requestData['category_to_delete']]);
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
        $categoryList = new CategoryList(1, 100, 100, $this);
        $perPage = $filter['per-page'] ?? 100;
        $page = $filter['page'] ?? 1;
        $categoryItems = $category->getCategoryList($filter);
//        pr($categoryItems);die();
        $paging = new Paging($filter['page'], $category->getTotalResults(), $perPage);
        $pagingView = $paging->getAsHTML();
        $perPageSelect = $paging->getPerPageSelectorDropdown($perPage);
        // nasty look.
//        pr($filter);die();

        $categoryList->filterFields = ['status' => '<br>' . $category->getStatusDropdown($filter['status']),
            'profile' => '<br>possible profiles: ' . $category->getPossibleProfiles($filter) . '<br>' . $category->getSelectDropDown($filter['profile'] ?? null, $filter, 'profile'),
            'url' => $category->getUrlTableFilterFields($filter),
            'descritpion' => ''

        ];
        $table = $categoryList->getTable($categoryItems, [
            'scripts' => HtmlCategoryHelper::getSimpleOnchangeSubmit('filter-status', 'category-form')
                . HtmlCategoryHelper::getSimpleOnchangeSubmit('per-page', 'category-form')
                . HtmlCategoryHelper::getPagingScript('page-input', 'category-form'),
            'inputs' => HtmlCategoryHelper::getPageInput('page-input', 'filter[page]', $page)
        ]);

        if (($this->getRequest()->isPost() || $this->params()->fromQuery('resetFilter', '')) && !($requestData['filter-button'] ?? null)) {
            return $this->redirect()->toRoute('cdiscount', ['action' => 'list']);
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

    /**
     * @return \Laminas\Http\Response|ViewModel
     * @throws \yii\db\Exception
     */
    public function productsAction()
    {
        $product = new Product($this->config);
        $asin = $this->params()->fromQuery('asin', 'asin');
        $locale = $this->params()->fromQuery('locale');

        $amazonProduct = new \Parser\Model\Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $amazonProduct->getLocalesForForm();

        $filterNew = $this->params()->fromPost('filter', []) + $this->params()->fromQuery('get', []);
        $resetFilter = $this->params()->fromPost('resetFilter', false);
        $filter = $product->getListFilter('cdiscount-product-list', $filterNew, $resetFilter);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $requestData = $request->getPost()->toArray();

            $list = $requestData['filter']['cdiscount_category_id'] ?? [];

            if ($list) {
                $list = array_keys($list);
            }
            if ($requestData['category_to_delete'] ?? false) {
                // delete category
                $res = $product->delete(['cdiscount_category_id' => $requestData['category_to_delete']]);
                if ($res) {
                    $product->msg->addMessage('category has been removed');
                }
                $filter['page'] = 1;
            } else if ($requestData['category_to_refresh'] ?? false) {

                $product->refresh([$requestData['category_to_refresh']]);
            } else if ($requestData['refresh-all'] ?? false) {
                $product->refreshAll($filter);
            } else if ($requestData['delete-all'] ?? false) {
                $product->deleteAllProducts($filter);
                $filter = $product->getListFilter('cdiscount-product-list', $filterNew, 1);
            } else if ($requestData['refresh-selected'] ?? false) {
                if ($list) {
                    $product->refresh($list);
                    $product->msg->addMessage('refreshed ' . count($list) . ' categories');
                } else {
                    $product->msg->addError('no items selected');
                }

            } else if ($requestData['delete-selected'] ?? false) {
                if ($list) {
                    $product->deleteProducts($list);
                    $product->msg->addMessage('categories has been removed');
                } else {
                    $product->msg->addError('no items selected');
                }
            }
        }
        $productList = new ProductList(1, 100, 100, $this);
        $perPage = $filter['per-page'] ?? 100;
        $page = $filter['page'] ?? 1;
        $productItems = $product->getProductList($filter);
//        pr($productItems);die();
        $paging = new Paging($filter['page'], $product->getTotalResults(), $perPage);
        $pagingView = $paging->getAsHTML();
        $perPageSelect = $paging->getPerPageSelectorDropdown($perPage);

        $productList->filterFields = ['status' => '<br>' . $product->getStatusDropdown($filter['status']),
            'url' => $product->getUrlTableFilterFields($filter),
            'category' => $product->getCategoryFilterField($filter),
            'descritpion' => $product->getDescriptionFilterField($filter),

        ];
        $table = $productList->getTable($productItems, [
            'scripts' => HtmlCategoryHelper::getSimpleOnchangeSubmit('filter-status', 'category-form')
                . HtmlCategoryHelper::getSimpleOnchangeSubmit('per-page', 'category-form')
                . HtmlCategoryHelper::getPagingScript('page-input', 'category-form'),
            'inputs' => HtmlCategoryHelper::getPageInput('page-input', 'filter[page]', $page)
        ]);

        if (($this->getRequest()->isPost() || $this->params()->fromQuery('resetFilter', '')) && !($requestData['filter-button'] ?? null)) {
            return $this->redirect()->toRoute('cdiscount', ['action' => 'products']);
        }
        $view = new ViewModel([
            'table' => $table,
            'message' => $product->msg->getStringMessages('<br />'),
            'errors' => $product->msg->getStringErrorMessages('<br />'),
            'perPageSelect' => $perPageSelect,
        ]);
        $view->addChild($pagingView, 'paging');
        return $view;

    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function syncAction()
    {
        $asin = $this->params()->fromQuery('asin');
        $productId = $this->params()->fromQuery('id');
        $debugMode = $this->params()->fromQuery('debug');
        $cdSync = new Product\CdiscountSync($this->config);
        $cdSync->entity = new Product($this->config);
        $cdSync->entity->debugMode = $debugMode;
        if ($productId) {
            $where = [$cdSync->entity->getTableKey() => $productId];
        } elseif ($asin) {
            $where = ['asin' => $asin];
        } else {
            pr('please specify asin or product id');
            die();
        }
        $productData = $cdSync->load($where);
        if ($productData) {
            $cdSync->entity->scrapeCDiscount($productData);
            $productData = $cdSync->load($where);
            pr($cdSync->entity->msg->getStringErrorMessages());
            pr($cdSync->entity->msg->getStringMessages());
            pr($productData);
        } else {
            pr('no product found');
            die();
        }
        return $this->zeroTemplate();
    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function scrapeAmazonAction()
    {
        $productId = $this->params()->fromQuery('id');
        $debugMode = $this->params()->fromQuery('debug');
        $cdSync = new Product\CdiscountSync($this->config);
        $cdSync->entity = new Product($this->config);
        $cdSync->entity->debugMode = $debugMode;
        if ($productId) {
            $where = [$cdSync->entity->getTableKey() => $productId];
        } else {
            pr('please specify product id');
            die();
        }
        $productData = $cdSync->load($where);
        if ($productData) {
            $cdSync->entity->checkAmazonByEAN($productData);
            $productData = $cdSync->load($where);
            pr($productData);
        } else {
            pr('no product found');
            die();
        }
        return $this->zeroTemplate();
    }


    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function syncCategoryAction()
    {

        $categoryId = $this->params()->fromQuery('id');
        $this->config->setDebugMode($this->params()->fromQuery('debug'));
        $category = new Category($this->config);
        if ($categoryId) {
            $category->setStatus($category::STATUS_NEVER_CHECKED, $categoryId);
            $category->scrape($categoryId);
            pr($category->msg->getStringMessages());
            pr($category->msg->getStringErrorMessages());
        } else {
            pr('please specify category id');
            die();
        }
        return $this->zeroTemplate();
    }
}
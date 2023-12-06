<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 23:39
 */

namespace Comparator\Controller;

use Comparator\Model\Comparator\Category;
use Comparator\Model\Comparator\CategoryList;
use Comparator\Model\Comparator\Product;
use Comparator\Model\Comparator\ProductList;
use Comparator\Model\Form\CategoryForm;
use Comparator\Model\Form\SearchForm;
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
     * TODO change to product upload
     * @return ViewModel
     */
    public function searchAction(): ViewModel
    {
        $data = [];
        $product = new Product($this->config);

        $asin = $this->params()->fromQuery('asin', 'asin');
        $syncable = $this->params()->fromPost('syncable', ProductSyncable::SYNCABLE_PRESYNCED);
        $locale = $this->params()->fromPost('locale', '');
//        pr($locale);die();
        $amProduct = new \Parser\Model\Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $amProduct->getLocalesForForm();

        /** @var Store $magento */
        $customFields = $this->config->getConfig('customFields');
        $uploadFormFields = [];
        $uploadForm = new SearchForm('', ['uploadFormFields' => $uploadFormFields, 'locales' => $locales]);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $requestData = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            if (!($requestData['locale'] ?? null)) {
                $requestData['locale'] = 'ca';
            }
            $uploadForm->setData($requestData);
            if ($requestData['category_to_delete'] ?? false) {
                // delete category
                $product->delete(['amazon_category_id' => $requestData['category_to_delete']]);
            } elseif ($uploadForm->setData($requestData) && $uploadForm->isValid()) {
                $data = $uploadForm->getData();
                $fileErrors = $uploadForm->get('product_list')->getMessages();
                $tempFile = $uploadForm->get('product_list')->getValue();
                $eanList = $uploadForm->get('product_ean')->getValue();
                $upcList = $uploadForm->get('product_upc')->getValue();
                $items = [];
                if (!$tempFile['tmp_name'] && !$eanList && !$upcList) {
                    $product->msg->addError('No items or file specified');
                }
                if ($tempFile['tmp_name']) {
                    $items = $product->extractItemsFromFile($tempFile['tmp_name']);
                }
                if ($tempFile['tmp_name'] && $fileErrors) {
                    $product->msg->addError('failed to upload file ' . implode(',', $fileErrors));
                }

                if (!$product->msg->hasErrors()) {
                    // adding custom fields from the config, very nasty.
                    try {
                        $product->processNewProductData($items, $eanList, $upcList, $locale);
                    } catch (\Exception $e) {
                        $product->msg->addError($e->getMessage());
                        Helper::logException($e, 'uploadComparatorItems.error.log');
                    }
                }
            } else {
                $product->msg->addError($uploadForm->getMessages());
            }
        }

        return new ViewModel([
            'form' => $uploadForm,
            'items' => $data,
            'locales' => $locales,
            'locale' => $locale,
            'message' => $product->msg->getStringMessages('<br />'),
            'errors' => $product->msg->getStringErrorMessages('<br />'),
        ]);

    }

    /**
     * @return \Laminas\Http\Response|ViewModel
     * @throws \yii\db\Exception
     */
    public function listAction()
    {
        $product = new Product($this->config);
        $asin = $this->params()->fromQuery('asin', 'asin');
        $locale = $this->params()->fromQuery('locale');

        $amazonProduct = new \Parser\Model\Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $amazonProduct->getLocalesForForm();

        $filterNew = $this->params()->fromPost('filter', []) + $this->params()->fromQuery('get', []);
        $resetFilter = $this->params()->fromPost('resetFilter', false);
        $filter = $product->getListFilter('comparator-product-list', $filterNew, $resetFilter);
        $request = $this->getRequest();
        if ($request->isPost()) {
            $requestData = $request->getPost()->toArray();

            $list = $requestData['filter']['comparator_product_id'] ?? [];
            if ($list) {
                $list = array_keys($list);
            }
            if ($requestData['product_to_refresh'] ?? false) {
                $product->refresh([$requestData['product_to_refresh']]);
            } else if ($requestData['refresh-all'] ?? false) {
                $product->refreshAll($filter);
            } else if ($requestData['delete-all'] ?? false) {
                $product->deleteAllProducts($filter);
                $filter = $product->getListFilter('comparator-product-list', $filterNew, 1);
            } else if ($requestData['refresh-selected'] ?? false) {
                if ($list) {
                    $product->refresh($list);
                    $product->msg->addMessage('refreshed ' . count($list) . ' products');
                } else {
                    $product->msg->addError('no items selected');
                }

            } else if ($requestData['delete-selected'] ?? false) {
                if ($list) {
                    $product->deleteProducts($list);
                    $product->msg->addMessage('products has been removed');
                } else {
                    $product->msg->addError('no items selected');
                }
            }
        }
        $productList = new ProductList(1, 100, 100, $this);

        $table = $productList->getPageData($filter, $product);

        if (($this->getRequest()->isPost() || $this->params()->fromQuery('resetFilter', '')) && !($requestData['filter-button'] ?? null)) {
            return $this->redirect()->toRoute('comparator', ['action' => 'list']);
        }
        try {
            $keepa = $product->getKeepaObject();
            $apiKeyObfuscated = $keepa->getApiKeyObfuscated();
            $tokensLeft = $keepa->tokensLeft;
        } catch (\RuntimeException $e) {
            $product->msg->addError($e->getMessage());
            $apiKeyObfuscated = 'none';
            $tokensLeft = 'none';
        }
        $view = new ViewModel([
            'table' => $table,
            'tokensLeft' => $tokensLeft,
            'apiKey' => $apiKeyObfuscated,
            'message' => $product->msg->getStringMessages('<br />'),
            'errors' => $product->msg->getStringErrorMessages('<br />'),
            'perPageSelect' => $productList->perPageSelect,
        ]);
        $view->addChild($productList->pagingView, 'paging');
        return $view;

    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function showitemAction()
    {
        $productId = $this->params()->fromQuery('id');
        $cmpSync = new Product\ComparatorSync($this->config);
        $cmpSync->entity = new Product($this->config);
        if ($productId) {
            $where = [$cmpSync->entity->getTableKey() => $productId];
        } else {
            pr('please specify product id');
            die();
        }
        $productData = $cmpSync->load($where);
        if ($productData) {
            pr($productData);
        } else {
            pr('no product found');
        }
        return $this->zeroTemplate();
    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function scrapeKeepaAction()
    {
        $productId = $this->params()->fromQuery('id');
        $debugMode = $this->params()->fromQuery('debug');
        $cmpSync = new Product\ComparatorSync($this->config);
        $cmpSync->entity = new Product($this->config);
        $cmpSync->entity->debugMode = $debugMode;
        if ($productId) {
            $where = [$cmpSync->entity->getTableKey() => $productId];
        } else {
            pr('please specify product id');
            die();
        }
        $productData = $cmpSync->load($where);
        if ($productData) {
            try {
                $cmpSync->entity->scrapeKeepa($productData);
            } catch (\RuntimeException $e) {
                pr($e->getMessage());
                die();
            }
            $productData = $cmpSync->load($where);
            pr($cmpSync->entity->msg->getStringErrorMessages());
            pr($cmpSync->entity->msg->getStringMessages());
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
        $cmpSync = new Product\ComparatorSync($this->config);
        $cmpSync->entity = new Product($this->config);
        $cmpSync->entity->debugMode = $debugMode;
        if ($productId) {
            $where = [$cmpSync->entity->getTableKey() => $productId];
        } else {
            pr('please specify product id');
            die();
        }
        $productData = $cmpSync->load($where);
        if ($productData) {
            $cmpSync->entity->checkAmazonByEAN($productData);
            $productData = $cmpSync->load($where);
            pr($productData);
        } else {
            pr('no product found');
            die();
        }
        return $this->zeroTemplate();
    }

}
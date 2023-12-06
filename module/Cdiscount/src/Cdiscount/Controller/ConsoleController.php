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
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\ViewModel;
use Parser\Model\Amazon\Html\Helper as HtmlCategoryHelper;
use Parser\Model\Helper\ProcessLimiter;
use Laminas\Console\Request as ConsoleRequest;


/**
 * Class ConsoleController
 * @package Parser\Controller
 * @inheritdoc
 */
class ConsoleController extends AbstractController
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

    public function onDispatch(MvcEvent $e, $data = [])
    {
        $request = $this->getRequest();

        if ($request instanceof ConsoleRequest) {
            $verbose = $request->getParam('verbose') || $request->getParam('v') || $request->getParam('debug');
            $delay = $request->getParam('delay');
            $this->authActions = [];
        } else {
            $this->authActions = ['scrape', 'scrapeAmazon', 'scrapeProduct', 'scrapeKeepa'];
            $verbose = $request->getQuery('verbose') || $request->getQuery('v') || $request->getQuery('debug');
            $delay = $request->getQuery('delay') || $request->getQuery('d');
        }
        if ((int)$delay) {
            sleep((int)$delay);
        }
        $this->config->setProperty('DebugMode', $verbose);

        parent::onDispatch($e, $data);
    }

    /**
     * @return ViewModel||
     * @throws \Exception
     */
    public function scrapeAction(): ViewModel
    {
        $request = $this->getRequest();
        $category = new Category($this->config);
        $categoryId = $request->getParam('category');
        $processExpireDelay = $category->getConfig('processLimiter', 'processExpireDelay') ?: 240;
        $activeConnectionsLimit = $category->getConfig('processLimiter', 'activeConnectionsLimit') ?: 5;
        $regularSyncPath = $category->getConfig('processLimiter', 'processId') ?: 'cd';

        $limiter = new ProcessLimiter($this->config, [
            'path' => $regularSyncPath,
            'expireTime' => $processExpireDelay,
            'processLimit' => $activeConnectionsLimit,
        ]);
        $productsUpdated = [];
        $categoryData = [];
        $errors = '';
        if (($limiterID = $limiter->initializeProcess()) && $this->proxy->loadAvailableProxy()) {

            $category->setLimiter($limiter);
            try {
                $category->scrape($categoryId);
            } catch (\Exception $e) {
                Helper::logException($e, 'scrapeCD.error.log');
            }
            $limiter->delete(['process_limiter_id' => $limiterID]);
            $message = $category->msg->getStringMessages("\r\n");

            $errors = $category->msg->getStringErrorMessages("\r\n");
        } else {
            $message = 'Active Connections limit reached, try to start sync later';
            $categoryData = [];
        }


        $data = [
            'items' => $categoryData,
            'products' => $productsUpdated,
            'message' => $message,
            'errors' => $errors
        ];
        pr($data);
        return $this->scrapeTemplate($data);
    }


    public function scrapeAmazonAction()
    {
        $cProduct = new Product($this->config);

        $processExpireDelay = $cProduct->getConfig('processLimiter', 'processExpireDelay') ?: 240;
        $activeConnectionsLimit = $cProduct->getConfig('processLimiter', 'activeConnectionsLimit') ?: 5;

        $regularSyncPath = $cProduct->getConfig('processLimiter', 'processId') ?: 'cd_amazon_product';


        $limiter = new ProcessLimiter($this->config, [
            'path' => $regularSyncPath,
            'expireTime' => $processExpireDelay,
            'processLimit' => $activeConnectionsLimit,
        ]);
        $productsUpdated = [];
        $productsData = [];
        if (($limiterID = $limiter->initializeProcess()) && $this->proxy->loadAvailableProxy()) {
            $cProduct->setLimiter($limiter);
            try {
                $cProduct->scrapeAmazon();
            } catch (\Exception $e) {
                Helper::logException($e, 'scrapeCDAmazon.error.log');
            }
            $limiter->delete(['process_limiter_id' => $limiterID]);
            if ($productsData['status'] ?? null) {
                $message = 'scrape success.';
            } elseif ($productsUpdated) {
                $message = 'products found';
            } else {
                $message = 'no category found';
            }
        } else {
            $message = 'Active Connections limit reached, try to start sync later';
            $productsData = [];
        }
        $data = [
            'items' => $productsData,
            'products' => $productsUpdated,
            'message' => $message,
        ];
        return $this->scrapeTemplate($data);
    }


    public function scrapeProductAction()
    {

        $cProduct = new Product($this->config);
        $productsUpdated = [];
        $cdSync = new Product\CdiscountSync($this->config);

        if ($limiter = $cdSync->initialize($cProduct)) {
            $cProduct->setLimiter($limiter);
            try {
                $cdSync->sync($limiter);
                $message = $cdSync->getStringMessages(';');
            } catch (\Exception $e) {
                Helper::logException($e, 'scrapeCDproduct.error.log');
                $message = $e->getMessage();
            }
            $limiter->closeProcess();
        } else {
            $message = $cdSync->getStringErrorMessages();
        }
        pr($message);

        return $this->zeroTemplate();
    }

}
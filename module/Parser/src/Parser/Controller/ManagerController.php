<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 27.09.2017
 * Time: 15:23
 */

namespace Parser\Controller;


use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Form\UploadForm;
use Parser\Model\Helper\Condition\Admin;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\JsonHelper;
use Parser\Model\Html\Paging;
use Parser\Model\Magento\ProductToStore;
use Parser\Model\Magento\Store;
use Parser\Model\Product;
use Parser\Model\ProductCustom;
use Parser\Model\ProductDetails;
use Parser\Model\Profile;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\ProxyConnection;
use Parser\Model\Web\ProxySource\ProxyScraper;
use Parser\Model\Web\UserAgent;
use Westsworld\TimeAgo;
use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Plugin\ExceptionHandler;
use Laminas\Db\Sql\Where;
use Laminas\View\Model\ViewModel;

class ManagerController extends AbstractController
{
    public $profile;
    protected $config;
    private $db;
    private $container;
    private $proxy;
    private $userAgent;

    public function __construct(Config $config, $container)
    {
        $this->container = $container;
        $this->proxy = $this->container->get(Proxy::class);
        /**
         * @var $userAgent UserAgent
         */
        $userAgent = $this->container->get(UserAgent::class);
        $this->userAgent = $userAgent;
        $this->config = $config;
        $this->db = $config->getDb();
        $this->authActions = ['list', 'getstat', 'import', 'process', 'config', 'configLocale'];
    }

    public function indexAction()
    {
        $data = [
        ];
        return new ViewModel([
            'data' => $data,
        ]);
    }

    public function importAction()
    {
        $request = $this->getRequest();
        $data = [];
        $asin = $this->params()->fromQuery('asin', 'asin');
        $syncable = $this->params()->fromPost('syncable', ProductSyncable::SYNCABLE_YES);
        $locale = '';
        $product = new Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $product->getLocalesForForm();

        /** @var Store $magento */
        $magentoListSelected = $this->params()->fromPost('magentoStore', []);
        $magento = new Store($this->db);
        $magentoList = $magento->getOptionsArray($magentoListSelected);

        $customFields = $this->config->getConfig('customFields');
        $uploadFormFields = $customFields['UploadForm'] ?? [];
        $uploadForm = new UploadForm('', ['locales' => $locales, 'uploadFormFields' => $uploadFormFields]);

        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            $locale = $this->params()->fromPost('locale', '');;

            $uploadForm->setData($post);
            if ($uploadForm->isValid()) {
                $data = $uploadForm->getData();
                $fileErrors = $uploadForm->get('asins')->getMessages();
                $tempFile = $uploadForm->get('asins')->getValue();
                $list = $uploadForm->get('asins_list')->getValue();

                if (!$locale) {
                    $product->addError('No locale specified');
                }
                if (!$tempFile['tmp_name'] && !$list) {
                    $product->addError('Please specify the file or put asins into textarea');
                }
                if ($tempFile['tmp_name'] && $fileErrors) {
                    $product->addError('failed to upload file ' . implode(',', $fileErrors));
                }
                if (!$product->hasErrors()) {
                    $product = new Product($this->config, $this->proxy, $this->userAgent, $asin, $locale);
                    $asins = [];
                    if ($tempFile['tmp_name']) {
                        $asins = Helper::extractAsinsFromFile($tempFile['tmp_name']);
                    }
                    $asins_list = [];
                    if ($list) {
                        $asins_list = Helper::extractAsinsFromString($list);
                    }
                    $addOptions = ['syncable' => $syncable];

                    // adding custom fields from the config, very nasty.
                    if (!empty($uploadFormFields)) {
                        foreach ($uploadFormFields as $customField => $fieldSet) {
                            if ($value = $uploadForm->get($customField)->getValue()) {
                                $addOptions[$customField] = $value;
                            }
                        }
                    }

                    $asins = array_merge($asins, $asins_list);
                    $product->addMessage(count($asins) . ' Found for processing');
                    //print_r($asins);die();
                    $product->addNewProducts($asins, $addOptions);
                    // manage magento store associations
                    if (is_array($asins)) {
                        $where = new Where();
                        $whereAsin = [];
                        foreach ($asins as $asin) {
                            if (is_array($asin)) {
                                $whereAsin[] = trim($asin['asin']);
                            } else {
                                $whereAsin[] = trim($asin);
                            }
                        }
                        $where->in('asin', $whereAsin);
                        $where->equalTo('locale', $locale);
                        if ($magentoListSelected) {
                            ProductToStore::associateProducts($this->db, $where, $magentoListSelected);
                        }
                        // change sync status of existing products
                        $product->updateList($where, ['syncable' => $syncable]);
                    }

                }
            } else {
                $product->addError('please choose locale and file');
            }

        }
        $result = new ViewModel([
            'form' => $uploadForm,
            'items' => $data,
            'message' => $product->getStringMessages('<br />'),
            'errors' => $product->getStringErrorMessages('<br />'),
            'locales' => $locales,
            'syncableList' => ProductSyncable::getOptions(),
            'syncable' => $syncable,
            'storeList' => $magentoList,
            'locale' => $locale,
        ]);
        return $result;
    }


    public function listAction()
    {
        // get the list of products filtered and sorted
        $config = Helper::loadConfig('data/parser/config/config.xml');

        $product = new Product($this->config, $this->proxy, $this->userAgent, "", "ca");

        $profile = new Profile($this->db, $this->identity);
        $profile->load();
        $filter = $this->params()->fromPost('filter', []);
        $getFilter = $this->params()->fromQuery('get', []);
        $filterNew = $getFilter + $filter;
        $filter = $profile->loadConfigData('filter');
        if (isset($filter['products']) && $filter['products']) {
            unset($filter['products']);
        }
        if (!$this->params()->fromQuery('resetFilter', '')) {
            $filter = array_merge($filter, $filterNew);
        } else {
            $filter = $filterNew;
        }
        $custom = new ProductCustom($this->db);

        $localeList = $product->getLocales();
        $filter = Helper::prepareListFilter($filter, $localeList);

        $downloadCsv = $this->params()->fromPost('download_csv', '');
        $downloadCsv = $downloadCsv ?: $this->params()->fromQuery('download_csv', '');
        $action = $this->params()->fromPost('mass-actions', '');
        if ($productId = $this->params()->fromPost('product_id', '')) {
            // perform sync action
            $product->massSync(['product_id' => $productId]);
        } elseif ($action !== '') {
            // perform mass action or action
            //print_r($action);
            $whereProducts = $product->getProductIds($filter, $this->params()->fromPost('check_all', ''));
            switch ($action) {
                case 'enable':
                case 'disable':
                    $product->updateList($whereProducts, ['enabled' => $action === 'enable' ? 1 : 0]);
                    break;
                    break;
                case 'delete':
                    $product->deleteList($whereProducts);
                    break;
                case 'sync_selected' :
                    $product->massSync($whereProducts);
                    break;
                case 'mass_update' :
                    $massSyncStatus = $this->params()->fromPost('mass_syncable', '');
                    $magentoStoreList = $this->params()->fromPost('magentoStore', []);
                    $dropAssociations = $this->params()->fromPost('drop-associations', "");
                    if ($massSyncStatus) {
                        $dataToUpdate = ['syncable' => $massSyncStatus];
                        $dataSyncable = [
                            ProductSyncable::SYNCABLE_YES,
                            ProductSyncable::SYNCABLE_PREFOUND,
                            ProductSyncable::SYNCABLE_PRESYNCED,
                        ];
                        if (in_array($massSyncStatus, $dataSyncable)) {
                            $dataToUpdate['next_update_date'] = date('Y-m-d H:i:s', strtotime('-3 year'));
                        }
                        $product->updateList($whereProducts, $dataToUpdate);
                    }
                    if (count($magentoStoreList) || $dropAssociations) {
                        ProductToStore::associateProducts($this->db, $whereProducts, $magentoStoreList);
                    }
                    if ($this->params()->fromPost('mass_custom_attribute_change_enable', '')) {
                        // mass attributes update enabled
                        $custom = new ProductCustom($this->db);
                        $custom->massUpdate($this->getRequest()->getPost()->toArray(), $whereProducts);
                    }
                    break;

                default:
                    $dataToUpdate = ['syncable' => $action];
                    $dataSyncable = [
                        ProductSyncable::SYNCABLE_YES,
                        ProductSyncable::SYNCABLE_PREFOUND,
                        ProductSyncable::SYNCABLE_PRESYNCED,
                    ];
                    if (in_array($action, $dataSyncable)) {
                        $dataToUpdate['next_update_date'] = date('Y-m-d H:i:s', strtotime('-3 year'));
                    }
                    $product->updateList($whereProducts, $dataToUpdate);
                    break;
            }
        } elseif ($action = $downloadCsv) {
            // generate csv file
            $check_all = $this->params()->fromPost('check_all', '') ?:
                $this->params()->fromQuery('check_all', '');
            $fields = $this->params()->fromPost('fields', '') ?:
                $this->params()->fromQuery('fields', '');
            $format = $this->params()->fromPost('format', '') ?:
                $this->params()->fromQuery('format', 'csv');
            $whereProducts = $product->getProductIds($filter, $check_all);
            $filePath = $this->config->getSetting('csvFile');
            $filePath = Helper::generateCSVContent($this->db, $whereProducts, $filePath, $fields);
            if ($filePath) {
                return $this->redirect()->toUrl($filePath);
            } else {
                // add error - nothing to generate
                $this->getResponse()->setStatusCode(500);
                $this->getRequest()->setContent('Please choose products');
                return;
            }
        }

        if ($this->getRequest()->isPost() || $this->params()->fromQuery('resetFilter', '')) {
            $profile->updateData(['filter' => $filter]);
            // redirect in order to drop Post event.
            return $this->redirect()->toRoute('manager', ['action' => 'list']);
        }
        $productList = $product->getList(['filter' => $filter]);
        $timeAgo = new TimeAgo();

        $perPage = $filter['per-page'] ?: $config['settings']['productPerPage'];
        $paging = new Paging($filter['page'], $product->getProperty('TotalProducts'), $perPage);
        $pagingView = $paging->getAsHTML();

        $magento = new Store($this->db);
        $magentoList = $magento->getOptionsArray([]);


        $view = new ViewModel([
            'productList' => $productList,
            'filter' => $filter,
            'locales' => $localeList,
            'massActions' => Product::getMassActions(),
            'syncableList' => ProductSyncable::getOptions(),
            'storeList' => $magentoList,
            'timeAgo' => $timeAgo,
            'total' => $product->getProperty('TotalProducts'),
            'flags' => $custom->flags,
        ]);
        $view->addChild($pagingView, 'paging');
        return $view;
    }


    public function getstatAction()
    {
//        if(isset($this->config->storeConfig['home_page'])) {
//            $route = $this->config->storeConfig['home_page'];
//            $options = isset($route['action']) ? ['action' => $route['action']] : [];
//            return $this->redirect()->toRoute($route['route'], $options);
//        }
        $profile = $this->params()->fromQuery('profile');
        $this->config->addTimeEvent('start');

        $cache = new \Laminas\Cache\Storage\Adapter\Redis([
    'server' => [
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
    'ttl' => 1800, // tempo di vita della cache in secondi
]);
        $plugin = new ExceptionHandler();
        $plugin->getOptions()->setThrowExceptions(false);
        $cache->addPlugin($plugin);

        $this->config->addTimeEvent('initialized plugin');

        $key = 'sync24-cache';
        $sync24 = $cache->getItem($key, $success);
        if (!$success) {
            $productStat = Helper::getProductStat($this->config);
            $sync24 = $productStat->graph->sync24h;
            $cache->setItem($key, serialize($sync24));
            $this->config->addTimeEvent('generating stats without cache');
        } else {
            $productStat = Helper::getProductStat($this->config, false);
            $productStat->graph->sync24h = unserialize($sync24);
//            $productStat = Helper::getProductStat($this->config);
//            $cache->setItem($key, serialize($productStat->graph->sync24h));
            $this->config->addTimeEvent('got stats from cache');
        }
        $ps = new ProxyScraper($this->config);
        $scraperPremiumStats = $ps->getExpirationData();
        $view = new ViewModel([
            'productStats' => $productStat,
            'scraperPremiumStats' => $scraperPremiumStats,
        ]);


        $chartSync24 = new ViewModel([
            'data' => $productStat->graph->sync24h,
            'chartId' => 'chart_sync24',
        ]);
        $chartSync24->setTemplate('helper/chartsync24');
        $view->addChild($chartSync24, 'chartSync24');

        $chartSyncSpeed = new ViewModel([
            'data' => Helper::getProductSyncSpeed($this->db),
            'chartId' => 'chart_sync_speed',
        ]);
        $this->config->addTimeEvent('chart_sync_speed');
        $chartSyncSpeed->setTemplate('helper/chartsyncspeed');
        $view->addChild($chartSyncSpeed, 'chartSyncSpeed');
        $this->config->addTimeEvent('finish');
        if ($profile) {
            $view->setVariable('timeline', $this->config->getTimeLine());
        }

        return $view;
    }

    public function ajaxproxydataAction()
    {
        $pc = new ProxyConnection();
        $data = $pc->getStats($this->db);
        $totals = $pc->getTotals($data);
        $stats = $pc::getStatistics($this->db);

        $view = new ViewModel([
            'data' => $data,
            'totals' => $totals,
            'stats' => $stats,
        ]);
        $view->setTerminal(true);
        return $view;
    }

    public function ajaxproductcustomAction()
    {
        $id = $this->params()->fromQuery('id', '');
        $custom = new ProductCustom($this->db);
        if (!$id) {
            $data = [];
            $productData = [];
        } else {
            $request = $this->getRequest();
            if ($request->isPost()) {
                $custom->load($id);
                $dataToSave = $request->getPost()->toArray();
                //unset($dataToSave['aepridcs']);
                $dataToSave = $custom->processData($dataToSave);
                if ($custom->data) {
                    $custom->update($dataToSave, ['product_id' => $id]);
                } else {
                    $dataToSave['product_id'] = $id;
                    $custom->insert($dataToSave);
                }
            }

            $custom->load($id);
            $data = $custom->data;
            $product = new Product($this->config, $this->proxy, $this->userAgent);
            $product->loadById($id);
            $productData = $product->getProperties();
        }

        $view = new ViewModel([
            'data' => $data,
            'productId' => $id,
            'product' => $productData,
            'flags' => $custom->flags,
        ]);
        $view->setTemplate('ajax/product_custom');
        $view->setTerminal(true);
        return $view;
    }

    public function ajaxproductimageloadAction()
    {
        $id = $this->params()->fromQuery('id', '');
        if (!$id) {
            $data = "";
        } else {
            $product = new Product($this->config, $this->proxy, $this->userAgent);
            $product->loadById($id);
            $data = $product->getProperty('images');
            $list = explode("|", $data);
            if (count($list)) {
                $data = $list[0];
            } else {
                $data = "";
            }
        }
        $view = new ViewModel([
            'data' => $data,
        ]);
        $view->setTemplate('ajax/product_custom_image');
        $view->setTerminal(true);
        return $view;

    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function stockPatternAction(): ViewModel
    {
        $pd = new ProductDetails($this->config->logger);
        $locale = $this->params()->fromQuery('locale', 'ca');
        $stockDropDownCount = $this->params()->fromQuery('stockSelect', 0);
        $localeConfig = $this->config->getLocaleConfig($locale);


//        $stockHtml = 'In stock on June 20, 2020.';
//        $stock = $pd::getStock($stockHtml, $localeConfig['productPage']['stockTags'], $stockDropDownCount);
//        pr($stock);
//        die();
        //        $stockHtml = 'Only 4 left in stock.';
//        $stockHtml = 'Usually ships within 1 to 2 weeks.';


        $list = Helper::getAllStockStrings($this->config->getDb(), $locale);
        $htmlData = [];
        foreach ($list as $key => $item) {
            $stock = $pd::getStock($item['string'], $localeConfig['productPage']['stockTags'], $stockDropDownCount);
            $list[$key]['stock'] = $stock;
            $htmlStock = $stock ? '<span style="color:blue">' . $stock . '</span>' : '<span style="color:green">' . $stock . '</span>';
            $htmlData[] = '<tr><td>' . $item['qty'] . '</td><td>' . $htmlStock . '</td><td>' . $item['string'] . '</td></tr>';
        }

        $html = '<table><tr><td>qty of products</td><td>stock qty</td><td>stock string</td></tr>' . implode("\r\n", $htmlData) . '</table>';
        echo $html;
        return $this->returnZeroTemplate();
    }

    public function deliveryPatternAction(): ViewModel
    {
        $pd = new ProductDetails($this->config->logger);
        $locale = $this->params()->fromQuery('locale', 'ca');
        $localeConfig = $this->config->getLocaleConfig($locale);


//        $stockHtml = 'In stock on June 20, 2020.';
//        $stock = $pd::getStock($stockHtml, $localeConfig['productPage']['stockTags'], $stockDropDownCount);
//        pr($stock);
//        die();
        //        $stockHtml = 'Only 4 left in stock.';
//        $stockHtml = 'Usually ships within 1 to 2 weeks.';


        $list = Helper::getAllDeliveryStrings($this->config->getDb(), $locale);
        $htmlData = [];

        $pCountries = explode(',', $localeConfig['offersPage']['preferredCountry']);
//            pr($pCountries);
//            pr($deliveryText);
        $shipsFromTag = $localeConfig['offersPage']['shipsFromTag'] ?? '/Ships from ([A-Za-z\-, ]+)/';

        foreach ($list as $key => $item) {
            $check = Helper::getCountryDeliveryCheck($shipsFromTag, $item['string'], $pCountries);
            if (!$check) {
                $list[$key]['check'] = 'skipping merchant due to country delivery options';
            } else {
                $list[$key]['check'] = 'merchant is good';
            }

            $htmlCheck = $check ? '<span style="color:blue">pass</span>' : '<span style="color:red">fail</span>';
            $htmlData[] = '<tr><td>' . $item['qty'] . '</td><td>' . $htmlCheck . '</td><td>' . $item['string'] . '</td></tr>';
        }

        $html = '<table>
<tr><td colspan="3"> countries to check: '. implode(',', $pCountries).'</td></tr>
<tr><td>qty of products   </td><td>check    </td><td>delivery string</td></tr>' . implode("\r\n", $htmlData) . '</table>';
        echo $html;
        return $this->returnZeroTemplate();
    }

    public function processAction()
    {
        $filter = Helper::prepareListFilter([], []);
        $product = new Product($this->config, $this->proxy, $this->userAgent);
        $product->processProducts($filter);
        return $this->zeroTemplate();
    }

    /**
     * @return ViewModel
     */
    public function configAction(): ViewModel
    {
        if (!Admin::fire($this->config)) {
            return $this->returnMessageTemplate(['errors' => 'not authorized']);
        }
        JsonHelper::generateLocalJsonFromLocalXml('data/parser/config.json', 'data/parser/config/config.xml');

        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            $newConfig = $post['settings'];
            if (!$newConfig) {
                $this->config->addError('Empty settings field');
            } else {
                try {
                    $configData = json_decode($newConfig, 1);
                    Helper::saveConfig($configData, 'data/parser/config.local.json');
                    $this->config->addMessage('update success');
                } catch (\Exception $exception) {
                    $this->config->addError($exception->getMessage());
                }
            }
        }
        $config = Helper::loadConfig('data/parser/config.json', 'json');

//        $jsonArray = JsonHelper::prependJson($config);
        $jsonArray = $config;

//        $schema = JsonHelper::generateJsonSchemaFromConfig($config);
//        file_put_contents('data/parser/config-schema.json', json_encode($schema));

        $schema = ['type' => 'object', 'title' => 'General config', 'properties' => json_decode(file_get_contents('data/parser/config-schema.json'), 1)];
        $schemaJson = json_encode($schema);

        $layout = $this->layout();
        $scriptsTemplate = new ViewModel([

        ]);
        $templateForScripts = 'parser/manager/config-scripts';
        $scriptsTemplate->setTemplate($templateForScripts);
        $layout->addChild($scriptsTemplate, 'additionalScripts');


        return new ViewModel([
            'schema' => $schemaJson,
            'settings' => json_encode($jsonArray),
            'message' => $this->config->getStringMessages('<br />'),
            'errors' => $this->config->getStringErrorMessages('<br />'),
        ]);
    }

    public function configLocaleAction()
    {
        if (!Admin::fire($this->config)) {
            return $this->returnMessageTemplate(['errors' => 'not authorized']);
        }
        $locale = $this->params()->fromRoute('locale');
        $locales = $this->config->getLocales();
        if (!in_array($locale, $locales)) {
            return $this->returnMessageTemplate(['errors' => 'locale ' . $locale . ' not found in list, please add locale in the general settings']);
        }
        $xmlConfigFile = 'data/parser/config/profile/' . $locale . '.xml';
        $jsonConfigFile = 'data/parser/locales/' . $locale . '.json';
        $jsonSchemaConfigFile = 'data/parser/locales/' . $locale . '-schema.json';
        $jsonLocaleConfigFile ='data/parser/locales/' . $locale . '.local.json';
        JsonHelper::generateLocalJsonFromLocalXml($jsonConfigFile, $xmlConfigFile);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = $request->getPost()->toArray();
            $newConfig = $post['settings'];
            if (!$newConfig) {
                $this->config->addError('Empty settings field');
            } else {
                try {
                    $configData = json_decode($newConfig, 1);
                    Helper::saveConfig($configData, $jsonLocaleConfigFile);
                    $this->config->addMessage('update success');
                } catch (\Exception $exception) {
                    $this->config->addError($exception->getMessage());
                }
            }
        }
        $config = Helper::loadConfig($jsonConfigFile, 'json');
        $schema = ['type' => 'object', 'title' => $locale . ' locale settings', 'properties' => json_decode(file_get_contents($jsonSchemaConfigFile), 1)];
        $schemaJson = json_encode($schema);

        $layout = $this->layout();
        $scriptsTemplate = new ViewModel([

        ]);
        $templateForScripts = 'parser/manager/config-scripts';
        $scriptsTemplate->setTemplate($templateForScripts);
        $layout->addChild($scriptsTemplate, 'additionalScripts');


        return new ViewModel([
            'locale' => $locale,
            'schema' => $schemaJson,
            'settings' => json_encode($config),
            'message' => $this->config->getStringMessages('<br />'),
            'errors' => $this->config->getStringErrorMessages('<br />'),
        ]);


//        foreach($locales as $locale) {
//            $xmlConfigFile = 'data/parser/config/profile/'.$locale.'.xml';
//            $jsonConfigFile = 'data/parser/locales/'.$locale.'.json';
//            JsonHelper::generateJsonSchemaAndConfigFromXml($xmlConfigFile, $jsonConfigFile);
//        }

    }
}
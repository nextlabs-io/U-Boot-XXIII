<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 10.09.2018
 * Time: 21:04
 */

namespace Parser\Controller;


use function GuzzleHttp\Promise\all;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\Logger;
use Parser\Model\Html\Paging;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\Magento\Connector;
use Parser\Model\Magento\ProductToStore;
use Parser\Model\Magento\Request;
use Parser\Model\Magento\Store;
use Parser\Model\Profile;
use Parser\Model\Web\WebPage;
use Westsworld\TimeAgo;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;


class MagentoController extends AbstractController
{
    public $db;
    public $config;

    /**
     * MagentoController constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = $config->getDb();
        $this->authActions = ['list', 'update', 'delete', 'showRequestsLog', 'showLog'];
    }


    public function listAction(): ViewModel
    {
        $magento = new Store($this->db);
        $errors = [];
        $messages = [];
        $emptyForm = [
            'parser_magento_id' => '',
            'title' => '',
            'enable' => false,
            'magento_trigger_path' => '',
            'magento_trigger_key' => '',
            'delete_trigger' => false,
            'create_trigger' => false,
            'send_images' => false,
            'check_description' => false,
        ];
        $form = $this->params()->fromPost();
        $form = array_merge($emptyForm, $form);
        if ($this->params()->fromQuery('mode') === 'update') {
            $id = $this->params()->fromQuery('id');
            $magento->load($id);
            $form = array_merge($form, (array)$magento->data);
        }
        if ($this->params()->fromPost('submit')) {
            $data = $form;
            $id = $data['parser_magento_id'];
            unset($data['parser_magento_id'], $data['submit']);
            if (!$data['title']) {
                $errors[] = 'Please specify the title';
            }
            if (!$data['magento_trigger_path']) {
                $errors[] = 'Please specify the Magento path';
            }
            if (!$errors) {
                $data = $magento->processData($data);
                if ($id) {
                    // update
                    $magento->update($data, ['parser_magento_id' => $id]);
                } else {
                    // create
                    $data['enable'] = (bool)$data['enable'];
                    $magento->insert($data);
                }
            }
        }
        $list = $magento->getList();

        $result = new ViewModel(['list' => $list, 'update' => $form, 'errors' => implode(',', $errors)]);
        return $result;
    }

    public function deleteAction(): \Laminas\Http\Response
    {
        $id = $this->params()->fromQuery('id');
        $magento = new Store($this->db);
        $magento->delete(['parser_magento_id' => $id]);
        ProductToStore::removeAssociationByStoreId($this->db, $id);
        return $this->redirect()->toRoute('magento', []);
    }

    /**
     * Action to process magento requests.
     */
    public function processRequestsAction(): ViewModel
    {
        ini_set('ignore_user_abort', 1);
        // manual sync start in development stage
        $dataKey = $this->params()->fromQuery('key', '');
        if (!$dataKey) {
            die('no data key');
        }
        $storeId = $this->params()->fromQuery('store', '');
        $maxItems = $this->params()->fromQuery('items_qty', '');
        // comma delimeted ids
        $typeIdString = $this->params()->fromQuery('type', '');
        $allTypes = [Request::RequestDelete, Request::RequestUpdate, Request::RequestCreate, Request::RequestUpdateDescription];
        $allTypesWithoutUpdate = [Request::RequestDelete, Request::RequestCreate, Request::RequestUpdateDescription];
        if ($typeIdString) {
            // we got some typeIdInstructions
            $typeCandidates = explode(',', $typeIdString);
            // all possible types
            $types = array_intersect($allTypes, $typeCandidates);
        } else {
            // by default we do not take update requests anymore, they are handled by the magmi updater
            $types = $allTypesWithoutUpdate;
        }
        $requestQueue = new Request($this->db);
        $config = $this->config->getConfig('settings');

        $where = new Where();
        $where->in('type', $types);
        $magentoList = $requestQueue->getStoresInTheQueue($where);

        if ((int)$storeId && in_array((int)$storeId, $magentoList, true)) {
            // limit possible queries to specified store id
            $magentoList = [$storeId];
        }

        $magentoPathPrefix = 1000;
        if (count($types) === 1) {
            $magentoPathPrefix .= '33' . array_values($types)[0] . '33';
        }
        $maxProcessesToRun = $config['magentoProductSyncLimit'] ?? 100;

        if ($maxItems) {
            $maxProcessesToRun = $maxItems;
        }
        $gotProcess = false;
        pr('magento list');
        pr($magentoList);
        if (count($magentoList)) {
            // we have processes to process
            foreach ($magentoList as $storeID) {
                $limiter = new ProcessLimiter($this->config, [
                    'path' => $magentoPathPrefix . $storeID,
                    'expireTime' => $config['magentoProcessExpireDelay'] ?? 240,
                    'processLimit' => $config['MagentoRegularProcessLimit'] ?? 1,
                ]);
                if ($limiterID = $limiter->initializeProcess()) {
                    $gotProcess = true;
                    // good to go for processing.
                    $connector = new Connector($this->config, $storeID);
                    $count = 0;

                    pr('types');
                    pr($types);
                    pr('max_items_to process_' . $maxProcessesToRun);

                    try{
                        while ($infoLine = $connector->processRequestFromQueue($types)) {
                            $count++;
                            pr($count);
                            pr($infoLine);
                            $limiter->touchProcess($limiterID);
                            if ($count > $maxProcessesToRun) {
                                break;
                            }
                        }
                    }
                    catch (\Exception $e){
                        Helper::logException($e, 'magento.processRequest.error.log');
                        $data['message'] = 'fatal error occurred, see magento.processRequest.error.log';
                    }


                }
                $limiter->delete(['process_limiter_id' => $limiterID]);
            }
            if (!$gotProcess) {
                pr('process is already running');
            }
        } else {
            pr('no requests found');
        }
        // someone else got process

        $result = new ViewModel([]);
        $result->setTemplate('zero');
        $result->setTerminal(true);
        return $result;
    }

    /**
     * @return ViewModel
     * sends a json array to magmi connector, and if got positive response, message queue is emptied.
     */
    public function magmiUpdateAction(): ViewModel
    {
        ini_set('ignore_user_abort', 1);
        // manual sync start in development stage
        $dataKey = $this->params()->fromQuery('key', '');
        if (!$dataKey) {
            die('no data key');
        }
        // store id is a mandatory
        $storeId = $this->params()->fromQuery('store', '');

        $maxItems = $this->params()->fromQuery('items_qty', 250);

        // comma delimeted ids
        $data = [];

        $requestQueue = new Request($this->db);
        $config = $this->config->getConfig('settings');
        $where = ['type' => Request::RequestUpdate];
        $magentoList = $requestQueue->getStoresInTheQueue($where);
        $magentoPathPrefix = $config['MagentoMagmiLimiterPathPrefix'] ?? 1100;
        $processMaxLimit = $config['MagentoMagmiProcessLimit'] ?? 1;

        if (!(int)$storeId) {
            $data['message'] = 'store ID is a mandatory field, url format: magento/magmiUpdate?key=1&store=1';
        } else if (in_array((int)$storeId, $magentoList, true)) {
            // limit possible queries to specified store id
            $limiter = new ProcessLimiter($this->config, [
                'path' => $magentoPathPrefix . $storeId,
                'expireTime' => $config['magentoProcessExpireDelay'] ?? 240,
                'processLimit' => $processMaxLimit,
            ]);
            if ($limiterID = $limiter->initializeProcess()) {
                $connector = new Connector($this->config, $storeId);
                try{
                    $result = $connector->processMagmiRequest($maxItems);
                    $data['message'] = $result['message'];
                }
                catch (\Exception $e){
                    Helper::logException($e, 'magmiRequest.error.log');
                    $data['message'] = 'fatal error occurred, see magmiRequest.error.log';
                }


                $limiter->delete(['process_limiter_id' => $limiterID]);
            } else {
                $data['message'] = 'process limit exceeded';
            }
        } else {
            $data['message'] = 'no queries found for selected store';
        }
        $result = new JsonModel($data);
        return $result;
    }


    public function showRequestsLogAction(): ViewModel
    {
        $logger = new Logger($this->db, []);


        $where = new Where();
        $where->like('data', 'updateRequest Fail%')->or->like('data', 'createRequest Fail%');
        $list = $logger->getList($where);

        $request = new Request($this->db);
        $requestsListGrouped = $request->getRequestsCount();
        $createRequests = $request->getCreateRequestsList();
        pr($requestsListGrouped);
        pr($list);
        if (count($createRequests)) {
            pr('up to 1000 items to be created');
            pr($createRequests);
        }

        return $this->returnZeroTemplate();
    }

    public function showLogAction()
    {
        // get the list of items filtered and sorted
        //$config = Helper::loadConfig("data/parser/config/config.xml");

        $profile = new Profile($this->db, $this->identity);
        $profile->load();
        $filter = $this->params()->fromPost('filter', []);
        $getFilter = $this->params()->fromQuery('get', []);
        $filterNew = $getFilter + $filter;
        $filter = $profile->loadConfigData('filter-magento-log');
        $logger = new \Parser\Model\Magento\Logger($this->db);
        if (!$this->params()->fromQuery('resetFilter', '')) {
            $filter = array_merge($filter, $filterNew);
        } else {
            $filter = $filterNew;
        }

        $filter = $logger::prepareListFilter($filter);
        // change filter saved to db
        if ($this->getRequest()->isPost() || $this->params()->fromQuery('resetFilter', '')) {
            $profile->updateData(['filter-magento-log' => $filter]);
            // redirect in order to drop Post event.
            return $this->redirect()->toRoute('magento', ['action' => 'showLog']);
        }

        $productList = $logger->getList(['filter' => $filter]);

        $timeAgo = new TimeAgo();

        $perPage = $filter['per-page'] ?: 100;
        $paging = new Paging($filter['page'], $logger->totalItems, $perPage);
        $pagingView = $paging->getAsHTML();
        $perPageSelect = $paging->getPerPageSelectorDropdown($filter['per-page']);

        /** @var Store $magento */
        $magentoListSelected = $this->params()->fromPost('magentoStore', []);
        $magento = new Store($this->db);
        $magentoListDropDown = $magento->getDropDown([$filter['l.store_id']]);

        $view = new ViewModel([
            'productList' => $productList,
            'filter' => $filter,
            'timeAgo' => $timeAgo,
            'total' => $logger->totalItems,
            'actionList' => $logger->getActionListDropDown($filter),
            'magentoList' => $magentoListDropDown,
            'typeList' => $logger->getTypeDropdown($filter['type']),
            'actionListForItems' => array_keys(\Parser\Model\Magento\Logger::$actions),
            'perPageSelect' => $perPageSelect,
        ]);

        $view->addChild($pagingView, 'paging');

        return $view;

    }
}
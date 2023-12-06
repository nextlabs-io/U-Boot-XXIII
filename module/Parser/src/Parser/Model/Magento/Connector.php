<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 25.07.18
 * Time: 18:32
 */

namespace Parser\Model\Magento;


use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Helper\Config;
use Parser\Model\ProductCustom;
use Parser\Model\SimpleObject;
use Parser\Model\Web\WebPage;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class Connector extends SimpleObject
{
    public $content = [];
    public $instruction = [];
    /** @var Request $request */
    public $request;
    /** @var Logger $logger */
    public $logger;
    private $config;
    private $basePath;
    private $requestPass;
    private $deleteOnNotSyncable = false;
    private $createOnSyncable = false;
    private $sendImages = true;
    private $checkDescription = false;
    private $storeId;
    private $instantProcess;

    public function __construct(Config $config, $storeId)
    {
        $this->config = $config;
        $this->request = new Request($this->config->getDb());
        $store = new Store($this->config->getDb());
        $this->storeId = $storeId;
        $store->load($storeId);

        if ($store->data['enable']) {
            $this->basePath = $store->data['magento_trigger_path'];
            $this->requestPass = $store->data['magento_trigger_key'];
            $this->deleteOnNotSyncable = $store->data['delete_trigger'];
            $this->createOnSyncable = $store->data['create_trigger'];
            $this->sendImages = $store->data['send_images'];
            $this->checkDescription = $store->data['check_description'];
        }

        $this->logger = new Logger($this->config->getDb());
    }


    /**
     * @deprecated
     * @param $postFields
     * @return string
     * @throws \Exception
     */
    public static function saveDataFile($postFields): string
    {
        $content = serialize($postFields);
        $dataKey = random_int(1000000, 99999999) . '.d';
        $path = 'data/content/';
        file_put_contents($path . $dataKey, $content);
        return $dataKey;
    }

    public function setInstantProcessing($val): void
    {
        $this->instantProcess = (bool)$val;
    }

    /**
     * @param $productData
     * @return $this
     *
     */
    public function processSyncRequest($productData): self
    {
        $settings = $this->config->getConfig('settings');
        $deleteZeroPriced = $settings['magentoDeleteProductOnZeroPrice'] ?? 0;
        // delete zero priced products
        // consider excluding parent products from this list. (or deletion of parent products should be more complex, i.e. only when all simple products were deleted)
        $deleteZeroPricedFull = $productData['price'] <= 0 && $deleteZeroPriced && $this->deleteUnsyncable();
        // delete unSyncable products
        $deleteUnsyncable = (ProductSyncable::SYNCABLE_YES != $productData['syncable']) && $this->deleteUnsyncable();
        $loggerMessage = '';

        if ($this->isConnected()) {

            // connect urls are specified and we can interact with magneto
            // data = ['product_id' => null/or int, 'price', 'stock']
            $data = $this->pingRequest($productData['asin']);

            if (isset($data['product_id']) && $data['product_id']) {
                $loggerMessage .= 'request success. ';
                // we have a product
                // check if price/qty match and update if needed
                $data['stock'] = $data['stock'] ?? 0;
                $data['price'] = $data['price'] ?? 0;
                // delete product if it is not ProductSyncable::SYNCABLE_YES
                if ($deleteUnsyncable || $deleteZeroPricedFull) {
                    // the product should be deleted
                    $this->addRequestToQueue(Request::RequestDelete,
                        ['asin' => $productData['asin'], 'locale' => $productData['locale']]);
                    $loggerMessage .= 'Detected deletion requirement';
                    $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, $loggerMessage);
                } else {
                    if (isset($data['m2eproe'])) {
                        if ($this->deleteUnsyncable()) {
                            $this->addRequestToQueue(Request::RequestDelete,
                                ['asin' => $productData['asin'], 'locale' => $productData['locale']]);
                        }
                        $this->instruction = [
                            'syncable' => ProductSyncable::SYNCABLE_DELETED,
                            'sync_log' => $data['m2eproe'],
                        ];
                        $loggerMessage .= 'Detected m2epro delete instructions';
                        $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, $loggerMessage);
                        return $this;
                    }

                    // check if we need to update images
                    if (isset($productData['custom_images_send'])
                        && $productData['custom_images_send']) {
                        $this->addRequestToQueue(Request::RequestUpdateDescription, $productData);
                        $loggerMessage .= 'Detected images update requirement';
                        $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, $loggerMessage);

                        return $this;
                    }

                    // check if other attributes are not synced
                    $pingRequestOptions = $this->config->getConfig('pingRequest');
                    if (isset($pingRequestOptions['attributesToUpdate']) && is_array($pingRequestOptions['attributesToUpdate'])) {
                        foreach ($pingRequestOptions['attributesToUpdate'] as $code => $magentoCode) {
                            // skipping images attribute, we cannot check if they are changed;
                            if ($code === 'images') {
                                continue;
                            }
                            if ($data[$magentoCode] != $productData[$code]) {
                                $this->addRequestToQueue(Request::RequestUpdateDescription, $productData);
                                $loggerMessage .= 'Detected attribute change: ' . $code;
                                $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, $loggerMessage);
                                return $this;
                            }
                        }
                    }

                    // price/stock request added in the end, since other requests does all data update along with price/stock
                    if (($data['price'] != $productData['price'])
                        || (int)$data['stock'] !== (int)$productData['stock']) {
                        $loggerMessage .= 'Detected price or stock change';
                        $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, $loggerMessage);
                        $this->addRequestToQueue(Request::RequestUpdate, $productData);
                        return $this;
                    }
                    $loggerMessage .= 'No changes detected';
                    $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, $loggerMessage);
                }
            } elseif ((int)$productData['syncable'] === ProductSyncable::SYNCABLE_YES && $this->createSyncable() && !$deleteZeroPricedFull && is_array($data)) {
                // there is no such product
                // yes, the product should be created
                $loggerMessage .= 'Product not found in magento';
                $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, $loggerMessage);
                $this->addRequestToQueue(Request::RequestCreate, $productData);
            } elseif (is_array($data)) {
                $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, 'product not found and not allowed to make create request');
            } else {
                // something wrong has happened
                $this->logger->addPingRequestLog($productData['product_id'], $this->storeId, '', 'failed to process ping request', $data);
            }

        }
        return $this;
    }

    public function deleteUnsyncable()
    {
        return $this->deleteOnNotSyncable;
    }

    public function isConnected(): bool
    {
        return $this->basePath ? true : false;
    }

    public function pingRequest($asin, $locale = null): array
    {
        $url = $this->basePath . '?action=ping&asin=' . $asin . '&locale=' . $locale;

        $pingRequestOptions = $this->config->getConfig('pingRequest');
        if (is_array($pingRequestOptions)
            && isset($pingRequestOptions['attributesToUpdate'])
            && is_array($pingRequestOptions['attributesToUpdate'])) {
            foreach ($pingRequestOptions['attributesToUpdate'] as $code => $magentoCode) {
                if ($magentoCode === 'images') {
                    continue;
                }
                $url .= '&atu[]=' . $magentoCode;
            }
        }
        if (isset($pingRequestOptions['checkM2EProErrors']) && $pingRequestOptions['checkM2EProErrors']) {
            $url .= '&m2eproe=1';
        }

        $this->makeRequest($url);
        return $this->content;
    }

    /**
     * @param        $url
     * @param string $type
     * @param array $postFields
     * @return string
     *
     * Function sends complex data using post and gzcompress the data
     */

    private function makeRequest($url, $type = 'get', $postFields = [])
    {
        $settings = $this->config->getConfig('settings');

        $logData = $settings['logger'] ?? false;
        if ($this->requestPass) {
            $url .= '&pass=' . $this->requestPass;
        }
        $browser = new WebPage();
        $browser->setUrl($url);
        if ($logData) {
            pr($url);
        }
        if ($type === 'post') {
            $browser->setProperty('POST', 1);
            $browser->setProperty('PostFields', $postFields);
        }
        try {
            $browser->getContentFromWeb();
            $cInfo = $browser->getProperty('CurlInfo');
            $code = $cInfo['http_code'];
            if ((int)$code === 200) {
                $html = $browser->getContent();
                if ($logData) {
                    pr($html);
                }
                $html = self::removeBOM($html);
                $content = json_decode($html);
                if (!$content) {
                    //$this->addError('failed to parse response');
                    $this->config->logger->add('makeRequest-no-json', $url);
                    $this->config->logger->add('makeRequest-html', $html);
                    $this->addError($html);
                    $this->content = ['html' => $html];
                    return $html;
                }
                $this->content = (array)$content;
                if (isset($this->content['errors'])) {

                    $this->loadErrors($this->content['errors']);
                    $this->config->logger->add('req-err', implode(';', $this->content['errors']));
                }
                if (!isset($this->content['message'])) {

                    $this->addError('makeRequest zero message');
                    return $html;
                }
                return $this->content['message'];

            }
            if ($browser->_curlError) {
                $this->addError('makeRequest error: ' . $browser->_curlError);
                if ($logData) {
                    pr('makeRequest error: ' . $browser->_curlError);
                }
                return $browser->_curlError;
            } else {
                if ($logData) {
                    pr('makeRequest error with ' . $url);
                    pr($browser->getProperty('ResultHeader'));
                    pr($browser->getProperty('CurlInfo'));
                }
                $this->addError('makeRequest error with ' . $url);
                return 'makeRequest error with ' . $url;
            }
        } catch (\Exception $e) {
            if ($logData) {
                pr('makeRequest failure: ' . $e->getMessage());
            }
            $this->addError('makeRequest failure: ' . $e->getMessage());
            return $e->getMessage();
        }

    }

    public static function removeBOM($str = '')
    {
        if (strpos($str, pack('CCC', 0xef, 0xbb, 0xbf)) === 0) {
            $str = substr($str, 3);
        }
        return $str;
    }

    /** adding requests to a queue
     * @param $type
     * @param $productData
     * @return int
     */
    public function addRequestToQueue($type, $productData): int
    {
        // unique $requestTag = $asin .'-'. $locale // $type
        // it should be unique for update/create processes
        $requestTagChunks = [];
        if (isset($productData['asin'])) {
            $requestTagChunks[] = $productData['asin'];
        }
        if (isset($productData['locale'])) {
            $requestTagChunks[] = $productData['locale'];
        }
        if (count($requestTagChunks)) {
            $requestTag = implode('-', $requestTagChunks);
            $this->request->delete(['type' => $type, 'store_id' => $this->storeId, 'request_tag' => $requestTag]);
        } else {
            // probably delete request
            $requestTag = '';
        }
        $dataSet = [
            'type' => $type,
            'store_id' => $this->storeId,
            'data' => serialize($productData),
            'request_tag' => $requestTag,
            'created' => new Expression('NOW()'),
        ];
        if ($this->instantProcess) {
            // we have to process the request right now, instead of adding it to the queue.
            $this->processRequestFromQueue([], $dataSet);
            return 1;
        }
        return $this->request->insert($dataSet);

    }

    /**
     * get a single request line from queue and process it.
     * @param array $types
     * @param array $item
     * @return bool
     */
    public function processRequestFromQueue($types = [], $item = []): bool
    {
        // when connector is initialized, we define the store, therefore, we need to get requests related to this store.
        // if request failed, we do not delete it, but change the failed_state
        $this->clearErrors();
        if (empty($item)) {
            $sql = new Sql($this->request->getAdapter());
            $where = new Where();
            $where->equalTo('store_id', $this->storeId)
                ->equalTo('failed_state', false);
            if (is_array($types) && count($types)) {
                $where->in('type', $types);
            }
            $select = $sql->select($this->request->getTable())
                ->where($where)
                ->order('parser_magento_request_id ASC')
                ->limit(1);
            $stmt = $sql->prepareStatementForSqlObject($select);
            $result = $stmt->execute();
//        $result = $this->request->select(['store_id' => $this->storeId, 'failed_state' => 0]);
            $item = $result->current();
        }
        if ($item && $item['data']) {
            $data = unserialize($item['data']);
            $type = (int)$item['type'];
            if ($type === Request::RequestCreate) {
                $this->createRequest($data);
            } elseif ($type === Request::RequestUpdate || $type === Request::RequestUpdateDescription) {
                $this->updateRequest($data);
            } elseif ($type === Request::RequestDelete) {
                $this->deleteRequest($data);
            }
        } else {
            // no processes left
            return false;
        }
        // request_tag
        if (isset($item['parser_magento_request_id'])) {
            $this->request->delete(['parser_magento_request_id' => $item['parser_magento_request_id']]);
        }

        if ($this->hasErrors()) {
            // process failed, set failed_state TODO do something with failed requests,
            if (isset($item['parser_magento_request_id'])) {
                $this->request->update(['failed_state' => true, 'process_log' => $this->getStringErrorMessages()],
                    ['parser_magento_request_id' => $item['parser_magento_request_id']]);
            }
            return $item['request_tag'] . ' with errors ' . $this->getStringErrorMessages();
        }
        // everything is fine, delete request from the queue.
        // TODO we can track here how long the process takes time etc.
        // do not delete for debug

        return $item['request_tag'];
    }

    /**
     * !NOTE data is by default - the product table field set, if you need to process some other attributes, they have to be added separately
     * @param $data array of the product
     * @return bool
     */
    private function createRequest($data): bool
    {

        if (!$this->checkPath()) {
            return false;
        }
        $url = $this->basePath . '?action=create';
        if (!$this->sendImages) {
            unset($data['images']);
        }
        $msg = $this->makeRequest($url, 'post', $data);
        if ($msg === 'create success') {
            if (isset($data['custom_images_send'])
                && $data['custom_images_send']) {
                $custom = new ProductCustom($this->config->getDb());
                $custom->resetImagesSendFlag($data['product_id']);
            }
            $this->logRequest('create', $data, 'create success');
            return true;
        }
        if ($this->hasErrors()) {
            $this->logRequest('create', $data, '', $msg);
//            $this->config->logger->add($data['asin'], 'createRequest Fail ' . $msg . ' ' . $this->getStringErrorMessages());
        } else {
            $this->logRequest('create', $data, '', $msg, $this->content);
//            $this->config->logger->add($data['asin'], 'createRequest Fail' . $msg);
        }
        return false;
    }

    /**
     * @return bool
     */
    private function checkPath()
    {
        if (!$this->basePath) {
            $this->addError('no control path specified in the settings');
            return false;
        }
        return true;
    }

    public function logRequest($type, $data, $msg = '', $error = '', $description = '')
    {
        /**
         *     public static $actions = [
         * 'ping' => 'check product stock and price (and other data) within magento',
         * 'update' => 'update price/stock using magmi',
         * 'updateDesc' => 'update description fields',
         * 'create' => 'create product',
         * 'delete' => 'delete product',
         * ];
         */
        $action = array_search($type, array_keys(Logger::$actions), true);
        if (!empty($description) && is_array($description)) {
            $description = serialize($description);
        }
        $dataToSave = ['action' => $action,
            'message' => $msg,
            'error' => $error,
            'store_id' => $this->storeId,
            'description' => $description,
            'product_id' => $data['product_id']];
//        pr($dataToSave);
        return $this->logger->add($dataToSave);

    }

    private function updateRequest($data)
    {
        if (!$this->checkPath()) {
            return false;
        }
        $price = $data['price'] ?? 0;
        $qty = $data['stock'] ?? 0;
        $asin = $data['asin'] ?? 0;
        $locale = $data['locale'] ?? 0;
        if ($asin && $locale) {
            $url = $this->basePath . '?action=update&asin=' . $asin;
            $url .= '&locale=' . urlencode($locale);
            $url .= '&stock=' . urlencode($qty);
            $url .= '&price=' . urlencode($price);

            $pingRequestOptions = $this->config->getConfig('pingRequest');
            if (isset($pingRequestOptions['attributesToUpdate']) && is_array($pingRequestOptions['attributesToUpdate'])) {
                foreach ($pingRequestOptions['attributesToUpdate'] as $code => $magentoCode) {
                    if ($code === 'images') {
                        if (isset($data['custom_images_send'])
                            && $data['custom_images_send']) {
                            $url .= '&' . $code . '=' . urlencode($data[$code]);
                        }
                    } elseif ($code === 'category') {
                        $url .= '&' . $code . '=' . urlencode($data[$code]);
                    } else {
                        $url .= '&atu[' . $magentoCode . ']=' . urlencode($data[$code]);
                    }
                }
            }

            $msg = $this->makeRequest($url);
            switch ($msg) {
                case 'update success':
                    // good case
                    if (isset($data['custom_images_send'])
                        && $data['custom_images_send']) {
                        $custom = new ProductCustom($this->config->getDb());
                        $custom->resetImagesSendFlag($data['product_id']);
                    }
                    $this->logRequest('updateDesc', $data, 'update success');
                    return true;
                    break;
                case 'no asin found or key is invalid':
                    // create product
                    $conf = $this->config->getConfig();
                    if (isset($conf['settings']['magentoCreateProductOnMissing']) && $conf['settings']['magentoCreateProductOnMissing']) {
                        return $this->createRequest($data);
                    }
                    $this->logRequest('updateDesc', $data, '', 'no asin found or key is invalid');
                    return false;
                    break;
                default:
                    if ($msg) {
                        $this->config->logger->add($asin, 'updateRequest Fail' . $msg);
                        $this->addError($msg);
                        $this->logRequest('updateDesc', $data, '', $msg);
                    }
                    return false;
                    break;
            }
        } else {
            $this->logRequest('updateDesc', $data, '', 'no asin or locale for update');
            $this->addError('no asin or locale for update');
        }
        return false;

    }

    public function deleteRequest($data)
    {
        if (!$this->checkPath()) {
            return false;
        }
        if (isset($data['asin'])) {
            $url = $this->basePath . '?action=delete&asin=' . $data['asin'] . '&locale=' . $data['locale'];
            $msg = $this->makeRequest($url);
            $this->logRequest('delete', $data, $msg);
        } elseif (isset($data['list']) && count($data['list'])) {
            // deleting list of asins
            // $list = [[asin=>, locale=>],[asin=>, locale=>]...]
            $url = $this->basePath . '?action=delete';
            $msg = $this->makeRequest($url, 'post', $data['list']);
            $this->logRequest('delete', $data, $msg);
        } else {
            return 'no items found for deletion';
        }
        return $msg;
    }

    public function createSyncable()
    {
        return $this->createOnSyncable;
    }

    public function processMagmiRequest($limit = 250): array
    {
        if (!$this->checkPath()) {
            return ['message' => 'no store path specified'];
        }
        $this->clearErrors();
        $sql = new Sql($this->request->getAdapter());
        $where = new Where();
        $where->equalTo('store_id', $this->storeId)
            ->equalTo('failed_state', false)
            ->equalTo('type', Request::RequestUpdate);
        $select = $sql->select($this->request->getTable())
            ->where($where)
            ->order('parser_magento_request_id ASC')
            ->limit($limit);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while ($item = $result->current()) {
            $data = unserialize($item['data']);
            $dataKeys = ['asin' => '', 'locale' => '', 'price' => '', 'stock' => ''];
            $list[$item['parser_magento_request_id']] = array_intersect_key($data, $dataKeys);
            $result->next();
        }
        if (count($list)) {
            $chunks = array_chunk($list, 200, true);
            $totalMessage = '';
            foreach ($chunks as $chunk) {
                $url = $this->basePath . '?action=magmi_update';
                $msg = $this->makeRequest($url, 'post', ['products' => $chunk]);
                if (isset($this->content['message'])) {
                    $msg = $this->content['message'];
                }
                $totalMessage .= $msg;
                if (strpos($msg, 'Skus imported OK:') !== false) {
                    // need to delete the items
                    $requestIdsToDelete = array_keys($chunk);
                    $where = new Where();
                    $where->in('parser_magento_request_id', $requestIdsToDelete);
                    $this->request->delete($where);
                    $this->logRequest('update', ['product_id' => 0], $msg, '', $chunk);
                } else {
                    // probably something went wrong, we need to indicate this
                    $this->config->logger->add('MagmiUpdate', $msg);
                    $this->logRequest('update', ['product_id' => 0], '', $msg, $chunk);
                }
            }
            return ['message' => $totalMessage];
        }
        return ['message' => 'no items found to process'];
    }
}
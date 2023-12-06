<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 24.05.2020
 * Time: 10:57
 */

namespace Parser\Model;


use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\Db\Sql\Predicate\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class ProductSync extends SimpleObject
{
    /**
     * @var Config
     */
    private $config;
    /**
     * @var Sql
     */
    private $sql;

    /* @var $proxy Proxy */
    private $proxy;
    /* @var $userAgent UserAgent */
    private $userAgent;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->sql = new Sql($this->config->getDb());
        $this->userAgent = new UserAgent($this->config->getDb());
        $this->proxy = new Proxy($this->config->getDb(), $config);
    }


    /**
     * @param ProcessLimiter $limiter
     * @return array
     * @throws \Exception
     */
    public function cronSyncProducts(ProcessLimiter $limiter): array
    {
        $limiterID = $limiter->getLimiterId();
        $syncables = [
            ProductSyncable::SYNCABLE_YES,
            ProductSyncable::SYNCABLE_PREFOUND,
            ProductSyncable::SYNCABLE_PRESYNCED,
        ];
        $settings = $this->config->getConfig('settings');
        // how much time the product is treated as up to date
        $productSyncDelay = $settings['productSyncDelay'] ?? 18000;

        // how many products to sync per process
        $productSyncLimit = $settings['productSyncLimit'] ?? 10;

        // process not expired products if main queue is empty
        $processNotExpiredProductsOnEmptyQueue = $settings['processNotExpiredProductsOnEmptyQueue'] ?? false;
        $syncedProducts = [];
        $list = $this->fetchResultObjectOfProducts($syncables, $productSyncLimit, $limiter, $processNotExpiredProductsOnEmptyQueue);
        foreach ($list as $key => $item) {
//            pr($processNotExpiredProductsOnEmptyQueue);
//            pr($item);
//                        $limiter->delete(['process_limiter_id' => $limiterID]);
//            die();


//            $product = new Product($this->config, $this->proxy, $this->userAgent);
            // setting products sync flag to yes. UPD already done in fetchResultObjectOfProducts()
            // we have an item to sync
            $asin = $item['asin'];
            $locale = $item['locale'];

            $product = new Product($this->config, $this->proxy, $this->userAgent, $asin, $locale);
            if (!$product->hasErrors()) {
                $product->loadFromArray($item);
                $product->sync(false);
                $syncedProducts[] = ['asin' => $asin, 'modified' => $item['modified'],
                    'messages' => $product->getStringMessages(), 'errors' => $product->getStringErrorMessages()];

            } else {
                // no proxy available, we do not update product, just set it back to non sync state
                sleep(10);
                $syncedProducts[] = [
                    'asin' => $item['asin'],
                    'message' => $product->getStringErrorMessages(),
                ];
                $this->proxy->clearErrors();
            }
            $this->removeRegistration(['product_id' => $item['product_id']]);

            pr($asin);
            $touched = $limiter->touchProcess($limiterID, $item['asin']);
            if (!$touched) {
                // process were not found.
                // release all products which were not synced
                $this->removeRegistration(['process_id' => $limiterID]);
                break;
            }


        }
        $this->addMessage('synced : ' . count($syncedProducts) . ' products');
        return $syncedProducts;
    }

    /**
     * @param array $syncables
     * @param integer $productSyncLimit
     * @param ProcessLimiter $limiter
     * @param bool $processNotExpiredProducts
     * @return array|mixed
     */
    public function fetchResultObjectOfProducts($syncables, $productSyncLimit, $limiter, $processNotExpiredProducts = false)
    {

        // we need here to select a product with making it sync_flag=true
        // NOT sure how to implement this using zend db orm
        $listOFBeingSynced = $this->getRegisteredProducts();

        $select = $this->sql->select('product');
        $where = new Where();
        $where->in('syncable', $syncables);
        if($listOFBeingSynced) {
            $where->notIn('product_id', $listOFBeingSynced);
        }
        if (!$processNotExpiredProducts) {
            $where->lessThan('next_update_date', new Expression('NOW()'));
        }
        $select->limit($productSyncLimit)
            ->where($where)
            ->order('next_update_date ASC');
        $stmt = $this->sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];

        while ($item = $result->current()) {
            $result->next();
            $productId = $item['product_id'];
            if ($this->registerProduct($productId, $limiter->getLimiterId())) {
                // if succeed, we add it for schedule in this process
                $list[] = $item;
            }
        }
//        Sample code, where update is perfomed to a trigger value before select
//        $query = 'UPDATE product SET sync_flag = 1, modified = NOW(), product_id = LAST_INSERT_ID(product_id)
//WHERE sync_flag=0 AND syncable IN(' . implode(',', $syncables) . ')';
//
//        if (!$processNotExpiredProducts) {
//            $query .= ' AND next_update_date < NOW()';
//        }
//
//        $query .= ' ORDER BY next_update_date ASC LIMIT 1';
//
//        $stmt = $sql->getAdapter()->getDriver()->createStatement();
//        $stmt->setSql($query);
//        $result = $stmt->execute();
//        $productId = $result->getGeneratedValue();
//        if ($productId) {
//            $select = $this->sql->select('product');
//            $where = new Where();
//            $where->equalTo('product_id', $productId);
//            $select->limit(1)->where($where);
//            $stmt = $this->sql->prepareStatementForSqlObject($select);
//            $result = $stmt->execute();
//            return $result->current();
//        }
        return $list;
    }

    public function getRegisteredProducts()
    {
        $select = $this->sql->select('product_sync');
        $stmt = $this->sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while ($item = $result->current()) {
            $result->next();
            $list[] = $item['product_id'];
        }
        return $list;
    }

    public function registerProduct($productId, $processId)
    {
//        pr($productId);
        // we need to reduce a load to a product table. therefore we keep product ids of products being synced in a separate table.
        $fields = ['product_id' => $productId, 'process_id' => $processId, 'created' => new Expression('NOW()')];
        $insert = $this->sql->insert('product_sync')->values($fields);
        $stmt = $this->sql->prepareStatementForSqlObject($insert);
        try {
            return $stmt->execute();
        } catch (\Exception $e) {
            //most likely duplicate entry
        }

        return false;

    }

    /**
     * @param $where
     * @return \Laminas\Db\Adapter\Driver\ResultInterface
     */
    public function removeRegistration($where): \Laminas\Db\Adapter\Driver\ResultInterface
    {
        $del = $this->sql->delete('product_sync');
        $del->where($where);
        $stmt = $this->sql->prepareStatementForSqlObject($del);
        return $stmt->execute();
    }

    /**
     * @return \Laminas\Db\Adapter\Driver\ResultInterface
     */
    public function cleanOldRegistered(): \Laminas\Db\Adapter\Driver\ResultInterface
    {
        $where = new Where();
        $where->lessThan('created', new Expression('DATE_SUB(NOW(), INTERVAL 1 HOUR)'));
        return $this->removeRegistration($where);
    }
}
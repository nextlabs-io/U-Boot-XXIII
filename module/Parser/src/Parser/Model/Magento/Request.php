<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 10.01.2019
 * Time: 13:45
 */

namespace Parser\Model\Magento;


use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

/**
 * Class Request designed to work with magento requests queue, add items to queue, process items.
 * @package Parser\Model\Magento
 */
class Request extends TableGateway
{
    public const RequestDelete = 1;
    public const RequestUpdate = 2;
    public const RequestCreate = 3;
    // TODO add this type of requests in order to keep images updating
    public const RequestUpdateDescription = 4;
    public static $tableKey = 'parser_magento_id';
    public $lastInsertValue;
    public $data;
    public $config;
    public $fields = [
        'created',
        'type',
        'store_id',
        'data',
        'failed_state',
        'process_log',
    ];

    public function __construct($db)
    {
        $table = 'parser_magento_request';
        parent::__construct($table, $db);
    }

    /**
     * get the total number of store ids in the request queue. We need it in order to define how many processes we can run sending requests to magento. We can run a process per store.
     * @param null|array|Where $where
     * @return array
     */
    public function getStoresInTheQueue($where = null): array
    {
        $db = $this->getAdapter();
        $sql = new Sql($db);
        $select = $sql->select($this->getTable())->group('store_id')->columns(['store_id']);
        if ($where) {
            $select->where($where);
        }
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while ($store = $result->current()) {
            $list[] = (int)$store['store_id'];
            $result->next();
        }
        return $list;
    }

    /**
     * process data before insert/update, remove fields which are not in the database
     * @param $data
     * @return array
     */
    public function filterData($data): array
    {
        $output = [];
        foreach ($this->fields as $field) {
            if (isset($data[$field])) {
                $output[$field] = $data[$field];
            }
        }
        return $output;
    }

    /**
     * @param int $period
     * Delete old requests, which are older than 2 days, todo add product id to the request, so to clear duplicate requests
     */
    public function deleteOldRequests($period = 2): void
    {
        if ((int)$period > 0) {
            $where = new Where();
            $where->lessThan('created', new Expression('DATE_SUB(NOW(), INTERVAL ' . (int)$period . ' DAY)'));
            $this->delete($where);
        }
    }

    public function getRequestsCount(): array
    {
        $sql = new Sql($this->getAdapter());
        $select = $sql->select($this->getTable());
        $select->columns(['type' => 'type', 'qty' => new Expression('COUNT(*)')])->group('type');
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while ($item = $result->current()) {
            if ((int)$item['type'] === self::RequestCreate) {
                $list['create requests'] = $item['qty'];
            } elseif ((int)$item['type'] === self::RequestUpdate) {
                $list['update requests'] = $item['qty'];
            } elseif ((int)$item['type'] === self::RequestUpdateDescription) {
                $list['update description requests'] = $item['qty'];
            } elseif ((int)$item['type'] === self::RequestDelete) {
                $list['delete requests'] = $item['qty'];
            }

            $result->next();
        }
        return $list;

    }

    /**
     * get a list of
     * @return array
     */
    public function getCreateRequestsList(): array
    {
        $sql = new Sql($this->getAdapter());
        $select = $sql->select($this->getTable());

        $where = new Where();
        $where->equalTo('type', self::RequestCreate);
        $select->columns(['locale_asin' => 'request_tag', 'store_id' => 'store_id']);
        $select->where($where)->order('store_id ASC')->limit(1000);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while ($item = $result->current()) {
            $list['store-' . $item['store_id']][] = $item['locale_asin'];
            $result->next();
        }
        return $list;

    }
}
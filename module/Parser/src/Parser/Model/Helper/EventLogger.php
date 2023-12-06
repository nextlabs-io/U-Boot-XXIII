<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 15.08.18
 * Time: 22:31
 */

namespace Parser\Model\Helper;

use Parser\Model\SimpleObject;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class EventLogger extends SimpleObject
{
    public CONST LOGGER_TABLE = 'event_log';
    public CONST LOGGER_TABLE_ID = 'event_log_id';
    public CONST PRODUCT_PRICE_UPDATE = 1;
    public CONST PRODUCT_STOCK_UPDATE = 2;
    public CONST PRODUCT_SYNC = 3;
    protected $db;
    protected $config;

    public function __construct($db, $config)
    {
        $this->config = $config;
        $this->db = $db;
    }

    /**
     * @param     $db
     * @param int $daysAfter
     * @return \Laminas\Db\Adapter\Driver\ResultInterface
     */
    public static function deleteOldEvents($db, $daysAfter = 4): \Laminas\Db\Adapter\Driver\ResultInterface
    {
        $sql = new Sql($db);
        $where = new Where();
        $created = date('Y-m-d H:i:s', strtotime('-' . (int)$daysAfter . ' days'));
        $where->lessThan('created', $created);

        $del = $sql->delete(self::LOGGER_TABLE)
            ->where($where);
        $stmt = $sql->prepareStatementForSqlObject($del);
        return $stmt->execute();
    }

    public function add($type, $msg, $timeSpent = null): \Laminas\Db\Adapter\Driver\ResultInterface
    {
        if (strlen($msg) > 128) {
            $msg = substr($msg, 0, 128);
        }
        $sql = new Sql($this->db);
        $insert = $sql->insert(self::LOGGER_TABLE)
            ->values(['event_type' => $type, 'event_log' => $msg, 'time_spent' => $timeSpent, 'created' => new Expression('NOW()')]);
        $stmt = $sql->prepareStatementForSqlObject($insert);
        return $stmt->execute();
    }

    /**
     * @param null $type
     * @param int $days
     * @return array
     * get count of events for 24 hours.
     */
    public function getStat($type = null, $days = 1): array
    {
        // TODO it takes too slow to get stats when created condition is set. we old delete events, think of moving it to an archive table.
        //self::deleteOldEvents($this->db, $days);
        $startTime = microtime(true);
        $sql = new Sql($this->db);
        $where = new Where();
        $where->greaterThan('created', date('Y-m-d H:i:s', strtotime('- '.((int) $days ?: 1).' days')));
        if ($type) {
            $where->equalTo('event_type', $type);
        }
        $select = $sql->select(self::LOGGER_TABLE)
            ->columns(['qty' => new Expression('COUNT(*)'), 'event_type' => 'event_type'])
            ->group('event_type')
            ->where($where);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [self::PRODUCT_PRICE_UPDATE => 0, self::PRODUCT_STOCK_UPDATE => 0, self::PRODUCT_SYNC => 0];
        while ($res = $result->current()) {
            $list[$res['event_type']] = $res['qty'];
            $result->next();
        }
        $endTime = microtime(true);
        $timeDelta = (int)(1000 * ($endTime - $startTime));
        $this->addMessage('getStats() time taken(ms):' . $timeDelta);
        return $list;
    }

    /**
     *
     * @return array
     */
    public function getGridData(): array
    {
        $sql = new Sql($this->db);
        $data = [];
        $interval = 30;
        $query = 'SELECT CONCAT(DATE_FORMAT(created, "%d-%H "), (date_format(created, "%i") DIV ' . $interval . ') + 1)  as `interval`, 
count(event_log_id) as qty FROM `event_log`  
WHERE event_type =' . self::PRODUCT_SYNC . ' AND created > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY `interval`';

        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        while ($res = $result->current()) {
            $data['sync'][$res['interval']] = $res['qty'];
            $result->next();
        }
        $query = 'SELECT CONCAT(DATE_FORMAT(created, "%d-%H "), (date_format(created, "%i") DIV ' . $interval . ') + 1)  as `interval`, 
count(event_log_id) as qty FROM `event_log`  
WHERE event_type =' . self::PRODUCT_STOCK_UPDATE . ' AND created > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY `interval`';
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        while ($res = $result->current()) {
            $data['stock'][$res['interval']] = $res['qty'];
            $result->next();
        }
        $query = 'SELECT CONCAT(DATE_FORMAT(created, "%d-%H "), (date_format(created, "%i") DIV ' . $interval . ') + 1)  as `interval`, 
count(event_log_id) as qty FROM `event_log`  
WHERE event_type =' . self::PRODUCT_PRICE_UPDATE . ' AND created > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY `interval`';
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        while ($res = $result->current()) {
            $data['price'][$res['interval']] = $res['qty'];
            $result->next();
        }

        // todo Need to understand if the outofstock state is the last one for product, i.e. get the number of out of stocked products per a period
        $query = 'SELECT CONCAT(DATE_FORMAT(created, "%d-%H "), (date_format(created, "%i") DIV ' . $interval . ') + 1)  as `interval`, 
SUM(IF(`stock` > 0, 1, 0)) as qtyStock, SUM(IF(`stock` > 0, 0, 1)) as qtyOutStock FROM `product_stock`  
WHERE created > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY `interval`';
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        while ($res = $result->current()) {
            $data['inStock'][$res['interval']] = $res['qtyStock'];
            $data['outOfStock'][$res['interval']] = $res['qtyOutStock'];
            $result->next();
        }

        $fullData = [];
        for ($i = 0; $i < (1440 / $interval); $i++) {
            $timestamp = strtotime('-' . ($i * $interval) . ' minute');
            $key = date('d-H ', $timestamp) . (int)(floor(date('i', $timestamp) / $interval) + 1);
            $sync = $data['sync'][$key] ?? 0;
            $price = $data['price'][$key] ?? 0;
            $stock = $data['stock'][$key] ?? 0;
            $inStock = $data['inStock'][$key] ?? 0;
            $outStock = $data['outOfStock'][$key] ?? 0;
            $fullData[date('Y-m-d H:i', $timestamp)] = [
                'sync' => $sync,
                'price' => $price,
                'stock' => $stock,
                'inStock' => $inStock,
                'outOfStock' => $outStock,
                'timestamp' => $timestamp,
            ];
        }
        return array_reverse($fullData);
    }
}
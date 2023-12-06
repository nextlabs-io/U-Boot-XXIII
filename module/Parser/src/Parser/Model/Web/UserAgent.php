<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.09.2017
 * Time: 17:41
 */

namespace Parser\Model\Web;

use Parser\Model\SimpleObject;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Sql\Expression as Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

// todo change to TableGateway and it should be more abstract, the definition of success should be set on a higher level (overall on the level of the scraping algorythm)
class UserAgent extends SimpleObject
{
    PUBLIC CONST DISABLE_LIMIT = 3;
    private $db;

    public function __construct(AdapterInterface $db)
    {
        $this->db = $db;
    }

    /**
     *   Change usage statisticts according to the proxy_connection data
     * @param $db
     */
    public static function updateStatistics($db): void
    {
        $sql = new Sql($db);
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        // update user_agent usage stats
        //$update = $sql->update(['ua' => 'user_agent']);
        //$stmt = $sql->prepareStatementForSqlObject($update);
        $queryReset = 'UPDATE `user_agent` SET usage_count=0, success_rate=NULL, success_qty=0';
        $stmt->setSql($queryReset);
        $stmt->execute();


        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $queryUA1 = 'UPDATE user_agent ua LEFT JOIN (SELECT count(*) as qty, sum(IF(pc.curl_code = 200, 1, 0)) as success_qty, pc.user_agent_id 
FROM proxy_connection pc 
GROUP BY pc.user_agent_id) pc ON pc.user_agent_id = ua.user_agent_id
SET ua.usage_count=pc.qty, ua.success_qty=pc.success_qty WHERE pc.user_agent_id = ua.user_agent_id';

        $stmt->setSql($queryUA1);
        $stmt->execute();


        /*
                 $queryUA2 = "UPDATE user_agent ua SET ua.success_rate =
                    CASE
                WHEN `ua`.`usage_count` > 0 THEN
        (1000 * (
        SELECT count(*) as qty
        FROM proxy_connection pc WHERE pc.curl_code = 200 AND ua.user_agent_id=pc.user_agent_id GROUP BY pc.user_agent_id
        )) / ua.usage_count
                ELSE 1
            END
        ";
        */
        $queryUA2 = 'UPDATE user_agent ua SET ua.success_rate = 
            CASE 
        WHEN `ua`.`usage_count` > 0 THEN
            ROUND((1000 * ua.success_qty) / ua.usage_count)
        ELSE 100 
    END
';

        $db->query($queryUA2);
    }

    /**
     * If no id is specified, new user agent is delivered according to the usage count and success rate.
     *
     * @param int $id
     * @param array $exceptionValuesList
     * @param array $types
     * @return bool
     */
    public function getUserAgent($id = 0, $exceptionValuesList = [], $types = []): bool
    {
        $sql = new Sql($this->getDb());

        $select = $sql->select('user_agent');
        $select->columns([
            'qty' => new Expression('COUNT(*)'),
            'avgSuccessRate' => new Expression('AVG(success_rate)'),
            'maxSuccessRate' => new Expression('MAX(success_rate)')
        ]);
        $where = new Where();
        if ($exceptionValuesList) {
            $where->notIn('value', $exceptionValuesList);
        }
        if (is_array($types) && count($types)){
            $where->in('type', $types);
        }
        $select->where($where);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $data = $result->current();
//        pr($data);
        if (!$data) {
            throw new \RuntimeException('no user_agents found');
        }

        $minimumSuccessRate = (int)(($data['maxSuccessRate'] +  $data['avgSuccessRate']) / 2);
        $maxSuccessRate = $data['maxSuccessRate'];
        $avgSuccessRate = (int)$data['avgSuccessRate'];
        $totalQty = $data['qty'];
        if ($totalQty < 100) {
            $limit = 10;
        } else if ($totalQty > 1000) {
            $limit = 100;
        } else {
            $limit = $totalQty / 10;
        }

        $select = $this->getSelect($sql, $id,$exceptionValuesList, $minimumSuccessRate, $types);
        $stmt = $sql->prepareStatementForSqlObject($select);
//        $sqlQuery = $stmt->getSql();
        $result = $stmt->execute();
        if ($data = $result->current()) {
            $this->loadFromArray($data);
            return $data['value'];
        }

        // could not find user agent with requested options, get any success rate
        $select = $this->getSelect($sql,$id, $exceptionValuesList, $avgSuccessRate, $types);
        $stmt = $sql->prepareStatementForSqlObject($select);
//        $sqlQuery = $stmt->getSql();
        $result = $stmt->execute();
        if ($data = $result->current()) {
            $this->loadFromArray($data);
            return $data['value'];
        }

//        throw new \Exception('failed to get user agent with min successRate: '.$minimumSuccessRate. ', max rate: '.$maxSuccessRate.', avg rate:'.$avgSuccessRate.' and query: '. $sqlQuery);

        $this->addError('No user agent found!');
        return false;
    }

    public function getSelect($sql, $id,$exceptionValuesList, $minimumSuccessRate, $types) {

        $select = $sql->select('user_agent');
        $select->columns([
            'user_agent_id',
            'value',
            'enabled',
            'usage_count',
            'success_rate',
            'order_value' => new Expression('
            CASE 
        WHEN `usage_count` <= 10 THEN 1000
        ELSE ROUND(`success_rate`, -1) 
    END'),
        ]);
        if (!$id) {
            $where = new Where();
            $where->equalTo('enabled', true);
            $where->equalTo('active', true);


            if ($exceptionValuesList) {
                $where->notIn('value', $exceptionValuesList);
            }
            if (is_array($types) && count($types)){
                $where->in('type', $types);
            }
            $where->nest()
                ->greaterThan('success_rate', $minimumSuccessRate)
                ->or
                ->lessThan('usage_count', 11)
                ->unnest();

            $select->where($where);
            $select->limit(1);
            $select->order(new Expression('RAND()'));
        } else {
            $where = new Where();
            $where->equalTo('user_agent_id', $id);
            $select->where($where);
        }
        return $select;
    }
    public function getDb()
    {
        return $this->db;
    }

    public function setDb(AdapterInterface $db)
    {
        $this->db = $db;
        return $this;
    }

    public function insertAgents($agents)
    {
        // delete all current.
//        $sql = new Sql($this->db);
//        $remove = $sql->delete('user_agent');
//        $stmt = $sql->prepareStatementForSqlObject($remove);
//        $stmt->execute();
        // add new user agents.
//        $agents = [];
        if ($agents && count($agents)) {
            foreach ($agents as $agent) {
                $agent = trim($agent);
                if (strlen($agent) > 255) {
                    pr($agent . ' too long');
                    continue;
                }
                $agent = trim($agent);
                $sql = new Sql($this->db);
                $select = $sql->select('user_agent');
                $where = new Where();
                $where->equalTo('value', $agent);
                $select->where($where);
                $stmt = $sql->prepareStatementForSqlObject($select);
                $result = $stmt->execute();
                if ($result->current()) {
                    $data = $result->current();
                    pr('already there ' . $data['value']);
                    // do nothing, or enable it for example;
                } else {
                    try {
                        $insert = $sql->insert('user_agent')
                            ->values(['value' => $agent]);
                        $stmt = $sql->prepareStatementForSqlObject($insert);
                        $stmt->execute();
                        pr('inserting '. $agent);
                    } catch (\Exception $e) {
                        print_r($e->getMessage());
                        print_r($agent);
                        die();
                    }
                }
            }
        }
    }

    public function disable($reason, $value = "")
    {
        return $this->update(['enabled' => '0', 'usage_log' => $reason], $value);
    }

    public function update($data = [], $key = "")
    {
        $key = $key ?: $this->getProperty('user_agent_id');
        $sql = new Sql($this->db);
        $update = $sql->update('user_agent')->where(['user_agent_id' => $key])->set($data);
        $stmt = $sql->prepareStatementForSqlObject($update);
        return $stmt->execute();
    }

    public function enable($key = "")
    {
        return $this->update(['enabled' => '1', 'usage_log' => ''], $key);
    }

    /**
     * Update user_agent usage depending on the success condition (which is curlCode = 200)
     * @param null $code
     * @return \Laminas\Db\Adapter\Driver\ResultInterface
     */
    public function triggerUsage($code = null)
    {
        // success rate will rise a little if positive code received or will decrease a little if the code is not 200
        $exprAdd = $code == 200 ? ' + 10 ' : '';

        $exprString = 'IF(`usage_count` > 10, ROUND((1000 * `success_qty` / `usage_count`)), `success_rate`)';
        $data['success_rate'] = new Expression($exprString);
        $data['usage_count'] = new Expression('`usage_count` + 1');
        if ($code == 200) {
            $data['success_qty'] = new Expression('`success_qty` + 1');
        } else {
            $data['success_qty'] = new Expression('`success_qty`');
        }
        return $this->update($data);
    }

    public function logFailedParse($log = '')
    {
        return $this->update(['fail_count' => new Expression('`fail_count` + 1'), 'usage_log' => $log]);
    }

}
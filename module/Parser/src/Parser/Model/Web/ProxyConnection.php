<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 14.05.2018
 * Time: 17:20
 */

namespace Parser\Model\Web;


use Parser\Model\Helper\Config;
use Parser\Model\SimpleObject;
use Parser\Model\Web\ProxySource\ProxyManager;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class ProxyConnection extends SimpleObject
{
    /**
     * Checks for disabled proxies if they are to enable. And disables proxies if they give a lot of zero responses.
     * @param        $db
     * @param Config $config
     */
    public static function updateStatistics($db, Config $config)
    {
        $sql = new Sql($db);

        // check for proxy_connection which are not closed for more than 5 minutes.
        $globalConfig = $config->getConfig('settings');

        $created = date('Y-m-d H:i:s', strtotime('-300 seconds'));
        $where = new Where();
        $where->lessThan('created', $created)
            ->equalTo('closed', false);
        $update = $sql->update('proxy_connection')
            ->where($where)
            ->set(['closed' => true]);
        $stmt = $sql->prepareStatementForSqlObject($update);
        $stmt->execute();


        // delete proxy_connections data which are older than two days
        $created = date('Y-m-d H:i:s', strtotime('-48 hours'));
        $where = new Where();
        $where->lessThan('created', $created);
        $update = $sql->delete('proxy_connection')
            ->where($where);
        $stmt = $sql->prepareStatementForSqlObject($update);
        $stmt->execute();


    }

    /**
     * @param       $db
     * @param array $hours
     * @return array
     */
    public function getStats($db, $hours = [])
    {
        $hours = $hours ?: ['1', '3', '24'];
        $dataGrid = [];
        $curlCodes = [];

        $proxyList = $this->getProxyList($db);
        if (count($proxyList)) {
            foreach ($proxyList as $proxy) {
                $dataGrid[$proxy['proxy_id']]['data'] = $proxy;
                $dataGrid[$proxy['proxy_id']]['stats'] = [];
            }
        } else {
            return [];
        }
        // todo need to refactor and to run once
        foreach ($hours as $hour) {
            $where = new Where();
            $where->equalTo('pc.closed', true)
                ->greaterThan('pc.created', new Expression('DATE_SUB(NOW(), INTERVAL ' . $hour . ' HOUR)'));
            $where->isNotNull('pc.curl_code');
//            $where->equalTo('pc.group', 'amzn-offer');

            $line = $this->getGroupStat($db, $where);
            $totals = [];
            foreach ($line as $proxy) {
                if (isset($dataGrid[$proxy['proxy_id']])) {
                    $totals = self::alterTotals($totals, $proxy, $hour);
                    if ($proxy['curl_code'] !== '') {
                        $dataGrid[$proxy['proxy_id']]['stats'][$hour][$proxy['curl_code']] = $proxy['qty'];
                        $curlCodes[$proxy['curl_code']] = '0%';
                    }
                }
            }
            foreach ($dataGrid as $proxyId => $data) {

                if (isset($data['stats'][$hour]) && count($data['stats'][$hour])) {
                    foreach ($data['stats'][$hour] as $curlCode => $qty) {
                        if ($curlCode !== '' && $totals[$proxyId][$hour]) {
                            $dataGrid[$proxyId]['stats'][$hour][$curlCode] = (int)(100 * $qty / $totals[$proxyId][$hour]) . '%';
                        }
                    }
                    $dataGrid[$proxyId]['stats'][$hour]['total'] = $totals[$proxyId][$hour];
                }
            }
        }
        foreach ($dataGrid as $proxyId => $data) {
            foreach ($data['stats'] as $hour => $line) {
                $dataGrid[$proxyId]['stats'][$hour] += $curlCodes;
                ksort($dataGrid[$proxyId]['stats'][$hour], SORT_STRING);
            }
        }
        // sorting data by success value
        usort($dataGrid, function ($a, $b) {
            $aVal = $a['stats'][1][200] ?? $a['stats'][3][200] ?? $a['stats'][24][200] ?? 0;
            $bVal = $b['stats'][1][200] ?? $b['stats'][3][200] ?? $b['stats'][24][200] ?? 0;
            return (int)$aVal < (int)$bVal;
        });
        return $dataGrid;
    }

    public static function getProxyList($db)
    {
        $sql = new Sql($db);
        $select = new Select(['p' => 'proxy']);
        $select->where(['enabled' => true]);
        $select->order('max_usage_limit DESC');
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        if ($result->current()) {
            while ($result->current()) {
                $list[] = $result->current();
                $result->next();
            }
        }
        return $list;
    }

    private function getGroupStat($db, $where)
    {
        $sql = new Sql($db);
        $select = new Select(['p' => 'proxy']);
        $select->join(['pc' => 'proxy_connection'], 'p.proxy_id = pc.proxy_id',
            ['qty' => new Expression('COUNT(DISTINCT pc.proxy_connection_id)'), 'curl_code'], Join::JOIN_LEFT)
            ->where($where)
            ->group(['pc.proxy_id', 'pc.curl_code']);
        $stmt = $sql->prepareStatementForSqlObject($select);

        $result = $stmt->execute();
        $list = [];
        while ($result->current()) {
            $list[] = $result->current();
            $result->next();
        }
        return $list;
    }

    private static function alterTotals($totals, $proxy, $hour)
    {
        if (isset($totals[$proxy['proxy_id']][$hour])) {
            $totals[$proxy['proxy_id']][$hour] += $proxy['qty'];
        } else {
            $totals[$proxy['proxy_id']][$hour] = $proxy['qty'];
        }
        return $totals;
    }

    /**
     * get proxy connection statistics
     * @param $db
     * @return array
     */
    public static function getStatistics($db)
    {
        $stats = [];
        $sql = new Sql($db);
        $select = $sql->select('proxy_connection')->where(['closed' => false]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        $stats['active'] = 0;
        $stats['pending'] = 0;
        while ($data = $result->current()) {
            $list[] = $data;
            $result->next();
        }
        if (count($list)) {
            $stats['active'] = count($list);
            $oldConnections = 0;
            foreach ($list as $connection) {
                $age = date('U') - strtotime($connection['created']);
                if ($age > 120) {
                    $oldConnections++;
                }
            }
            $stats['pending'] = $oldConnections;
        }


        return $stats;
    }

    public static function getHourlyStats($db)
    {
        $query = 'SELECT `created_interval`, 
DATE_FORMAT(FROM_UNIXTIME(`created_interval`), "%Y-%m-%d %H") as `interval`, `proxy_id`, `curl_code`,
count(*) as qty
FROM proxy_connection  
WHERE `closed` = 1  AND `created_interval` > UNIX_TIMESTAMP(DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 24 HOUR), "%Y-%m-%d %H"))  GROUP BY `created_interval`, `proxy_id`, `curl_code`';


        $proxyList = self::getProxyList($db);
        if (count($proxyList)) {
            foreach ($proxyList as $proxy) {
                $dataGrid[$proxy['proxy_id']]['data'] = $proxy;
                $dataGrid[$proxy['proxy_id']]['grid'] = [];
            }
        } else {
            return [];
        }

        $sql = new Sql($db);
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        $data = [];
        while ($res = $result->current()) {
            $data[] = $res;
            $result->next();
        }
        print_r($data);
    }

    public function getTotals($data)
    {
        $totals = [];
        if (is_array($data) && count($data)) {
            foreach ($data as $proxy) {
                if (isset($proxy['stats'])) {
                    $stats = $proxy['stats'];
                    foreach ($stats as $hour => $line) {
                        foreach ($line as $code => $qty) {
                            if ($code === 'total') {
                                if (!isset($totals[$hour][$code])) {
                                    $totals[$hour][$code] = (int)$qty;
                                } else {
                                    $totals[$hour][$code] += (int)$qty;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $totals;
    }
}
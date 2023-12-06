<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 19.05.2020
 * Time: 23:01
 */

namespace Parser\Model\Web\ProxySource;


use Parser\Model\Web\Proxy;
use Laminas\Db\Sql\Where;

class ProxyManager extends ProxySource
{

    private $listToDisable = [];
    private $listToEnable = [];
    private $listToDeactivate = [];

    public function checkProxies($data)
    {
        $db = $this->globalConfig->getDb();
        $globalConfig = $this->globalConfig->getConfig('settings');

        // getting settings for default proxy management profile TODO move it to the proxy source section
        $percentToDeactivate = $globalConfig['proxyZeroResponsePercentBan'] ?: 50;
        $fail200ToDisable = $globalConfig['proxy200ResponsePercentBan'] ?: 10;
        $disableDeadProxies = $globalConfig['disableDeadProxies'] ?: 1;

        if (count($data)) {
            foreach ($data as $k => $proxy) {
                // depending on the proxy group we have different enable/activate strategies.
                $group = $proxy['data']['group'];

                $manageStrategy = $this->getStrategyByGroup($group);
                if ($manageStrategy === 'default' || !$group) {
                    // running a default profile

                    $failPercent = isset($proxy['stats'][1][0]) ? (int)$proxy['stats'][1][0] : 0;
                    $success200 = isset($proxy['stats'][1][200]) ? (int)$proxy['stats'][1][200] : 0;
                    $total = isset($proxy['stats'][1]['total']) ? (int)$proxy['stats'][1]['total'] : 0;

                    if ($total > 100 && !$success200 && $proxy['data']['active'] && $disableDeadProxies) {
                        // if active, and
                        $this->listToDisable[] = $proxy['data']['proxy_id'];
                    } elseif ($total && $failPercent > $percentToDeactivate) {
                        $this->listToDeactivate[] = $proxy['data']['proxy_id'];
                    } elseif ($total && $success200 < $fail200ToDisable) {
                        $this->listToDeactivate[] = $proxy['data']['proxy_id'];

                    } elseif ((int)$proxy['data']['active'] !== 1) {
                        $this->listToEnable[] = $proxy['data']['proxy_id'];
                    }
                } elseif ($manageStrategy === '200response') {
                    $disableLevel = $this->settings[$group]['disableLevel'] ?? 10;
                    $deactivateLevel = $this->settings[$group]['deactivateLevel'] ?? 15;
                    $success200 = isset($proxy['stats'][1][200]) ? (int)$proxy['stats'][1][200] : 0;
                    $total = isset($proxy['stats'][1]['total']) ? (int)$proxy['stats'][1]['total'] : 0;

                    if ($total > 20 && $success200 <= $disableLevel && $proxy['data']['active'] && $disableDeadProxies) {
                        // if active, and
                        $this->listToDisable[] = $proxy['data']['proxy_id'];

                    } elseif ($total && $success200 <= $deactivateLevel) {

                        $this->listToDeactivate[] = $proxy['data']['proxy_id'];
                    } elseif ((int)$proxy['data']['active'] !== 1) {

                        $this->listToEnable[] = $proxy['data']['proxy_id'];
                    }

                }
            }
        }
        if (count($this->listToDisable)) {
            $where = new Where();
            $where->in('proxy_id', $this->listToDisable);
            $data = ['enabled' => false];
            Proxy::staticUpdate($db, $data, $where);
        }
        if (count($this->listToDeactivate)) {
            $where = new Where();
            $where->in('proxy_id', $this->listToDeactivate);
            $data = ['active' => false];
            Proxy::staticUpdate($db, $data, $where);
        }
        if (count($this->listToEnable)) {
            $where = new Where();
            $where->in('proxy_id', $this->listToEnable);
            $data = ['active' => true];
            Proxy::staticUpdate($db, $data, $where);
        }

    }

    private function getStrategyByGroup($group): string
    {
        if (!$group || !isset($this->settings[$group])) {
            return 'default';
        }
        $profile = $this->settings[$group];
        return $profile['disableStrategy'] ?? 'default';

    }
}
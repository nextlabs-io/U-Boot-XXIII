<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 18.05.2020
 * Time: 18:56
 */

namespace Parser\Model\Web\ProxySource;


use Parser\Model\Helper\Config;
use Parser\Model\SimpleObject;
use Parser\Model\TablePage;
use Parser\Model\Web\Proxy;

// class to manage proxy items. to load them from sources like proxyscraper or file
class ProxySource extends SimpleObject
{
    /**
     * @var array|null
     */
    protected $settings;
    /**
     * @var array all groups from the config
     */
    protected $sources;
    /**
     * @var array all types from the config
     */
    protected $types;
    protected $type;

    protected $fieldsAssociation = [0 => 'ip', 1 => 'port', 2 => 'max_usage_limit', 3 => 'proxy_type', 4 => 'proxy_character'];
    protected $minimumFields = 2;

    protected $globalConfig;
    private $possibleTypes = ['default', 'proxyscraper', 'file'];

    public function __construct(Config $config)
    {
        $this->globalConfig = $config;
        $settings = $this->globalConfig->getConfig('proxySources');
        $this->settings = $settings;
        $this->getSources($settings);
        $this->type = 'default';
    }

    private function getSources()
    {
        $types = [];
        if (is_array($this->settings) && count($this->settings)) {
            foreach ($this->settings as $key => $setting) {
                if ($setting['type'] ?? null) {
                    $types[$setting['type']][$key] = $setting;
                }
            }
        }
        $this->types = array_intersect_key($types, array_flip($this->possibleTypes));
    }

    /**
     * @param bool $enableExisting
     * @return array
     */
    public function processProxyUpdate($enableExisting = false)
    {
        $proxyList = $this->getProxyListFromSource();
        if ($proxyList) {
            return $this->addProxies($proxyList, $enableExisting);
        }
        return [];
    }

    /**
     * @param string|null $type
     * @return array
     */
    public function getProxyListFromSource($type = null): array
    {
        $urls = $this->generateUrls();
        if (count($urls)) {
            $proxyList = [];
            foreach ($urls as $key => $url) {
                if (isset($this->settings[$key])) {
                    $maximumToProcess = $this->settings[$key]['pageSize'] ?? 20;
                    $maxAllowedActiveIps = $this->settings[$key]['maxAllowedActiveIps'] ?? 20;
                    $proxy = new Proxy($this->globalConfig->getDb(), $this->globalConfig);
                    $activeListWhere = ['group' => $key, 'active' => true, 'enabled' => true];
                    $currentlyActiveProxyList = $proxy->getProxyList($activeListWhere, ['proxy_id', 'ip', 'port']);
                    $resultData[$key]['currentlyActiveProxyListQty'] = count($currentlyActiveProxyList);

                    if (count($currentlyActiveProxyList) >= $maxAllowedActiveIps) {
                        $this->addMessage('group ' . $key . ' of proxies has a maximum allowed active qty = ' . $maxAllowedActiveIps . '; current qty = ' . count($currentlyActiveProxyList));
                    } else {
                        $proxyList[$key] = $this->getProxiesFromFile($url);
                    }

                }

            }
            return $proxyList;
        }
        return [];
    }

    /**
     * @return array
     */
    protected function generateUrls(): array
    {
        // an url is generated per type. default type gives empty urls
        return [];
    }

    private function getProxiesFromFile($url): array
    {
        if ($file = $this->getFile($url)) {
            $list = [];
            foreach ($file as $proxyItem) {
                $proxy = explode(':', $proxyItem);
                $proxy = array_map('trim', $proxy);
                $associatedData = $this->associateFieldsFromFile($proxy);
                if ($associatedData) {
                    $list[] = $associatedData;
                }
            }
            if (!$list) {
                $this->addError('got ' . count($file) . ' lines in a file, but failed to parse proxies');
            }

            return $list;
        }
        return [];
    }

    protected function getFile($url)
    {
        try {
            return @file($url);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return [];
        }
    }

    protected function associateFieldsFromFile($data)
    {
        $assocArray = $this->fieldsAssociation;
        if (count($data) < $this->minimumFields) {
            return false;
        }
        if (count($data) > count($assocArray)) {
            $data = array_slice($data, 0, count($assocArray));

        } else if (count($data) < count($assocArray)) {
            $assocArray = array_slice($assocArray, 0, count($data));
        }
        return array_combine($assocArray, $data);
    }

    /**
     * @param $data
     * @param bool $enableExisting
     * @return array
     */
    protected function addProxies($data, $enableExisting = false): array
    {
        /**
         *
         * [proxyscraperFree] => Array
         * (
         * [0] => Array  (
         * [ip] => 103.83.38.154
         * [port] => 5836
         * )
         */

        $proxy = new Proxy($this->globalConfig->getDb(), $this->globalConfig);
        $resultData = [];
        foreach ($data as $key => $proxyList) {
            $enableByCron = $this->settings[$key]['enableByCron'] ?? true;
            if(!$enableByCron) {
                pr('enableByCron set to false, existing proxies will not be reenabled for ' . $key);
            }
            $enableExistingByCron = $enableByCron ? $enableExisting : false;
            if (count($proxyList) && isset($this->settings[$key])) {
                $maximumToProcess = $this->settings[$key]['pageSize'] ?? 20;
                $maxAllowedActiveIps = $this->settings[$key]['maxAllowedActiveIps'] ?? 20;
                $resultData[$key] = ['existing' => 0, 'new' => 0, 'total' => count($proxyList), 'maximumToProcess' => $maximumToProcess, 'maxAllowedActiveIps' => $maxAllowedActiveIps];
                $addon = ['max_usage_limit' => $this->settings[$key]['maxUsageLimit'] ?? 3, 'group' => $key, 'enabled' => true, 'active' => true, 'proxy_type' => $this->settings[$key]['proxyType'] ?? 'http', 'proxy_character' => $this->settings[$key]['proxyCharacter'] ?? 'single'];
                $proxyList = $this->hydrateArray($proxyList, $addon);
                // check the number of active ips of this group
                $activeListWhere = ['group' => $key, 'active' => true, 'enabled' => true];
                $currentlyActiveProxyList = $proxy->getProxyList($activeListWhere, ['proxy_id', 'ip', 'port']);
                $resultData[$key]['currentlyActiveProxyListQty'] = count($currentlyActiveProxyList);

                if (count($currentlyActiveProxyList) >= $maxAllowedActiveIps) {
                    $this->addMessage('group ' . $key . ' of proxies has a maximum allowed active qty = ' . $maxAllowedActiveIps . '; current qty = ' . count($currentlyActiveProxyList));
                    return $resultData;

                }
                foreach ($proxyList as $proxyData) {
                    if ($loadedProxy = $proxy->loadProxyByIpPort($proxyData['ip'], $proxyData['port'])) {
                        // already exist, checking preferences on what to do with existing

                        if ($enableExistingByCron) {
                            $proxyId = $loadedProxy['proxy_id'];
                            $proxy->update(['enabled' => true, 'active' => true], $proxyId);
                        }
                        // ignoring the line
                        $resultData[$key]['existing']++;
                    } else {
                        // add new one
                        $resultData[$key]['new']++;
                        $proxy->add($proxyData);
                    }
                    if ($resultData[$key]['new'] >= $maximumToProcess) {
                        break;
                    }
                }
            }
        }
        return $resultData;

    }

    private function hydrateArray($list, $addon)
    {
        array_walk($list, static function (&$item) use ($addon) {
            $item = array_merge($addon, $item);
        });
        return $list;
    }
}
<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 18.05.2020
 * Time: 22:41
 */

namespace Parser\Model\Web\ProxySource;

/*
api.proxyscrape.com/?request=lastupdated&proxytype=http&serialkey=%Serialkey%
api.proxyscrape.com/?request=remaining&serialkey=%Serialkey%
AND to get the IP :
api.proxyscrape.com/?request=getproxies&proxytype=http&timeout=2000&country=US&status=alive&ssl=yes&serialkey=%Serialkey%
api.proxyscrape.com/?request=getproxies&proxytype=http&timeout=2000&country=CA&status=alive&ssl=yes&serialkey=%Serialkey%

// need to store some statistics - like last check, check results, etc. Need also to send an email in case if there are problems - like no proxies left to process.
*/

use Parser\Model\Helper\Config;

class ProxyScraper extends ProxySource
{

    private $remainingProxies;

    public function __construct(Config $config)
    {
        parent::__construct($config);
        $this->type = 'proxyscraper';
    }

    public function getExpirationData()
    {
        $data = [];
        if ($typeConfigList = $this->types[$this->type] ?? []) {
            foreach ($typeConfigList as $key => $item) {
                $serialKey = $this->getSerial($key);
                if (isset($item['baseUrl'], $item['remainingQuery']) && $item['enabled'] && $serialKey) {
                    $item['remainingQuery'] .= ',serialkey=' . $serialKey;

                    $params = str_replace(',', '&', $item['remainingQuery']);
                    $params = str_replace(';', ',', $params);

                    $url = $item['baseUrl'] . '?' . $params;
                    $info = $this->getFile($url);

                    if($info[0] ?? null) {
                        $data[$key] = str_replace('key will expire in', 'ttl', strtolower($info[0]));
                    } else {
                        $data[$key] = 'expired';
                    }
                }
            }
        }
        return $data;

    }

    /**
     * @return array
     */
    protected function generateUrls(): array
    {
        // an url is generated per type.
        $urls = [];
        if ($typeConfigList = $this->types[$this->type] ?? []) {
            foreach ($typeConfigList as $key => $item) {
                $serialKey = $this->getSerial($key);

                if (!isset($item['baseUrl'], $item['ipQuery'])) {
                    $this->addError('missing baseUrl or ipQuery for ' . $key);
                } elseif ($item['enabled']) {
                    if ($serialKey) {
                        $item['ipQuery'] .= ',serialKey=' . $serialKey;
                    }

                    $params = str_replace(',', '&', $item['ipQuery']);
                    $params = str_replace(';', ',', $params);

                    $url = $item['baseUrl'] . '?' . $params;
                    $urls[$key] = $url;
                }
            }
        }
        return $urls;
    }
    public function getConfigType(){
        return $this->types[$this->type] ?? [];
    }

    public function getSerial($key){
        $typeConfigList = $this->types[$this->type] ?? [];
        $item = $typeConfigList[$key] ?? null;
        if(!$item){
            return null;
        }
        $serialKey = $this->globalConfig->getProfileSetting('proxyscraper_'. $key);
        if($serialKey === null){
            $serialKey =  $item['serial'] ?? null;
        }
        return $serialKey;
    }

}
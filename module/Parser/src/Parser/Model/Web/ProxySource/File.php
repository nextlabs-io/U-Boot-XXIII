<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 18.05.2020
 * Time: 22:42
 */

namespace Parser\Model\Web\ProxySource;


use Parser\Model\Helper\Config;

class File extends ProxySource
{

    public function __construct(Config $config)
    {
        parent::__construct($config);
        $this->type = 'file';
        $this->fieldsAssociation = [0 => 'ip', 1 => 'port', 2 => 'max_usage_limit', 3=> 'user_name' , 4 => 'user_pass'];
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
                if (! ($item['path'] ?? null)) {
                    $this->addError('missing path for ' . $key);
                } elseif ($item['enabled']) {
                    $url = $item['path'];
                    $urls[$key] = $url;
                } else {
                    $this->addMessage('skipping disabled profile for type ' . $this->type. ' with key '. $key);
                }
            }
        } else {
            $this->addMessage('no config options available for selected type: ' . $this->type);
        }
        return $urls;
    }
}
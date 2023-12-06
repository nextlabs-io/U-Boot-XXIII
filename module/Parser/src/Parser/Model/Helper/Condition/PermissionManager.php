<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 06.01.2021
 * Time: 15:43
 */

namespace Parser\Model\Helper\Condition;


class PermissionManager
{
    /**
     * @var Config|\Parser\Model\Helper\Config
     */
    private $config;
    /**
     * @var array|null
     */
    private $hiddenSections;

    /**
     * PermissionManager constructor.
     * @param Config $config
     */
    public function __construct(\Parser\Model\Helper\Config $config)
    {
        $this->config = $config;
        $this->hiddenSections = array_filter($this->config->getConfig('hiddenSections') ?? []);
    }

    /**
     * @param array $items
     * @return array
     */
    public function check(array $items): array
    {
        if($this->hiddenSections && is_array($items) && count($items)){
            foreach ($items as $key => $item) {
                if(isset($this->hiddenSections[$key])){
                    unset($items[$key]);
                }
            }
        }
        return $items;
    }
}
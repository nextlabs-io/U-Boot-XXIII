<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 29.06.2020
 * Time: 12:57
 */

namespace Parser\Model\Helper;


abstract class Hook implements HookInterface
{
    /** @var Config $config */
    protected $config;

    /**
     * @param String $action
     * @param array $data
     */
    public function findHook(String $action, Array $data = [])
    {
        if ($class = $this->config->storeConfig['hookList'][$action] ?? []) {
            if (is_array($class)) {
                foreach ($class as $classItem) {
                    $this->evaluateHook($classItem, $data);
                }
            } else {
                $this->evaluateHook($class, $data);
            }
        }
    }


    /**
     * @param String $class
     */
    protected function evaluateHook(String $class, $data = []): void
    {
        if (class_exists($class)) {
            /** @var Hook $hookClass */
            $hookClass = new $class($this->config);
            $hookClass->processHook($data);
        }
    }

    /**
     * @param Config $config
     */
    public function setConfig(Config $config): void
    {
        $this->config = $config;
    }
}
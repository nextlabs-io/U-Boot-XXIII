<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 09.07.2020
 * Time: 21:13
 */

namespace Parser\Model\Helper\Condition;


use Parser\Model\Helper\Config;

class Live
{
    public function fire(Config $config)
    {
        if ($config->auth->hasIdentity() && ($identity = $config->auth->getIdentity())) {
            return $identity !== 'Store Owner';
        }
        return false;
    }
}
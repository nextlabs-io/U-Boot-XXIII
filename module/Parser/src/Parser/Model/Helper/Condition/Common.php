<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 09.07.2020
 * Time: 18:51
 */

namespace Parser\Model\Helper\Condition;


use Parser\Model\Helper\Config;

class Common
{
    public function fire(Config $config){
        return $config->auth->hasIdentity();
    }
}
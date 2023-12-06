<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 09.07.2020
 * Time: 21:13
 */

namespace Parser\Model\Helper\Condition;


use Parser\Model\Helper\Config;

class Admin
{
    public static function fire(Config $config)
    {
//        pr($config->storeConfig['currentRouterParams']);die();
        // first check if config forbid to get the controller/action

        if ($config->auth->hasIdentity() && ($identity = $config->auth->getIdentity())) {
            $role = $config->userProfile->data['role'];
            return $role === 'admin' || $role === 'superadmin';
        }
        return false;
    }
}
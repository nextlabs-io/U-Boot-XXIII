<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 09.07.2020
 * Time: 21:13
 */

namespace Parser\Model\Helper\Content;


use Parser\Model\Helper\Config;

class ConfigLocales
{
    public function fire(Config $config)
    {
        $locales = $config->getLocales();
        $list[] = ['order' => 0, 'type' => 'route', 'module' => 'manager', 'action' => 'config', 'class' => 'fa-cog', 'title' => 'General Settings',
        ];
        foreach ($locales as $key =>  $locale){
            $list['config-locale-'.$locale] = ['order' => ($key+1) * 10,
                'type' => 'route',
                'module' => 'manager-config',
                'options' => ['locale' => $locale],
                'class' => 'fa-list',
                'title' => 'Locale '. $locale];
        }
        return $list;
    }
}
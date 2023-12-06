<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 29.06.2020
 * Time: 14:20
 */

namespace Parser\Model\Helper;


class CommonHook extends Hook
{
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function processHook(Array $data = [])
    {
        // common hook does nothing for now.
    }
}
<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 29.06.2020
 * Time: 12:57
 */

namespace Parser\Model\Helper;

/**
 * a simple implementation of hooks,
 * usage:
 * put
 * Interface HookInterface
 * @package Parser\Model\Helper
 */
interface HookInterface
{
    public function findHook(String $string, Array $data);
    public function processHook(Array $data);
}
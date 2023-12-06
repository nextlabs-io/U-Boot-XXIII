<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 30.11.2020
 * Time: 18:24
 */

namespace Cdiscount\Model\Cdiscount\Product;



use Parser\Model\Helper\Config;
use Parser\Model\SimpleObject;

class ProductParser extends SimpleObject
{
    public $content;
    public function __construct($content)
    {
        $this->content = $content;

    }
}
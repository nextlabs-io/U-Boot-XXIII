<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 20.07.18
 * Time: 14:57
 */

namespace Parser\Factory;

use Interop\Container\ContainerInterface;
use Parser\Model\Amazon\Search\Product;
use Parser\Model\Helper\Config;

class SearchProductFactory
{
    public function __invoke(ContainerInterface $container, $name)
    {
        $config = $container->get(Config::class);
        return new Product($config);
    }

}
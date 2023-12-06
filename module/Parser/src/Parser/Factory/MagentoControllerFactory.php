<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 10.09.2018
 * Time: 21:08
 */

namespace Parser\Factory;

use Interop\Container\ContainerInterface;
use Parser\Controller\MagentoController;
use Parser\Model\Helper\Config;

class MagentoControllerFactory
{
    const CONTROLLER_CLASS = 'MagentoController';

    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $config = $container->get(Config::class);
        return new MagentoController($config);
    }
}
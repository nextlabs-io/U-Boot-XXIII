<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 07.03.2017
 * Time: 5:09
 */

namespace Parser\Factory;

use Interop\Container\ContainerInterface;
use Parser\Controller\ConfigurationController;
use Parser\Model\Helper\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConfigurationControllerFactory implements FactoryInterface
{
    const CONTROLLER_CLASS = 'ConfigurationController';


    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $config = $container->get(Config::class);
        return new ConfigurationController($config);
    }
}
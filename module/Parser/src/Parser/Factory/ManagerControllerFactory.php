<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 27.09.2017
 * Time: 15:23
 */

namespace Parser\Factory;

use Interop\Container\ContainerInterface;
use Parser\Controller\ManagerController;
use Parser\Model\Helper\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ManagerControllerFactory implements FactoryInterface
{
    const CONTROLLER_CLASS = 'ManagerController';

    /**
     * {@inheritDoc}
     * * Implementations should update to implement only Laminas\ServiceManager\Factory\FactoryInterface.
     *
     *
     * Once you have tested your code, you can then update your class to only implement
     * Laminas\ServiceManager\Factory\FactoryInterface, and remove the `createService()`
     * method.
     * @var Interop\Container\ContainerInterface $container
     * @return ManagerController
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $config = $container->get(Config::class);
        return new ManagerController($config, $container);
    }
}
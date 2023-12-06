<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 07.03.2017
 * Time: 5:09
 */

namespace Comparator\Factory;

use Comparator\Controller\ConsoleController;
use Interop\Container\ContainerInterface;
use Parser\Model\Helper\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ConsoleControllerFactory implements FactoryInterface
{
    const CONTROLLER_CLASS = 'ListController';

    /**
     * {@inheritDoc}
     * * Implementations should update to implement only Laminas\ServiceManager\Factory\FactoryInterface.
     *
     *
     * Once you have tested your code, you can then update your class to only implement
     * Laminas\ServiceManager\Factory\FactoryInterface, and remove the `createService()`
     * method.
     * @var Interop\Container\ContainerInterface $container
     * @return ConsoleController
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $config = $container->get(Config::class);
        return new ConsoleController($config);
    }
}
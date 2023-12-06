<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 21.07.18
 * Time: 13:54
 */

namespace Parser\Factory;

use Interop\Container\ContainerInterface;
use Parser\Model\Helper\Config;
use Laminas\Db\Adapter\Adapter;

class ConfigFactory
{
    /**
     * {@inheritDoc}
     * * Implementations should update to implement only Laminas\ServiceManager\Factory\FactoryInterface.
     *
     *
     * Once you have tested your code, you can then update your class to only implement
     * Laminas\ServiceManager\Factory\FactoryInterface, and remove the `createService()`
     * method.
     * @var Interop\Container\ContainerInterface $container
     * @return Config
     */
    public function __invoke(ContainerInterface $container, $name, $options = [])
    {
        $db = $container->get(Adapter::class);
        $storeConfig = $container->get('config');

        $router = $container->get('router');
        $request = $container->get('request');
        $routerMatch = $router->match($request);
        $routeParams = $routerMatch->getParams();
        $storeConfig['currentRouterParams'] = $routeParams;

        return new Config($db, $storeConfig);
    }

}
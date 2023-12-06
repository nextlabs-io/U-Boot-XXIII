<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 09.09.2017
 * Time: 17:58
 */

namespace Parser\Factory;

use Interop\Container\ContainerInterface;
use Parser\Model\Helper\Config;
use Parser\Model\Web\Proxy;
use Laminas\Db\Adapter\Adapter;

class ProxyFactory
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
     * @return Proxy
     */
    public function __invoke(ContainerInterface $container, $name, $options = [])
    {
        $db = $container->get(Adapter::class);
        $config = $container->get(Config::class);

        return new Proxy($db, $config);
    }
}
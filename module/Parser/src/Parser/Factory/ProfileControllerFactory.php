<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 10.08.18
 * Time: 21:34
 */

namespace Parser\Factory;

use Interop\Container\ContainerInterface;
use Parser\Controller\ProfileController;
use Parser\Model\Helper\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;


class ProfileControllerFactory implements FactoryInterface
{
    const CONTROLLER_CLASS = 'ProfileController';

    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $config = $container->get(Config::class);
        return new ProfileController($config);
    }
}


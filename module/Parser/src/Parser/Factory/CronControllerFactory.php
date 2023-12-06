<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 13.07.2019
 * Time: 19:31
 */

namespace Parser\Factory;


use Interop\Container\ContainerInterface;
use Parser\Controller\CronController;
use Parser\Model\Helper\Config;

class CronControllerFactory
{
    public const CONTROLLER_CLASS = 'CronController';

    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $config = $container->get(Config::class);
        return new CronController($config);
    }
}
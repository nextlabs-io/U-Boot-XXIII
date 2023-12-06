<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 10.09.2017
 * Time: 13:28
 */

namespace Parser\Factory;

use Interop\Container\ContainerInterface;
use Parser\Model\Web\UserAgent;

class UserAgentFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $db = $container->get('Laminas\Db\Adapter\Adapter');
        return new UserAgent($db);
    }
}
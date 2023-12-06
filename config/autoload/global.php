<?php

use Laminas\Db\Adapter\AdapterServiceFactory;
use Laminas\Db\Adapter\Adapter;

/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */
/**
 * @param $val
 */

return [
    // ...
    'controllerMap' => [
    ],
    'db' => [
        'driver' => 'pdo',
        'dsn' => 'mysql:dbname=parser_table;host=localhost;charset=utf8mb4',
        'driver_options' => [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'utf8mb4\'',
        ],
        'charset'  => 'utf8mb4',
        'username' => '',
        'password' => '',
    ],
    'service_manager' => [
        'factories' => [
            Adapter::class => AdapterServiceFactory::class,
        ],
    ],
    'view_helper_config' => [
        'asset' => [
            'resource_map' => [
                'website-title' => 'Web Data Extraction Engine',
                'demo' => 0,
            ],
        ],
    ],

];

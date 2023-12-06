<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 22:53
 */

namespace eBay;

use eBay\Factory\ListControllerFactory;
use Laminas\Router\Http\Segment;


return [
    'controllerMap' => [
        'migrate-ebay' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationTable' => 'migration_ebay',
            'migrationPath' => 'module/eBay/migrations',
        ],
    ],

    'service_manager' => [
        'invokables' => [],
        'factories' => [],
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'doctype' => 'HTML5',
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
        'template_map' => [
            'ebay_sidebar' => __DIR__ . '/../view/layout/sidebar.phtml',
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\ListController::class => ListControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'ebay' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/ebay[/:action]',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'index',
                    ],

                ],
            ],
        ],
    ],
];
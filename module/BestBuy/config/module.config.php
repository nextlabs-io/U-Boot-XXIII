<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 22:53
 */

namespace BestBuy;

use BestBuy\Factory\ListControllerFactory;
use BestBuy\Factory\KeepaControllerFactory;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use yii\console\controllers\MigrateController;


return [
    'controllerMap' => [
        'migrate-bestbuy' => [
            'class' => MigrateController::class,
            'migrationTable' => 'migration_bestbuy',
            'migrationPath' => 'module/BestBuy/migrations',
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
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\ListController::class => ListControllerFactory::class,
            Controller\KeepaController::class => KeepaControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'bestbuy' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/bestbuy[/:action]',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'category',
                    ],

                ],
            ],
            'bestbuy-index' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/bestbuy/',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'category',
                    ],
                ],
            ],
            'keepa' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/keepa[/:action]',
                    'defaults' => [
                        'controller' => Controller\KeepaController::class,
                        'action' => 'index',
                    ],

                ],
            ],

        ],
    ],
    'sidebar' => [
        'bestbuy' => [
            'condition' => \Parser\Model\Helper\Condition\Live::class,
            'items' => [

                'bestbuy' => [
                    'order' => 120,
                    'type' => 'route',
                    'module' => 'bestbuy',
                    'action' => 'category',
                    'class' => 'fa-bold',
                    'title' => 'BestBuy Categories',
                    'children' => [
                        'bestbuy-products-list' => ['order' => 20, 'type' => 'route', 'module' => 'bestbuy', 'action' => 'category', 'class' => 'fa-list', 'title' => 'Categories'],
                        'bestbuy-products-form' => ['order' => 10, 'type' => 'route', 'module' => 'bestbuy', 'action' => 'upload', 'class' => 'fa-search', 'title' => 'Upload Form'],
                    ]
                ],
                'keepa' => [
                    'order' => 200,
                    'type' => 'route',
                    'module' => 'keepa',
                    'action' => 'index',
                    'class' => 'fa-signal',
                    'title' => 'Keepa Products',
                ],
            ],
        ],
    ],
    'hookList' => [
        'product-sync' => [
        ],
        'product-delete' => [
        ],
    ],

    'bestBuyConfig' => [
        'host' => 'https://www.bestbuy.ca',
        'baseUrl' => 'https://www.bestbuy.ca/en-ca/',
        'categoryTag' => 'category/',
        'pagingTag' => 'page={page}',
        'pagesQtyPerRun' => 10,
        'productsQtyPerRun' => 100,
    ]
];
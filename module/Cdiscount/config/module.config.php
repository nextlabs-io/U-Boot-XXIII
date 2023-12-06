<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 22:53
 */

namespace Cdiscount;

use Cdiscount\Factory\ConsoleControllerFactory;
use Cdiscount\Factory\ListControllerFactory;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use yii\console\controllers\MigrateController;


return [
    'controllerMap' => [
        'migrate-cdiscount' => [
            'class' => MigrateController::class,
            'migrationTable' => 'migration_cdiscount',
            'migrationPath' => 'module/Cdiscount/migrations',
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
            Controller\ConsoleController::class => ConsoleControllerFactory::class,
        ],
    ],
    'console' => [
        'router' => [
            'routes' => [
                'cdiscount-scrape-controller' => [
                    'options' => [
                        'route' => 'scrape cdiscount [--verbose|-v] [--delay=delay] [--category=category] ',
                        'defaults' => [
                            'controller' => Controller\ConsoleController::class,
                            'action' => 'scrape'
                        ]
                    ]
                ],
                'cdiscount-scrape-amazon-controller' => [
                    'options' => [
                        'route' => 'scrape cdiscountAmazon [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => Controller\ConsoleController::class,
                            'action' => 'scrapeAmazon'
                        ]
                    ]
                ],
                'cdiscount-scrape-product-controller' => [
                    'options' => [
                        'route' => 'scrape cdiscountProduct [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => Controller\ConsoleController::class,
                            'action' => 'scrapeProduct'
                        ]
                    ]
                ],
            ]
        ]
    ],
    'router' => [
        'routes' => [
            'cdiscount' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/cdiscount[/:action]',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'list',
                    ],

                ],
            ],
            'cdiscount-console' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/cdiscount-console[/:action]',
                    'defaults' => [
                        'controller' => Controller\ConsoleController::class,
                        'action' => 'scrape',
                    ],

                ],
            ],
            'cdiscount-index' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/cdiscount/',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'list',
                    ],
                ],
            ],

        ],
    ],
    'sidebar' => [
        'cdiscount' => [
            'condition' => \Parser\Model\Helper\Condition\Live::class,
            'items' => [
                'cdiscount' => ['order' => 20, 'type' => 'route', 'module' => 'cdiscount', 'action' => 'list', 'class' => 'fa-search', 'title' => 'CDiscount Products', 'children' => [
                    'find-cdiscount-category-list' => ['order' => 30, 'type' => 'route', 'module' => 'cdiscount', 'action' => 'list', 'class' => 'fa-list', 'title' => 'Categories'],
                    'find-cdiscount-products-list' => ['order' => 20, 'type' => 'route', 'module' => 'cdiscount', 'action' => 'products', 'class' => 'fa-list', 'title' => 'Products'],
                    'find-cdiscount-products-form' => ['order' => 10, 'type' => 'route', 'module' => 'cdiscount', 'action' => 'search', 'class' => 'fa-search', 'title' => 'Add categories'],
                ]
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

    'cdiscountConfig' => [
        'host' => 'https://cdiscount.com',
        'baseUrl' => 'https://cdiscount.com/',
        'categoryTag' => 'category/',
        'pagingTag' => 'page={page}',
        'pagesQtyPerRun' => 10,
        'productsQtyPerRun' => 100,
    ]
];
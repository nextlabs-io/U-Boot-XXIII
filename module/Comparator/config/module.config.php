<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 22:53
 */

namespace Comparator;

use Comparator\Controller\ConsoleController;
use Comparator\Controller\ListController;
use Comparator\Factory\ConsoleControllerFactory;
use Comparator\Factory\ListControllerFactory;
use Parser\Model\Helper\Condition\Live;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use yii\console\controllers\MigrateController;


return [
    'controllerMap' => [
        'migrate-comparator' => [
            'class' => MigrateController::class,
            'migrationTable' => 'migration_comparator',
            'migrationPath' => 'module/Comparator/migrations',
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
            ListController::class => ListControllerFactory::class,
            ConsoleController::class => ConsoleControllerFactory::class,
        ],
    ],
    'console' => [
        'router' => [
            'routes' => [
                'comparator-scrape-controller' => [
                    'options' => [
                        'route' => 'scrape comparator [--verbose|-v] [--delay=delay] [--category=category] ',
                        'defaults' => [
                            'controller' => ConsoleController::class,
                            'action' => 'scrape'
                        ]
                    ]
                ],
                'comparator-scrape-amazon-controller' => [
                    'options' => [
                        'route' => 'scrape comparatorAmazon [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => ConsoleController::class,
                            'action' => 'scrapeAmazon'
                        ]
                    ]
                ],
                'comparator-scrape-product-controller' => [
                    'options' => [
                        'route' => 'scrape comparatorProduct [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => ConsoleController::class,
                            'action' => 'scrapeProduct'
                        ]
                    ]
                ],
                'comparator-scrape-keepa-controller' => [
                    'options' => [
                        'route' => 'scrape comparatorKeepa [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => ConsoleController::class,
                            'action' => 'scrapeKeepa'
                        ]
                    ]
                ],
            ]
        ]
    ],
    'router' => [
        'routes' => [
            'comparator' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/comparator[/:action]',
                    'defaults' => [
                        'controller' => ListController::class,
                        'action' => 'list',
                    ],

                ],
            ],
            'comparator-console' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/comparator-console[/:action]',
                    'defaults' => [
                        'controller' => ConsoleController::class,
                        'action' => 'scrape',
                    ],

                ],
            ],
            'comparator-index' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/comparator/',
                    'defaults' => [
                        'controller' => ListController::class,
                        'action' => 'list',
                    ],
                ],
            ],

        ],
    ],
    'sidebar' => [
        'comparator' => [
            'condition' => Live::class,
            'items' => [
                'comparator' => ['order' => 20, 'type' => 'route', 'module' => 'comparator', 'action' => 'category', 'class' => 'fa-search', 'title' => 'Comparator', 'children' => [
                    'find-comparator-products-list' => ['order' => 20, 'type' => 'route', 'module' => 'comparator', 'action' => 'list', 'class' => 'fa-list', 'title' => 'Products'],
                    'find-comparator-products-form' => ['order' => 10, 'type' => 'route', 'module' => 'comparator', 'action' => 'search', 'class' => 'fa-search', 'title' => 'Add items'],

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
];
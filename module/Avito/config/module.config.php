<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 22:53
 */

namespace Avito;

use Avito\Factory\ListControllerFactory;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;


return [
    'controllerMap' => [
        'migrate-avito' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationTable' => 'migration_avito',
            'migrationPath' => 'module/Avito/migrations',
        ],
    ],
    'service_manager' => [
        'invokables' => [
        ],
        'factories' => [
        ],
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'doctype' => 'HTML5',
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\ListController::class => ListControllerFactory::class,
        ],
    ],

    'console' => [
        'router' => [
            'routes' => [
                'avito-scrape-controller' => [
                    'options' => [
                        'route' => 'scrape avito [--verbose|-v] [--delay=delay] [--category=category] ',
                        'defaults' => [
                            'controller' => Controller\ListController::class,
                            'action' => 'index'
                        ]
                    ]
                ],
            ]
        ]
    ],

    'router' => [
        'routes' => [
            /** uncomment to make this new home page, make sure the module is loaded last */
            /*
            'home' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action'     => 'list',
                    ],
                ],
            ],*/
            'Avito' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/avito[/:action]',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'index',
                    ],

                ],
            ],
            'Avito-index' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/avito/',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'index',
                    ],
                ],
            ],
        ],
    ],
];
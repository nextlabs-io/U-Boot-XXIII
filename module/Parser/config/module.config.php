<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 22:53
 */

namespace Parser;

use Parser\Factory\ConfigFactory;
use Parser\Factory\ConfigurationControllerFactory;
use Parser\Factory\CrawlerControllerFactory;
use Parser\Factory\ListControllerFactory;
use Parser\Factory\TestControllerFactory;
use Parser\Factory\MagentoControllerFactory;
use Parser\Factory\ManagerControllerFactory;
use Parser\Factory\ProfileControllerFactory;
use Parser\Factory\CronControllerFactory;
use Parser\Factory\ProxyFactory;
use Parser\Factory\SearchProductFactory;
use Parser\Factory\StatusControllerFactory;
use Parser\Factory\UserAgentFactory;
use Parser\Model\Helper\Condition\Common;
use Parser\Model\Helper\Condition\Demo;
use Parser\Model\Helper\Condition\Live;
use Parser\Model\Helper\Condition\Admin;
use Parser\Model\Helper\Content\ConfigLocales;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\Session\Service\SessionManagerFactory;
use Laminas\Session\SessionManager;
use Laminas\Session\Validator\RemoteAddr;
use Laminas\Session\Validator\HttpUserAgent;
use Laminas\Session\Storage\SessionArrayStorage;


return [
    'config_file' => 'data/parser/config/config.xml',
    'controllerMap' => [
        'migrate-parser' => [
            'class' => 'yii\console\controllers\MigrateController',
            'migrationTable' => 'migration_parser',
            'migrationPath' => 'module/Parser/migrations',
        ],
    ],
    'service_manager' => [
        'invokables' => [
        ],
        'factories' => [
            Model\Web\Proxy::class => ProxyFactory::class,
            Model\Web\UserAgent::class => UserAgentFactory::class,
            Model\Helper\Config::class => ConfigFactory::class,
            Model\Amazon\Search\Product::class => SearchProductFactory::class,
            SessionManager::class => SessionManagerFactory::class,
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
        'template_map' => [
            'layout/layout' => __DIR__ . '/../view/layout/layout.phtml',
            'layout/sidebar' => __DIR__ . '/../view/layout/sidebar.phtml',
            'layout/topnav' => __DIR__ . '/../view/layout/topnav.phtml',
            'error/404' => __DIR__ . '/../view/error/404.phtml',
            'error/index' => __DIR__ . '/../view/error/index.phtml',
            'zero' => __DIR__ . '/../view/layout/zero.phtml',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\CrawlerController::class => CrawlerControllerFactory::class,
            Controller\ConfigurationController::class => ConfigurationControllerFactory::class,
            Controller\ListController::class => ListControllerFactory::class,
            Controller\TestController::class => TestControllerFactory::class,
            Controller\StatusController::class => StatusControllerFactory::class,
            Controller\ManagerController::class => ManagerControllerFactory::class,
            Controller\MagentoController::class => MagentoControllerFactory::class,
            Controller\ProfileController::class => ProfileControllerFactory::class,
            Controller\CronController::class => CronControllerFactory::class,
        ],
    ],
    'console' => [
        'router' => [
            'routes' => [
                'general-test-controller' => [
                    'options' => [
                        'route' => 'test scrape catalog',
                        'defaults' => [
                            'controller' => Controller\TestController::class,
                            'action' => 'consoleScrape'
                        ]
                    ]
                ],
                'general-test-controller-browsers' => [
                    'options' => [
                        'route' => 'test browsers [--proxy=proxy]',
                        'defaults' => [
                            'controller' => Controller\TestController::class,
                            'action' => 'testBrowsers'
                        ]
                    ]
                ],
                'cron-scrape-catalog-action' => [
                    'options' => [
                        'route' => 'scrape catalog [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => Controller\CrawlerController::class,
                            'action' => 'scrape'
                        ]
                    ]
                ],
                'cron-sync-amazon-product-action' => [
                    // &locale=de&asin=B003UT1YW6&debug=2
                    'options' => [
                        'route' => 'scrape amazonProduct [--locale=locale] [--asin=asin] [--debug=debug]',
                        'defaults' => [
                            'controller' => Controller\ListController::class,
                            'action' => 'parse'
                        ]
                    ]
                ],
                'cron-sync-amazon-product-sync-action' => [
                    // &locale=de&asin=B003UT1YW6&debug=2
                    'options' => [
                        'route' => 'scrape amazon [--debug=debug] [--delay=delay] [--key=key]',
                        'defaults' => [
                            'controller' => Controller\ListController::class,
                            'action' => 'consolesync'
                        ]
                    ]
                ]


            ]
        ]
    ],
    'router' => [
        'routes' => [
            'home' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/',
                    'defaults' => [
                        'controller' => Controller\ManagerController::class,
                        'action' => 'getstat',
                    ],
                ],
            ],
            'profile' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/profile[/:action]',
                    'defaults' => [
                        'controller' => Controller\ProfileController::class,
                        'action' => 'index',

                    ],

                ],
            ],
            'parser' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/parser[/:action]',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'parser-index' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/parser/',
                    'defaults' => [
                        'controller' => Controller\ListController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'status' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/status[/:action]',
                    'defaults' => [
                        'controller' => Controller\StatusController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'manager' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/manager[/:action]',
                    'defaults' => [
                        'controller' => Controller\ManagerController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'manager-config' =>[
                'type' => Segment::class,
                'options' => [
                    'route'    => '/configLocale[/:locale]',
                    'defaults' => [
                        'controller' => Controller\ManagerController::class,
                        'action'     => 'configLocale',
                        'locale'       => 'ca',
                    ],
                    'constraints' => [
                        'locale' => '[a-z]*',
                    ],
                ],
            ],
            'magento' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/magento[/:action]',
                    'defaults' => [
                        'controller' => Controller\MagentoController::class,
                        'action' => 'list',
                    ],
                ],
            ],
            'crawler' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/crawler[/:action]',
                    'defaults' => [
                        'controller' => Controller\CrawlerController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'config' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/config[/:action]',
                    'defaults' => [
                        'controller' => Controller\ConfigurationController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'cron' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/cron[/:action]',
                    'defaults' => [
                        'controller' => Controller\CronController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'technik' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/tech/stockpattern',
                    'defaults' => [
                        'controller' => Controller\ManagerController::class,
                        'action' => 'stockPattern',
                    ],
                ],

            ],
            // test controller

            'aska' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/aska[/:action]',
                    'defaults' => [
                        'controller' => Controller\TestController::class,
                        'action' => 'index',
                    ],
                ],
            ],
        ],
    ],
    'sidebar' => [
        'parser-sidebar' => [
            'condition' => Common::class,
            'items' => [
                'home' => ['order' => 0, 'type' => 'route', 'module' => 'home', 'action' => '', 'class' => 'fa-dashboard', 'title' => 'Dashboard'],
                'Products' => ['order' => 10, 'type' => 'route', 'module' => 'manager', 'action' => 'list', 'class' => 'fa-th-list', 'title' => 'Products'],
                'FindProducts' => ['order' => 20, 'type' => 'route', 'module' => 'crawler', 'action' => 'search', 'class' => 'fa-search', 'title' => 'Find Products', 'children' => [
                    'find-products-list' => ['order' => 20, 'type' => 'route', 'module' => 'crawler', 'action' => 'list', 'class' => 'fa-list', 'title' => 'Categories'],
                    'find-products-form' => ['order' => 10, 'type' => 'route', 'module' => 'crawler', 'action' => 'search', 'class' => 'fa-search', 'title' => 'Upload Form'],
                ]
                ],
                'UploadASINs' => ['order' => 30, 'type' => 'route', 'module' => 'manager', 'action' => 'import', 'class' => 'fa-upload', 'title' => 'Upload ASINs'],
                'Configuration' => ['order' => 40, 'type' => 'route', 'module' => 'config', 'action' => 'list', 'class' => 'fa-sliders', 'title' => 'Configuration'],

            ]
        ],
        'parser-live' => [
            'condition' => Live::class,
            'items' => [
                'MagentoList' => ['order' => 50, 'type' => 'route', 'module' => 'magento', 'action' => 'list', 'class' => 'fa-shopping-cart', 'title' => 'Magento List'],
            ],
        ],
        'config-admin' => [
            'condition' => Admin::class,
            'items' => [
                'GeneralConfig' => ['order' => 1150, 'type' => 'route', 'module' => 'manager', 'action' => 'config', 'class' => 'fa-cog', 'title' => 'Settings',
                    'children' => ConfigLocales::class
                ],
            ],
        ],
        'parser-demo' => [
            'condition' => Demo::class,
            'items' => ['QuickTour' => ['order' => 60, 'type' => 'route', 'module' => 'profile', 'action' => 'quicktour', 'class' => 'fa-book', 'title' => 'Quick Tour'],
            ],
        ]
    ],
    'session_storage' => [
        'name' => 'parser',
        'type' => SessionArrayStorage::class,
        'options' => [
        ],
    ],
    'session_container' => [
        'name' => 'parser',
    ],
    'session_config' => [
        'name' => 'parser',
        'remember_me_seconds' => 86400,
        'cache_expire' => 86400,
        'cookie_lifetime' => 86400,
        'gc_maxlifetime' => 86400,
    ],
    'session_manager' => [
        'name' => 'parser',
        'validators' => [
            RemoteAddr::class,
            HttpUserAgent::class,
        ],
    ],
];
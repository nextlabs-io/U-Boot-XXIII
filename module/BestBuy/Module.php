<?php

namespace BestBuy;

use Laminas\ModuleManager\Feature\AutoloaderProviderInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;

class Module implements AutoloaderProviderInterface, ConfigProviderInterface
{
    /**
     * Return an array for passing to Laminas\Loader\AutoloaderFactory.
     *
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return [
            'Laminas\Loader\StandardAutoloader' => [
                'namespaces' => [
                    // Autoload all classes from namespace 'Parser' from '/module/Parser/src/Parser'
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ],
            ],
        ];
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap($e)
    {
        $app = $e->getParam('application');
        $app->getEventManager()->attach('render', [$this, 'setLayoutTitle']);

        /* enable session with custom confug */
        //$manager = $e->getApplication()->getServiceManager()->get(SessionManager::class)->start();

    }


    /**
     * @param  \Laminas\Mvc\MvcEvent $e The MvcEvent instance
     * @return void
     */
    public function setLayoutTitle($e)
    {
        $matches = $e->getRouteMatch();
        if ($matches) {
            //$action = $matches->getParam('action');
            $controller = $matches->getParam('controller');
            $module = __NAMESPACE__;
            $siteName = 'Web Data Extraction Engine';

            // Getting the view helper manager from the application service manager
            $viewHelperManager = $e->getApplication()->getServiceManager()->get('ViewHelperManager');

            // Getting the headTitle helper from the view helper manager
            $headTitleHelper = $viewHelperManager->get('headTitle');
            /**
             * @var \Laminas\View\Helper\HeadTitle $headTitleHelper
             */

            // Setting a separator string for segments
            $headTitleHelper->setSeparator(' - ');
            //$headTitleHelper->append($action);
            // Setting the action, controller, module and site name as title segments
            //$headTitleHelper->append($controller);
            //$headTitleHelper->append($module);
            $headTitleHelper->append($siteName);
        }
    }
}
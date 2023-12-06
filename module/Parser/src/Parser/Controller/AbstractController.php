<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 10.08.18
 * Time: 15:11
 */

namespace Parser\Controller;

use Parser\Model\Helper\Condition\PermissionManager;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\LocalScripts;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Session as SessionStorage;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Exception;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;


abstract class AbstractController extends AbstractActionController
{
    public $session;
    protected $authActions = [];

    /** @var Config $config */
    protected $config;

    protected $identity = false;
    protected $menu = [];

    /**
     * @param MvcEvent $e
     * @param array $data
     * @return mixed
     */

    public function onDispatch(MvcEvent $e, $data = [])
    {
        $this->session = new Container();
        $routeMatch = $e->getRouteMatch();
        if (!$routeMatch) {
            /**
             *       Potentially allow pulling directly from request metadata?
             */
            throw new Exception\DomainException('Missing route matches; unsure how to retrieve action');
        }
        $action = $routeMatch->getParam('action', 'not-found');
        $controller = $routeMatch->getParam('controller');
        // getting module name from the controller class
        $module = strtolower(explode('\\', $controller)[0]);

        if (!$this->checkActionForAuth($action)) {
            return $this->redirect()->toUrl('/profile/login?redirect=' . $this->getRequest()->getRequestUri());
        }


        $layout = $this->layout();

        $layout->setTemplate('layout/layout');
        $menuItems = $this->config->storeConfig['sidebar'] ?? [];
        $menuItemsToRender = [];
        $menuItemsToRenderList = [];
        $permissionManager = new PermissionManager($this->config);
        foreach ($menuItems as $key => $menuItem) {
            if (isset($menuItem['condition']) && class_exists($menuItem['condition'])) {
                $className = $menuItem['condition'];
                $condObject = new $className();
                if (isset($menuItem['items']) && $condObject->fire($this->config)) {
                    $items = $this->processItems($menuItem['items']);
                    $menuItemsToRenderList[] = $items;
                }
            }
        }

        if (count($menuItemsToRenderList)) {
            $menuItemsToRender = array_merge(... $menuItemsToRenderList);
            $menuItemsToRender = $permissionManager->check($menuItemsToRender);
            $menuItemsToRender = $this->orderSidebarItems($menuItemsToRender);
        }

        $sidebarView = new ViewModel([
            'action' => $action,
            'controller' => $controller,
            'menuItems' => $menuItemsToRender,
        ]);
        $templateMap = $this->config->storeConfig['view_manager']['template_map'];
        // a little trick to get a module specific sidebar/topnav
        $sidebarTemplate = $module . '_sidebar';
        $sidebarTemplate = isset($templateMap[$sidebarTemplate]) ? $sidebarTemplate : 'layout/sidebar';
        $sidebarView->setTemplate($sidebarTemplate);

        $layout->addChild($sidebarView, 'sidebar');

        $auth = $this->getAuth();
        $identity = null;
        if ($auth->hasIdentity()) {
            $identity = $auth->getIdentity();
        }

        $topNav = new ViewModel([
            'action' => $action,
            'controller' => $controller,
            'identity' => $identity,

        ]);
        $topNavTemplate = $module . '_topnav';
        $topNavTemplate = isset($templateMap[$topNavTemplate]) ? $topNavTemplate : 'layout/topnav';
        $topNav->setTemplate($topNavTemplate);
        $layout->addChild($topNav, 'topnav');
        /** @var  $localScripts  scripts which is placed before </head> ends, files are taken from /data/LocalScripts/*.html */
        // TODO move LocalScripts content to a config, in order to apply caching
        $layout->setVariables(LocalScripts::get());
        return parent::onDispatch($e);
    }

    /**
     * @param $action
     * @return false if action require authentication and user is not authenticated, otherwise
     * @return true
     */
    public function checkActionForAuth($action): bool
    {
        if (is_array($this->authActions) && count($this->authActions) && in_array($action, $this->authActions)) {
            return $this->auth();
        }
        return true;
    }

    /**
     * @return bool - if the user is authenticated.
     */

    public function auth()
    {
        $auth = $this->getAuth();
        if ($auth->hasIdentity()) {
            $this->identity = $auth->getIdentity();
            return true;
        }
        return false;
    }

    /**
     * @return AuthenticationService
     */
    public function getAuth(): AuthenticationService
    {
        $auth = $this->config->auth;
        if (!$this->session) {
            $this->session = $this->config->session;
        }
        return $auth;
    }

    private function processItems($items)
    {
        if (count($items)) {
            foreach ($items as $key => $item) {

                $children = $item['children'] ?? null;
                if ($children && !is_array($children) && class_exists($children)) {
                    $obj = new $children();
                    $items[$key]['children'] = $obj->fire($this->config);
                }
            }
        }
        return $items;
    }

    private function orderSidebarItems(array $menuItemsToRender)
    {
        if ($menuItemsToRender) {

            $orderArray = array_map(static function ($elem) {
                return $elem['order'] ?? null;
            }, $menuItemsToRender);
            asort($orderArray);
            $menuItemsToRender = array_merge($orderArray, $menuItemsToRender);
        }
        return $menuItemsToRender;

    }

    /**
     * @Deprecated
     * @param array $data
     * @return ViewModel
     */
    public function returnZeroTemplate($data = []): ViewModel
    {
        return $this->zeroTemplate($data);
    }

    /**
     * @Deprecated
     * @param array $data
     * @return ViewModel
     */
    public function returnMessageTemplate($data = []): ViewModel
    {
        $result = new ViewModel($data);
        $result->setTemplate('layout/message');
        return $result;
    }

    /**
     * @param array $data
     * @return ViewModel
     */
    public function zeroTemplate($data = []): ViewModel
    {
        $result = new ViewModel($data);
        $result->setTemplate('zero');
        $result->setTerminal(true);
        return $result;
    }

    /**
     * @param array $data
     * @return ViewModel
     */
    public function scrapeTemplate($data = []): ViewModel
    {
        $result = new ViewModel($data);
        $result->setTemplate('helper/scrape');
        $result->setTerminal(true);
        return $result;
    }

    public function generateRouteUrl($route, $params = [], $options = [])
    {
        // sample $route = 'routeName', $params = ['action'=> $someAction],
        // $options = ['query' => 'id=1']
        if (!method_exists($this, 'plugin')) {
            throw new Exception\DomainException(
                'Redirect plugin requires a controller that defines the plugin() method'
            );
        }
        $urlPlugin = $this->plugin('url');
        $url = $urlPlugin->fromRoute($route, $params, $options);
        return $url;
    }
}
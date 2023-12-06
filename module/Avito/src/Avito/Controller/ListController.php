<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 23:39
 */

namespace Avito\Controller;

use Avito\Model\Avito;
use Avito\Model\Green;
use Parser\Controller\AbstractController;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Psr\Container\ContainerInterface;
use Laminas\View\Model\ViewModel;


/**
 * Class ListController
 * @package Parser\Controller
 * @inheritdoc
 */
class ListController extends AbstractController
{
    private $db;
    private $container;
    /* @var $proxy Proxy */
    private $proxy;
    private $userAgent;

    public function __construct(Config $config, $container)
    {
        /**
         * @var $proxy Proxy
         */
        /**
         * @var $container ContainerInterface
         */
        $this->container = $container;
        $this->proxy = $this->container->get(Proxy::class);
        /**
         * @var $userAgent UserAgent
         */
        $userAgent = $this->container->get(UserAgent::class);
        $this->userAgent = $userAgent;
        $this->config = $config;
        $this->db = $this->config->getDb();
        $this->authActions = ['list'];
    }

    public function listAction()
    {
        $result = new ViewModel([
            'data' => $this->container->get('Config'),
        ]);
        //$result->setTerminal(true);
        return $result;
    }

    public function indexAction()
    {
        $timeStart = microtime(1);
        // reading config xml file

        $limiter = new ProcessLimiter($this->config, [
            'path' => 'avito',
            'expireTime' => '10800',
            'processLimit' => 100,
        ]);
        $productData = [];
        if (($limiterID = $limiter->initializeProcess()) && $this->proxy->loadAvailableProxy()) {

            try {
                $avito = new Avito($this->config);
                if (! $avito->hasErrors()) {
                    $productData = $avito->getOffers($limiter);
                } else {
                    $productData = ["errors" => $avito->getErrors()];
                }
            } catch (\Exception $e) {
                Helper::logException($e, 'avito.error.log');
            }
            $limiter->delete(['process_limiter_id' => $limiterID]);
        } else {
            $productData['error'] = 'Active Connections limit reached, try to start sync later';
        }

        $timeEnd = microtime(1);
        $productData['parsingTime (ms)'] = (int)(1000 * ($timeEnd - $timeStart));
        pr($productData);
        return $this->zeroTemplate();
    }

    public function greenAction()
    {
        $content = file_get_contents('data/green.txt');
        $green = new Green($this->proxy, $this->userAgent);
        //$green->processHtml($content);
        $green->iterateUrls();
        die();
    }

    public function greenlistAction()
    {
        $green = new Green($this->proxy, $this->userAgent);
        //$green->processHtml($content);
        print_r($green->getList());
        die();
    }

}
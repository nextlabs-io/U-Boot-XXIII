<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2018
 * Time: 23:39
 */

namespace Parser\Controller;

use Parser\Model\Amazon\BrandBlacklist;
use Parser\Model\Amazon\Seller;
use Parser\Model\Helper\Config;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\View\Model\ViewModel;


class ConfigurationController extends AbstractController
{
    private $db;
    private $proxy;
    private $userAgent;

    public function __construct(Config $config)
    {
        $this->config = $config;
        /* @var $db AdapterInterface */
        $this->db = $config->getDb();
        $this->proxy = new Proxy($this->db, $config);
        $this->userAgent = new UserAgent($this->db);
        $this->authActions = ['list'];
    }


    public function listAction()
    {
        $data = [];
        $brandList = new BrandBlacklist($this->config);
        $sellerList = new Seller($this->config);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            //print_r($post);
            $brandList->saveData($post['brand']);
            $sellerList->saveData($post['seller']);
        }

        $brandData = $brandList->loadData();
        $sellerData = $sellerList->loadData();


        $result = new ViewModel([
            'brandList' => $brandData,
            'sellerList' => $sellerData,
        ]);
        return $result;

    }


}


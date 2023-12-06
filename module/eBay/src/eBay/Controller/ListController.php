<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 23:39
 */

namespace eBay\Controller;

use eBay\Model\Form\SearchForm;
use eBay\Model\Manager;
use Parser\Controller\AbstractController;
use Parser\Model\Helper\Config;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\View\Model\ViewModel;


/**
 * Class ListController
 * @package Parser\Controller
 * @inheritdoc
 */
class ListController extends AbstractController
{
    private $db;
    /* @var $proxy Proxy */
    private $proxy;
    private $userAgent;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = $this->config->getDb();
        /**
         * @var $proxy Proxy
         */
        $this->proxy = new Proxy($this->db, $config);
        /**
         * @var $userAgent UserAgent
         */
        $this->userAgent = new UserAgent($this->db);
        $this->authActions = ['list', 'index'];
    }

    public function listAction()
    {
        $result = new ViewModel([]);
        $result->setTemplate('zero');
        return $result;
    }

    public function indexAction()
    {
        $request = $this->getRequest();
        $list = [];
        $manager = new Manager();
        $form = new SearchForm();
        $message = '';
        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            $form->setData($post);
            if ($form->isValid()) {
                $search = $form->get('search')->getValue();
                $list = $manager->testRequest($search);
                if(!$list) {
                    $message = $manager->connectMessage;
                    $list = [];
                }
            } else {
                // give some message
                $message = 'Please specify search field';
            }
        }

        $result = new ViewModel([
            'message' => $message,
            'form' => $form,
            'items' => $list,
        ]);
        //$result->setTerminal(true);
        return $result;
    }

}
<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 06.03.2017
 * Time: 23:39
 */

namespace BestBuy\Controller;

use BestBuy\Model\BestBuy\Category;
use BestBuy\Model\BestBuy\KeepaAPI;
use BestBuy\Model\BestBuy\Product;
use BestBuy\Model\BestBuy\ProductKeepa;
use BestBuy\Model\BestBuy\ProductKeepaData;
use Parser\Controller\AbstractController;
use Parser\Model\Configuration\ProductSyncable;
use BestBuy\Model\Form\UploadForm;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\SimpleObject;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\View\Model\ViewModel;


/**
 * Class ListController
 * @package Parser\Controller
 * @inheritdoc
 */
class KeepaController extends AbstractController
{
    private $db;
    /* @var $proxy Proxy */
    private $proxy;
    /* @var $proxy UserAgent */
    private $userAgent;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->proxy = new Proxy($this->config->getDb(), $this->config);
        $this->userAgent = new UserAgent($this->config->getDb());
        $this->authActions = ['index',];
    }

    public function checkActionForAuth($action): bool
    {
        $auth = $this->getAuth();
        if ($auth->hasIdentity()) {
            $this->identity = $auth->getIdentity();
        }
        if ($this->identity === 'Store Owner') {
            return false;
        }
        return parent::checkActionForAuth($action);
    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function indexAction()
    {
        $request = $this->getRequest();
        $data = [];
        $asin = $this->params()->fromQuery('asin', 'asin');
        $locale = '';
        $product = new \Parser\Model\Product($this->config, $this->proxy, $this->userAgent, $asin, 'ca');
        $locales = $product->getLocalesForForm();
        $keepa = new ProductKeepa($this->config);

        $tokensLeft = $keepa->keepa->tokensLeft;
        $apiKeyObfuscated = Helper::obfuscateString($keepa->keepa->getApiKey());
        $uploadForm = new UploadForm('', ['locales' => $locales]);

        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            $locale = $this->params()->fromPost('locale', '');

            $uploadForm->setData($post);

            if ($uploadForm->isValid()) {

                $data = $uploadForm->getData();
                $fileErrors = $uploadForm->get('asins')->getMessages();
                $tempFile = $uploadForm->get('asins')->getValue();
                $list = $uploadForm->get('asins_list')->getValue();

                if (!$locale) {
                    $keepa->msg->addError('No locale specified');
                }
                if (!$tempFile['tmp_name'] && !$list) {
                    $keepa->msg->addError('Please specify the file or put asins into textarea');
                }
                if ($tempFile['tmp_name'] && $fileErrors) {
                    $keepa->msg->addError('failed to upload file ' . implode(',', $fileErrors));
                }
                if (!$keepa->msg->hasErrors()) {
                    $asins = [];
                    if ($tempFile['tmp_name']) {
                        $asins = Helper::extractAsinsFromFile($tempFile['tmp_name']);
                    }
                    $asins_list = [];
                    if ($list) {
                        $asins_list = Helper::extractAsinsFromString($list);
                    }

                    $asins = array_merge($asins, $asins_list);

                    //print_r($asins);die();
                    if (is_array($asins)) {
                        $added = $keepa->addNewProducts($asins, $locale);
//                        $keepa->msg->addMessage(count($added) . ' Found for processing');
                    }

                }
            } else {
                $keepa->msg->addError('please choose locale and file');
            }

        }
        $totals = $keepa->getAggregatedData();
        return new ViewModel([
            'form' => $uploadForm,
            'items' => $data,
            'message' => $keepa->msg->getStringMessages('<br />'),
            'errors' => $keepa->msg->getStringErrorMessages('<br />'),
            'locales' => $locales,
            'syncableList' => ProductSyncable::getOptions(),
            'locale' => $locale,
            'tokensLeft' => $tokensLeft,
            'apiKeyObfuscated' => $apiKeyObfuscated,
            'totals' => $totals,
        ]);

    }

    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function scrapeAction(): ViewModel
    {
        $pk = new ProductKeepa($this->config);
        $product = $pk->getScrapeCandidate();
        if ($product) {
            $pk->setStatus($pk::STATUS_CURRENTLY_IN_PROGRESS, $product[$pk->getTableKey()]);
        }
        $status = $pk->checkKeepa($product);

        return $this->zeroTemplate();
    }

    /**
     * @return int|ViewModel|null
     * @throws \Exception
     */
    public function cronAction()
    {

        $delay = $debugMode = $this->params()->fromQuery('a', 1);
        if($delay){
            sleep($delay);
        }
        $debugMode = $this->params()->fromQuery('debug', '');
        $this->config->setProperty('DebugMode', $debugMode);

        $product = new ProductKeepa($this->config);

        if (!$product->keepa->tokensLeft || $product->keepa->tokensLeft < 10) {
            die('no tokens left or check keepa API');
        }
        $regularSyncPath = $product->getConfig('settings', 'processId') ?: 'keepa';
        $processExpireDelay = $product->getConfig('settings', 'processExpireDelay') ?: 240;
        $activeConnectionsLimit = $product->getConfig('settings', 'activeConnectionsLimitKeepa') ?: 1;
        $maxToProcess = $product->getConfig('settings', 'maxToProcess') ?: 1;
        $limiter = new ProcessLimiter($this->config, [
            'path' => $regularSyncPath,
            'expireTime' => $processExpireDelay,
            'processLimit' => $activeConnectionsLimit,
        ]);


        if ($limiterID = $limiter->initializeProcess()) {
            $product->setNeverCheckedForFailed();
            $product->setLimiter($limiter);
            try {
                for ($i = 0; $i < $maxToProcess; $i++) {
                    $result = $product->scrapeProduct();
                    if (!$result) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                Helper::logException($e, 'scrapeProductKeepa.error.log');
            }
            $limiter->delete(['process_limiter_id' => $limiterID]);
        } else {
            $product->msg->addMessage('Active Connections limit reached, try to start sync later');

        }
        $result = new ViewModel([
            'message' => $product->msg->getStringMessages(),
            'error' => $product->msg->getStringErrorMessages(),
        ]);
        $result->setTerminal(true);
        return $result;
    }

    /**
     * @throws \Exception
     */
    public function changeAction(): ViewModel
    {
        $product = new ProductKeepa($this->config);
//        $product->update(['technical' => 5], []);
        $product->setNeverCheckedForFailed();
        $product->processKeepaFieldChange(5, 2, 100, 1000);

        return $this->zeroTemplate();
    }

    /**
     * @return \Laminas\Http\Response
     * @throws \Exception
     */
    public function refreshAction(): \Laminas\Http\Response
    {
        $product = new ProductKeepa($this->config);
        $where = new Where();
        $where->equalTo('status', $product::STATUS_NOT_FOUND);
        $product->update(['status' => $product::STATUS_NEVER_CHECKED], $where);
        return $this->redirect()->toRoute('keepa', []);
    }

    public function exportAction(){
        $regularSyncPath = 'keepa_export';
        $processExpireDelay = 240;
        $activeConnectionsLimit = 1;
        $limiter = new ProcessLimiter($this->config, [
            'path' => $regularSyncPath,
            'expireTime' => $processExpireDelay,
            'processLimit' => $activeConnectionsLimit,
        ]);
        if ($limiterID = $limiter->initializeProcess()) {
            $pkd = new ProductKeepaData($this->config);
            $qty = $pkd->exportKeepaTable();
            pr('exported '. $qty. ' items');
            $limiter->delete(['process_limiter_id' => $limiterID]);
        } else {
            pr('Active Connections limit reached, try to start sync later');
        }
        return $this->zeroTemplate();
    }

    public function testLengthAction(){
        $pkd = new ProductKeepaData($this->config);
        $sql = new Sql($this->config->getDb());
        $select = $sql->select($pkd->getTable());
        $select->columns(['title' => 'title', 'sql_length' => new Expression(' LENGTH(title)'), 'sql_char_length' => new Expression('CHAR_LENGTH(title)')]);
        $select->limit(1);
        $select->order('sql_length DESC' );
        //$select->where();

        $rowSet = $pkd->selectWith($select);
        $data = $rowSet->current();
        $data['php_length'] = strlen($data['title']);
        $data['php_mb4_length'] = mb_strlen($data['title']);
        pr($data);
        return $this->zeroTemplate();
    }
}
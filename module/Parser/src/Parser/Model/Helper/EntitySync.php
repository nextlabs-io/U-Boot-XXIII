<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 30.11.2020
 * Time: 14:21
 */

namespace Parser\Model\Helper;





use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\SimpleObject;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\Db\Sql\Predicate\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;


class EntitySync extends SimpleObject
{

    /**
     * @var Config
     */
    protected $globalConfig;
    /**
     * @var Sql
     */
    protected $sql;

    /* @var $proxy Proxy */
    protected $proxy;
    /* @var $userAgent UserAgent */
    protected $userAgent;
    /**
     * @var DefaultTablePage
     */
    public $entity;

    public function __construct(Config $config)
    {
        $this->globalConfig = $config;
        $this->sql = new Sql($this->globalConfig->getDb());
        $this->userAgent = new UserAgent($this->globalConfig->getDb());
        $this->proxy = new Proxy($this->globalConfig->getDb(), $config);
    }

    public function initialize(DefaultTablePage $entity){
        $this->entity = $entity;
        $processExpireDelay = $entity->getConfig('processLimiter', 'processExpireDelay') ?: 240;
        $activeConnectionsLimit = $entity->getConfig('processLimiter', 'activeConnectionsLimit') ?: 5;

        $regularSyncPath = $entity->getConfig('processLimiter', 'processId') ?: '200719';

        $limiter = new ProcessLimiter($this->globalConfig, [
            'path' => $regularSyncPath,
            'expireTime' => $processExpireDelay,
            'processLimit' => $activeConnectionsLimit,
        ]);
        if(!$this->proxy->loadAvailableProxy()){
            $this->addError('failed to load proxy');
            return false;
        }
        if (($limiterID = $limiter->initializeProcess())) {
            $this->entity->setLimiter($limiter);
            return $limiter;
        } else {
            $message = 'Active Connections limit reached, try to start sync later';
            $this->addError($message);
            return false;
        }
    }

}
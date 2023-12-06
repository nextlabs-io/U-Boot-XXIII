<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 26.11.2018
 * Time: 22:14
 */

namespace Parser\Model\Helper;


use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

/**
 * Class SyncProcessLimiter
 * This class to find out how many sync processes are running and to get process limits
 */
class ProcessLimiter extends TableGateway
{
    public $config;
    public $processLimit;
    /** @var $expireTime integer in seconds */
    private $expireTime;
    private $path;
    /**
     * @var int
     */
    private $limiterId;

    /**
     * SyncProcessLimiter constructor.
     * @param Config $config
     * @param array $options
     */
    public function __construct(Config $config, $options = [])
    {
        $this->config = $config;

        // process path, required to separate different processes
        $this->path = $options['path'] ?? 0;
        // expiration time in seconds
        $this->expireTime = $options['expireTime'] ?? 240;

        // maximum number of processes
        $this->processLimit = $options['processLimit'] ?? 5;
        $table = 'process_limiter';
        parent::__construct($table, $config->getDb());
    }

    /**
     * @param null $path
     * @return bool|string - returns the index file name if we didn't reach the process limit
     */
    public function initializeProcess($path = null)
    {
        $path = $path ?: $this->path;
        // check if we reach the limit
        if ($this->getProcessCount($path) >= $this->processLimit) {
            return false;
        }

        $result = $this->insert([
            'path_id' => $path,
            'created' => new Expression('NOW()'),
            'updated' => new Expression('NOW()'),
            'expire' => new Expression('DATE_ADD(NOW(), INTERVAL  ' . $this->expireTime . ' SECOND)'),
        ]);
        if ($result) {
            $this->limiterId = $this->getLastInsertValue();
            return $this->limiterId;
        }
        return false;
    }

    public function getProcessCount($path = null)
    {
        // clear expired first
        $this->clearOldProcesses();

        $path = $path ?: $this->path;
        $sql = new Sql($this->getAdapter());
        $select = $sql->select($this->getTable())
            ->columns(['qty' => new Expression('count(*)')])
            ->where(['path_id' => $path]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        if ($data = $result->current()) {
            return $data['qty'];
        }
        return 0;
    }

    /**
     * @return int
     */
    public function clearOldProcesses()
    {
        $where = new Where();
        $where->lessThan('expire', new Expression('NOW()'));
        return $this->delete($where);
    }

    /**
     * trigger alive status of the process
     * TODO throw exception if the process is dead. It might appear, that process is deleted already at the moment of update, in this case - script should stop.
     * @param $processID int
     * @param string $tag
     * @return int
     */
    public function touchProcess($processID = '', $tag = ''): int
    {
        if(!$processID) {
            $processID = $this->getLimiterId();
        }

        $updated = $this->update([
            'updated' => new Expression('NOW()'),
            'expire' => new Expression('DATE_ADD(NOW(), interval ' . $this->expireTime . ' second)'),
        ], ['process_limiter_id' => $processID]);

        if (!$updated && $tag) {
            // no process has been updated, it was deleted probably
            $this->config->logger->add($tag, 'failed to touch process with id ' . $processID);
        }
        return $updated;
    }


    public function closeProcess($id = null): int
    {
        if(!$id){
            $id = $this->getLimiterId();
        }
        if($id) {
            return $this->delete(['process_limiter_id' => $id]);
        }
        return 0;
    }

    /**
     * @return int
     */
    public function getLimiterId(): int
    {
        return $this->limiterId;
    }

    /**
     * @param int $limiterId
     * @return ProcessLimiter
     */
    public function setLimiterId(int $limiterId): ProcessLimiter
    {
        $this->limiterId = $limiterId;
        return $this;
    }
}
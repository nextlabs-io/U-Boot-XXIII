<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 19.03.2020
 * Time: 13:46
 */

namespace Parser\Model;

/*
 *  further evolution of the basic table page class. adding some behaviour describing attributes
 *  this class also utilizes store of the page content in a separate table.
 */

use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\Html\Dropdown;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Update;
use Laminas\Db\Sql\Where;


/**
 * declare fields in the constructor, fields are processed via filter before update/insert
 *
 * Class DefaultTablePage
 * @package Parser\Model
 */
class DefaultTablePage extends TablePage
{
    // completed processing
    public const STATUS_SUCCESS = 1;
    public const STATUS_NEVER_CHECKED = 50;
    public const STATUS_UNKNOWN_ERROR = 30;
    public const STATUS_NOT_FOUND = 2;
    public const STATUS_FAILED = 10;
    public const STATUS_FAILED_TO_EXTRACT_FIELDS = 11;

    // processing is handled (it might be handled during several attempts)
    public const STATUS_IN_PROGRESS = 20;
    // processing is being performed right now
    public const STATUS_CURRENTLY_IN_PROGRESS = 100;

    public const DEBUG_SAVE_MODE = 3;
    public const DEBUG_PRINT_MODE = 1;

    // debug mode does not scrape for url, if content is stored already and more
    public $debugMode;
    public $msg;
    // set if to save content to db.
    /**
     * @var int $totalResults
     */
    public $totalResults;
    protected $storeContent = true;
    protected $tableKey;
    /** @var ProcessLimiter */
    protected $limiter;

    public function __construct($url, Config $globalConfig, $table, $tableKey)
    {
        parent::__construct($url, $globalConfig, $table);

        $this->tableKey = $tableKey;

        $this->debugMode = $globalConfig->getProperty('DebugMode');

        // default fields list, to be modified in a child class, status - sets the crawling status, curl_code -resulted scraping code, content - html content of the page, Note 'created', 'updated' fields should always be in the table.
        $this->fields = ['status', 'curl_code', 'content'];
        $this->msg = new SimpleObject();
    }

    public function scrapeSuccessMarker(): bool
    {
        // take content and define whether there is a required data or not
        return true;
    }

    /**
     * clean broken items, this function should be triggered by cron or time to time in order to fix items which remain in in progress status too long.
     */
    public function resetInProgressItems()
    {
        $where = new Where();
        $where->equalTo('status', self::STATUS_IN_PROGRESS);
        $where->lessThan('created', new Expression('DATE_SUB(NOW(), INTERVAL 1 HOUR)'));
        $this->update(['status' => self::STATUS_UNKNOWN_ERROR], $where);
    }

    /**
     * Update
     *
     * @param array $set
     * @param string|array|\Closure $where
     * @param null|array $joins
     * @return int
     */
    public function itemUpdate($set, $where = null, array $joins = null)
    {
        if ($this->limiter) {
            $this->limiter->touchProcess();
        }
        if ($set) {
            $set['updated'] = new Expression('NOW()');
        }
        return parent::update($set, $where, $joins);
    }

    public function setContent($content)
    {
        return gzcompress($content);
    }

    /**
     * @param $status
     * @param $where
     * @return int
     * @throws \Exception
     */
    public function setStatus($status, $where)
    {
        if (!is_array($where)) {
            $where = [$this->tableKey => $where];
        }
        if (in_array($status, $this->getAllStatuses())) {
            return $this->update(['status' => $status], $where);
        } else {
            throw new \Exception('wrong status for ' . $this->getTable() . ': ' . $status);
        }
        return false;
    }

    public function getAllStatuses()
    {
        return [self::STATUS_FAILED_TO_EXTRACT_FIELDS, self::STATUS_FAILED, self::STATUS_NOT_FOUND, self::STATUS_SUCCESS, self::STATUS_UNKNOWN_ERROR, self::STATUS_NEVER_CHECKED, self::STATUS_IN_PROGRESS, self::STATUS_CURRENTLY_IN_PROGRESS];
    }

    /**
     * @param ProcessLimiter $limiter
     */
    public function setLimiter(\Parser\Model\Helper\ProcessLimiter $limiter): void
    {
        $this->limiter = $limiter;
    }

    public function limiterDelete(): void
    {
        if ($this->limiter) {
            $this->limiter->delete(['process_limiter_id' => $this->limiter->getLimiterId()]);
        }
    }

    /**
     * @param $filterKey - key in the profile to store filter preferences
     * @param $requestData - filter options got from request
     * @param bool $resetFilterFlag - if to reset filter flag
     * @return array
     */
    public function getListFilter($filterKey, $requestData, $resetFilterFlag = false)
    {
        $auth = $this->globalConfig->auth;
        $identity = null;
        if ($auth->hasIdentity()) {
            $identity = $auth->getIdentity();
            $profile = new Profile($this->globalConfig->getDb(), $identity);
            $profile->load();
            $filterSaved = $profile->loadConfigData($filterKey);

            if (!$resetFilterFlag) {
                $filter = array_merge($filterSaved, $requestData);
//                pr($filter);pr($requestData);die();
            } else {
                $filter = $this->prepareListFilter([]);
            }
            if (serialize($filterSaved) !== serialize($filter)) {
                if (($filter['per-page'] ?? null)
                    && ($filterSaved['per-page'] ?? null)
                    && $filter['per-page'] != $filterSaved['per-page']) {
                    // reset page if paging is changed.
                    $filter['page'] = 1;
                }
                $profile->updateData([$filterKey => $filter]);
            }
            return $this->prepareListFilter($filter);
        }

        return [];
    }

    public function prepareListFilter($filter)
    {
        // got only fields related to the model.
        $fields = [
            'page' => '1',
            'status' => '',
            'per-page' => 100,
            'title' => ''
        ];
        $filter = array_intersect_key($filter, $fields);
        $filter = array_merge($fields, $filter);
        return $filter;
    }

    /**
     * @return int
     */
    public function getTotalResults(): int
    {
        return $this->totalResults;
    }

    /**
     * @return int
     */
    public function fixInProgressHangingItems()
    {
        // will set from currently in progress to in progress for categories which were failed processing due to some reason
        $where = new Where();
        $where->equalTo('status', self::STATUS_CURRENTLY_IN_PROGRESS);
        $where->lessThan('updated', new Expression('DATE_SUB(NOW(), INTERVAL 2 HOUR)'));
        $this->update(['status' => self::STATUS_IN_PROGRESS, 'updated' => new Expression('NOW()')], $where);

        $where = new Where();
        $where->equalTo('status', self::STATUS_FAILED_TO_EXTRACT_FIELDS);
        $where->lessThan('updated', new Expression('DATE_SUB(NOW(), INTERVAL 4 HOUR)'));
        $this->update(['status' => self::STATUS_NEVER_CHECKED, 'updated' => new Expression('NOW()')], $where);

        return 1;

    }

    public function getTableKey()
    {
        return $this->tableKey;
    }

    public function getStatusDropdown($selected): string
    {
        $list = [
            -1 => '',
            self::STATUS_NEVER_CHECKED => 'not checked',
            self::STATUS_SUCCESS => '<span class="border-green">finished</span>',
            self::STATUS_IN_PROGRESS => '<span class="blue">in progress</span>',
            self::STATUS_UNKNOWN_ERROR => 'unknown error',
            self::STATUS_FAILED => 'failed to process',
            self::STATUS_NOT_FOUND => 'not found',
            self::STATUS_FAILED_TO_EXTRACT_FIELDS => 'failed to extract fields',
            self::STATUS_CURRENTLY_IN_PROGRESS => '<span class="red">working now...</span>',
        ];

        return Dropdown::getHtml($list, $selected,
            [
                'name' => 'filter[status]',
                'aria-controls' => 'datatable-responsive',
                'class' => 'col-lg-12 form-control padd-top',
                'id' => 'filter-status',
            ], ['no-default-value' => 1]);
    }

    public function getCondition($filter, $tablePrefix = 'l'): Where
    {
        $where = new Where();
        if (-1 !== (int)($filter['status'] ?? null)) {
            $where = $this->setWhere($filter, 'status', 'equalTo', $where, [], $tablePrefix);
        }
        if ($filter['title'] ?? null) {
            $titleId = $tablePrefix ? $tablePrefix . '.title' : 'title';
            $urlId = $tablePrefix ? $tablePrefix . '.url' : 'url';
            $where->nest()
                ->like($titleId, '%' . $filter['title'] . '%')
                ->or
                ->like($urlId, '%' . $filter['title'] . '%')
                ->unnest();
        }
        return $where;
    }

    /**
     * @param $data
     * @param $value
     * @param $action
     * @param $where
     * @param array $validate
     * @param string $tablePrefix
     * @return Where
     */
    public function setWhere($data, $value, $action, $where, $validate = [], $tablePrefix = ''): Where
    {
        if (self::validateWhereValue($data, $value, $validate)) {
            /**
             * @var Where $where
             */
            $fieldName = $tablePrefix ? $tablePrefix . '.' . $value : $value;
            switch ($action) {
                case 'equalTo':
                    $where->equalTo($fieldName, $data[$value]);
                    break;
                case 'like':
                    $where->like($fieldName, '%' . $data[$value] . '%');
                    break;
                case 'greaterThan' :

                    if (!in_array('datetime', $validate)) {
                        $properValue = $data[$value] - 0.001;
                    } else {
                        $date = \DateTime::createFromFormat('d/m/y H:i', $data[$value]);
                        $properValue = $date->format('Y-m-d H:i:s');
                    }
                    $value = strtolower(str_replace('from', '', $value));
                    $fieldName = $tablePrefix ? $tablePrefix . '.' . $value : $value;
                    $where->greaterThanOrEqualTo($fieldName, $properValue);
                    break;
                case 'lessThan' :
                    if (!in_array('datetime', $validate)) {
                        $properValue = $data[$value];
                    } else {
                        $date = \DateTime::createFromFormat('d/m/y H:i', $data[$value]);
                        $properValue = $date->format('Y-m-d H:i:s');
                    }
                    $value = strtolower(substr($value, 2));
                    $fieldName = $tablePrefix ? $tablePrefix . '.' . $value : $value;
                    $where->lessThanOrEqualTo($fieldName, $properValue);
                    break;
            }
        }
        return $where;
    }

    /**
     * @param $data
     * @param $value
     * @param array $validate
     * @return bool
     */
    public static function validateWhereValue($data, $value, $validate = []): bool
    {
        if (isset($data[$value]) && strlen($data[$value])) {
            if (count($validate)) {
                $isValid = true;
                foreach ($validate as $validator) {
                    switch ($validator) {
                        case 'int':
                            $isValid = ((int)$data[$value] || $data[$value] === 0);
                            break;
                        case 'float':
                            $isValid = ((float)$data[$value] || is_numeric($data[$value]));
                            break;
                        case 'datetime':
                            //"07/31/2018 12:46 AM"
                            $validator = new \Laminas\Validator\Date(['format' => 'd/m/y H:i']);
                            $isValid = $validator->isValid($data[$value]);
                            break;
                    }
                    if (!$isValid) {
                        return false;
                    }
                }
                return $isValid;
            }
            return true;
        }
        return false;
    }

    public function checkExistById($id)
    {
        $rowSet = $this->select([$this->tableKey => $id]);
        return $rowSet->current()[$this->tableKey] ?? null;
    }

    /**
     * @param $errorKey - unique id of the error
     * @param $content - content under which scraping were performed
     * @param $action - a string which represents the performed action, use to easier split errors under directories
     */
    public function registerError($errorKey, $content, $action): void
    {
        // if some scraping went into error, you can save content in order to revise it later
        $fileDir = getcwd() . '/data/errors/' . $action;
        if (!is_dir($fileDir) && !mkdir($fileDir) && !is_dir($fileDir)) {
            throw new \RuntimeException(sprintf('registerError directory "%s" was not created', $fileDir));
        }
        $filePattern = 'data/errors/' . $action . '/{path}';
        Helper::saveContentToFileByPath($errorKey, $content, $filePattern);
    }

    /**
     * @param $errorKey
     * @param $action
     * @return bool|false|string
     */
    public function getRegisteredErrorContent($errorKey, $action)
    {
        $filePattern = 'data/errors/' . $action . '/{path}';
        return Helper::getContentFromFileByPath($errorKey, $filePattern);
    }

    /**
     * @param array|Where $where
     * @return array
     */
    protected function getScrapeCandidate($where = null): array
    {
        $select = new Select($this->table);
        if (!$where) {
            $candidateStatuses = $this->getUncompletedStatuses();
            $finalStatuses = $this->getFinalStatuses();
            $finalStatuses[] = self::STATUS_CURRENTLY_IN_PROGRESS;
            $where = new Where();
            $where->in('status', $candidateStatuses);
            $where->notIn('status', $finalStatuses);
            $select->order('status desc');
        }

        $select->where($where);
        $select->limit(1);
        $rowSet = $this->selectWith($select);
        return $rowSet->current() ? (array)$rowSet->current() : [];
    }

    public function getUncompletedStatuses()
    {
        return [self::STATUS_NEVER_CHECKED, self::STATUS_IN_PROGRESS];
    }

    public function getFinalStatuses()
    {
        return [self::STATUS_FAILED_TO_EXTRACT_FIELDS, self::STATUS_FAILED, self::STATUS_NOT_FOUND, self::STATUS_SUCCESS, self::STATUS_UNKNOWN_ERROR];
    }

    protected function getIdFromArray($data)
    {
        return $data[$this->tableKey] ?? null;
    }

    protected function getTotalQty()
    {
        $select = new Select(' ');
        $select->columns(['qty' => new Expression('FOUND_ROWS()')]);
        $select->setSpecification(Select::SELECT, array(
            'SELECT %1$s' => array(
                array(1 => '%1$s', 2 => '%1$s AS %2$s', 'combinedby' => ', '),
                null
            )
        ));
        $rowSet = $this->selectWith($select);
        return $rowSet->current()['qty'];
    }

    public function addPaging(Select $select, array $filter, bool $ignorePaging){
        $page = (int)($filter['page'] ?? 1);
        if ($page && !$ignorePaging) {
            $limit = $filter['per-page'] ?? 100;
            $select->limit($limit);
            $select->offset(($page - 1) * $limit);
        }
        $select->quantifier(new Expression('SQL_CALC_FOUND_ROWS'));
        return $select;
    }

    public function updateById($data)
    {
        if(isset($data[$this->getTableKey()])) {
            $where = [$this->getTableKey() => $data[$this->getTableKey()]];
            unset($data[$this->getTableKey()]);
            return $this->update($data, $where);
        }
        return 0;
    }
}
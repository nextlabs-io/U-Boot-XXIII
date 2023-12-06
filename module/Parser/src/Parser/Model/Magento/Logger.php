<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 05.06.2019
 * Time: 14:19
 */

namespace Parser\Model\Magento;

use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Html\Dropdown;
use Laminas\Db\Sql\Expression as Expr;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class Logger extends TableGateway
{
    public static $tableKey = 'parser_magento_log_id';
    public static $actions = [
        'ping' => 'check product stock and price (and other data) within magento',
        'update' => 'update price/stock using magmi',
        'updateDesc' => 'update description fields',
        'create' => 'create product',
        'delete' => 'delete product',
    ];
    public static $orderAllowed = ['l.action', 'l.store_id', 'l.created', 'l.parser_magento_log_id', 'type'];
    private static $fields = [
        'action' => 'int',
        'product_id' => 'int',
        'store_id' => 'int',
        'message' => 'string',
        'error' => 'string',
        'description' => 'text',
        'created' => 'timestamp'
    ];
    public $lastInsertValue;
    public $table;
    public $adapter;
    public $data;
    public $config;
    public $descriptionData = [
        'action' => 'Action type',
        'product_id' => 'Product ID',
    ];
    public $totalItems;

    public function __construct($db)
    {
        $table = 'parser_magento_log';
        parent::__construct($table, $db);
    }

    public static function prepareListFilter($filter)
    {
        $fields = [
            'p.asin' => '',
            'p.title' => '',
            'l.store_id' => '-1',
            'l.action' => '-1',
            'l.parser_magento_log_id' => '',
            'sortCreated' => '',
            'sortId' => '',
            'type' => '',
            'page' => '',
            'fromCreated' => '',
            'toCreated' => '',
            'per-page' => 100,
            'sort_column' => 'l.created',
            'sort_type' => 'desc',
        ];
        foreach ($fields as $key => $field) {
            if (!isset($filter[$key])) {
                $filter[$key] = $field;
            }
        }
        $actionKeys = array_keys(self::$actions);
        $actionList = [-1 => ''];
        foreach ($actionKeys as $key => $value) {
            $actionList[$key] = $value;
        }
        $filter['actionList'] = $actionList;
        return $filter;
    }

    public static function cleanLogs($db): void
    {
        $sql = new Sql($db);
        $delete = $sql->delete('parser_magento_log');
        $where = new Where();
        $where->lessThan('created', new Expression('DATE_SUB(NOW(), INTERVAL 1 MONTH)'));
        $delete->where($where);
        $stmt = $sql->prepareStatementForSqlObject($delete);
        $stmt->execute();
    }

    public function getList($data = []): array
    {
        $filter = $data['filter'] ?? [];
        $where = $this->getCondition($filter);
        $filter['page'] = $filter['page'] ?? 1;
        $filter['sort_type'] = $filter['sort_type'] ?? 'desc';
        $filter['sort_column'] = $filter['sort_column'] ?? '';

        if ($filter['sort_column'] !== '' && in_array($filter['sort_column'], self::$orderAllowed, true)) {
            $sortType = $filter['sort_type'] === 'desc' ? ' desc' : ' asc';
            $order = $filter['sort_column'] . $sortType;
        } else {
            $order = 'l.created DESC';
        }

        // calculating total count
        $sql = new Sql($this->getAdapter());
        $select = $sql->select(['l' => $this->getTable()])
            ->join(['p' => 'product'], 'l.product_id = p.product_id',
                ['title', 'asin'], Join::JOIN_LEFT)
            ->join(['m' => 'parser_magento'], 'l.store_id = m.parser_magento_id',
                ['magento' => 'title'], Join::JOIN_LEFT)
            ->where($where)
            ->columns(['count' => new Expr('COUNT(*)')]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $num = $stmt->execute();
        if ($data = $num->current()) {
            $this->totalItems = $data['count'];
        }

        $select = new Select(['l' => $this->getTable()]);
        $select->join(['p' => 'product'], 'l.product_id = p.product_id',
            ['title', 'asin', 'locale'], Join::JOIN_LEFT)
            ->join(['m' => 'parser_magento'], 'l.store_id = m.parser_magento_id',
                ['magento' => 'title'], Join::JOIN_LEFT)
            ->where($where)
            ->columns(['type' => new Expr("IF(l.error > '', 'error', 'message')"), '*'])
            ->order($order);

        $perPage = $filter['per-page'] ?? 100;

        if (!isset($filter['no-limit'])) {
            $select->limit($perPage);
            $offset = $filter['page'] > 1 ? ((int)$filter['page'] - 1) * $perPage : 0;
            if ($offset) {
                $select->offset($offset);
            }
        }

        $rowSet = $this->selectWith($select);
        $data = [];
        while ($line = $rowSet->current()) {
            $data[] = (array)$line;
            $rowSet->next();
        }
        return $data;
    }

    public function getCondition($filter)
    {
        $where = new Where();
        if (isset($filter['l.action']) && $filter['l.action'] >= 0) {
            $where = $this->setWhere($filter, 'l.action', 'equalTo', $where);
        }
        $where = $this->setWhere($filter, 'p.asin', 'equalTo', $where);
        $where = $this->setWhere($filter, 'p.title', 'like', $where);
        if (isset($filter['l.store_id']) && $filter['l.store_id'] >= 0) {
            $where = $this->setWhere($filter, 'l.store_id', 'equalTo', $where);
        }
        if (isset($filter['type']) && $filter['type'] === 'error') {
            $where->greaterThan('l.error', '');
        }
        if (isset($filter['type']) && $filter['type'] === 'message') {
            $where->equalTo('l.error', '');
        }

        /* hrenaten' s from and to, from and to are removed and field name strtolowered  and item. added*/
        $where = $this->setWhere($filter, 'fromCreated', 'greaterThan', $where, ['datetime']);
        $where = $this->setWhere($filter, 'toCreated', 'lessThan', $where, ['datetime']);
        return $where;
    }

    private function setWhere($data, $value, $action, $where, $validate = [])
    {
        if (self::validateWhereValue($data, $value, $validate)) {
            /**
             * @var Where $where
             */
            switch ($action) {
                case 'equalTo':
                    $where->equalTo($value, $data[$value]);
                    break;
                case 'like':
                    $where->like($value, '%' . $data[$value] . '%');
                    break;
                case 'greaterThan' :
                    if (!in_array('datetime', $validate, true)) {
                        $properValue = $data[$value] - 0.001;
                    } else {
                        $date = \DateTime::createFromFormat('d/m/y H:i', $data[$value]);
                        $properValue = $date->format('Y-m-d H:i:s');
                    }
                    $where->greaterThanOrEqualTo('l.' . strtolower(str_replace('from', '', $value)), $properValue);
                    break;
                case 'lessThan' :
                    if (!in_array('datetime', $validate, true)) {
                        $properValue = $data[$value];
                    } else {
                        $date = \DateTime::createFromFormat('d/m/y H:i', $data[$value]);
                        $properValue = $date->format('Y-m-d H:i:s');
                    }
                    $where->lessThanOrEqualTo('l.' . strtolower(substr($value, 2)), $properValue);
                    break;
            }
        }
        return $where;
    }

    public static function validateWhereValue($data, $value, $validate = []): bool
    {
        if (isset($data[$value]) && strlen($data[$value])) {
            if (count($validate)) {
                $isValid = true;
                foreach ($validate as $validator) {
                    switch ($validator) {
                        case 'int':
                            $isValid = ((int)$data[$value] || $data[$value] === '0');
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

    /**
     * @param $productId
     * @param $storeId
     * @param string $msg
     * @param string $error
     * @param string $description
     * @return Logger
     */
    public function addPingRequestLog($productId, $storeId, $msg = '', $error = '', $description = ''): Logger
    {

        $action = array_search('ping', array_keys(self::$actions), true);
        $data = ['action' => $action,
            'message' => $msg,
            'error' => $error,
            'store_id' => $storeId,
            'description' => $description,
            'product_id' => $productId];
        return $this->add($data);
    }

    /**
     * @param $data
     * @return $this
     */
    public function add($data): self
    {
        $data = $this->processData($data);
        $data['created'] = new Expr('NOW()');
        $this->insert($data);

        return $this;
    }

    public function processData($data)
    {
        if (is_array($data) && count($data)) {
            foreach ($data as $key => $value) {
                if (isset(self::$fields[$key])) {
                    if (self::$fields[$key] === 'bit') {
                        $data[$key] = (int)$value ? 1 : 0;
                    } elseif (self::$fields[$key] === 'string' && strlen($value) > 255) {
                        $data[$key] = substr($value, 0, 255);
                    }
                } else {
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

    public function getActionListDropDown($filter): string
    {
        $string = '<select name="filter[l.action]" id="filter[l.action]" aria-controls="datatable-responsive" class="col-lg-12 form-control padd-top">';
        foreach ($filter['actionList'] as $k => $v) {
            $string .= '<option value="' . $k . '"' . ((int)$filter['l.action'] != -1 && (int)$filter['l.action'] === (int)$k ? ' selected="selected"' : '') . '>' . $v . '</option>';
        }
        $string .= '</select>';
        return $string;
    }

    public function getTypeDropdown($selected): string
    {
        $list = ['' => '', 'error' => 'error', 'message' => 'message'];
        return Dropdown::getHtml($list, $selected,
            [
                'name' => 'filter[type]',
                'aria-controls' => 'datatable-responsive',
                'class' => 'col-lg-12 form-control padd-top',
                'id' => 'filter-type',
            ], ['no-default-value' => 1]);
    }
}
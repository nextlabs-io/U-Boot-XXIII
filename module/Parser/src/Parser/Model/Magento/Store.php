<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 05.09.2018
 * Time: 19:21
 */

namespace Parser\Model\Magento;

use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class Store extends TableGateway
{
    public static $tableKey = 'parser_magento_id';
    public $lastInsertValue;
    public $table;
    public $adapter;
    public $data;
    public $config;
    public $descriptionData = [
        'title' => 'Title',
        'enable' => 'Enable',
        'magento_trigger_path' => 'Magento control url',
        'magento_trigger_key' => 'Magento control secret key',
        'delete_trigger' => 'Delete products if not Active in the Parser',
        'create_trigger' => 'Create product in Magento',
        'send_images' => 'Send images data',
        'check_description' => 'Check if description is empty (price and qty is always checked)',
    ];
    private $fields = [
        'title' => 'string',
        'enable' => 'bit',
        'magento_trigger_path' => 'string',
        'magento_trigger_key' => 'string',
        'delete_trigger' => 'bit',
        'create_trigger' => 'bit',
        'send_images' => 'bit',
        'check_description' => 'bit',
    ];

    public function __construct($db)
    {
        $table = 'parser_magento';
        parent::__construct($table, $db);
    }

    /**
     * @param $id - store id
     * @return $this
     */
    public function load($id)
    {
        $where = new Where();
        $where->equalTo('parser_magento_id', $id);
        $rowSet = $this->select($where);
        $this->data = $rowSet->current();
        return $this;
    }

    /**
     * @param array $selected
     * @return array - list of stores for html dropdown interface
     */
    public function getOptionsArray($selected = []): array
    {
        $this->columns = [self::$tableKey, 'title', 'enable'];
        $list = $this->getList();
        $data = [];
        if (count($list)) {
            foreach ($list as $store) {
                $data[$store[self::$tableKey]] = [
                    'title' => $store['title'],
                    'selected' => in_array($store[self::$tableKey], $selected) ? 1 : 0,
                ];
            }
        }
        return $data;
    }

    public function getDropDown($selected)
    {
        $magentoList = $this->getOptionsArray($selected);
        $magentoListDropDown = '<select name="filter[l.store_id]>" aria-controls="datatable-responsive" class="col-lg-12 form-control padd-top"><option value="-1"></option>';
        foreach ($magentoList as $storeId => $store) {
            $magentoListDropDown .= '<option value="' . $storeId . '"' . ($store['selected'] ? ' selected="selected"' : '') . '>' . $store['title'] . '</option>';
        }
        $magentoListDropDown .= '<select>';
        return $magentoListDropDown;
    }

    /**
     * @param array $conditions
     * @return array list of stores
     */

    public function getList($conditions = []): array
    {
        $where = new Where($conditions);
        $rowSet = $this->select($where);
        $data = [];
        while ($line = $rowSet->current()) {
            $data[] = (array)$line;
            $rowSet->next();
        }
        return $data;
    }

    public function processData($data)
    {
        if(is_array($data) && count($data)) {
            foreach ($data as $key => $value) {
                if (isset($this->fields[$key])) {
                    if ($this->fields[$key] === 'bit') {
                        $data[$key] = (bool)$value;
                    }
                } else {
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

}
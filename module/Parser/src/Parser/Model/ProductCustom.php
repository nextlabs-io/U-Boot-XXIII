<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 08.10.2018
 * Time: 15:47
 * class ProductCustom implements custom defined data for the product. This data can be used for connectors and later
 *
 * How to add new attribute:
 * 1. add custom_[attribute] and custom_[attribute]_flag to `product_custom` table.
 * [attribute] must exist in the `product` table
 * 2. change $flags array, add [attribute]_flag to it with proper description - type and title.
 * if type is not text or textarea, it needs to be handled in the ajax/product_custom.phtml
 * 3. add new attributes to $tableColumns array
 * if attribute requires additional validation, you need to update processData() function
 */

namespace Parser\Model;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;

class ProductCustom extends TableGateway
{
    public static $tableKey = 'product_custom_id';
    public $data;
    public $config;
    public $flags = [
        'custom_title_flag' => ['type' => 'text', 'title' => 'Title'],
        'custom_price_flag' => ['type' => 'text', 'title' => 'Price'],
        'custom_description_flag' => ['type' => 'textarea', 'title' => 'Description'],
        'custom_short_description_flag' => ['type' => 'textarea', 'title' => 'Short Description'],
        'custom_category_flag' => ['type' => 'text', 'title' => 'Category'],
        'custom_images_flag' => ['type' => 'textarea', 'title' => 'Images'],
    ];
    public $tableColumns = [
        'product_id',
        'custom_short_description_flag',
        'custom_short_description',
        'custom_description_flag',
        'custom_description',
        'custom_title_flag',
        'custom_title',
        'custom_price_flag',
        'custom_price',
        'custom_images_flag',
        'custom_images',
        'custom_category_flag',
        'custom_category',
    ];

    public function __construct($db)
    {
        $table = 'product_custom';
        parent::__construct($table, $db);
    }

    /**
     * @param $id
     * @return array
     */
    public function getFlaggedAttributes($id): array
    {
        $this->load($id);
        if ($this->data) {
            $flagged = [];
            $customData = $this->data;
            foreach ($this->flags as $flag => $flagData) {
                if (isset($this->data[$flag]) && $this->data[$flag]) {
                    // this field should be replaced
                    $attribute = str_replace(['custom_', '_flag'], '', $flag);
                    $flagged[$attribute] = $customData['custom_' . $attribute];
                }
            }
            $flagged['custom_images_send'] = $customData['custom_images_send'];
            return $flagged;
        }
        return [];

    }

    /**
     * @param $id - product ID
     * @return $this
     */
    public function load($id)
    {
        $where = new Where();
        $where->equalTo('product_id', $id);
        $rowSet = $this->select($where);
        $this->data = $rowSet->current();
        return $this;
    }

    /**
     * @param      $id
     * @param null $flag
     */
    public function resetImagesSendFlag($id, $flag = null)
    {
        $this->update(['custom_images_send' => (int)$flag ? 1 : 0], ['product_id' => $id]);
    }

    public function massUpdate($data, $where)
    {
        // $where is related to the product table, we need first extract all product ids from it.
        $sql = new Sql($this->adapter);
        $select = $sql->select('product')->columns(['product_id'])->where($where);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        while ($item = $result->current()) {
            $id = $item['product_id'];
            $custom = new ProductCustom($this->adapter);
            $custom->load($id);
            $dataToSave = $custom->processData($data);
            if ($custom->data) {
                $custom->update($dataToSave, ['product_id' => $id]);
            } else {
                $dataToSave['product_id'] = $id;
                $custom->insert($dataToSave);
            }
            $result->next();
        }
    }

    public function processData($data)
    {
        //$data = array_filter($data);
        $flags = $this->flags;
        $columns = $this->tableColumns;

        // remove columns which are not in the database
        foreach ($data as $key => $value) {
            if (!in_array($key, $columns, true)) {
                unset($data[$key]);
            } elseif (strpos('_flag', $key)) {
                // check if a bit
                $data[$key] = (int)$value ? 1 : 0;
            }
        }

        foreach ($flags as $flag => $flagData) {
            if (!isset($data[$flag])) {
                $data[$flag] = 0;
            }
        }
        if (isset($data['custom_price']) && !$data['custom_price']) {
            // the price is a float, it should not be empty string, otherwise it might raise mysql exception
            $data['custom_price'] = null;
        } elseif (isset($data['custom_price'])) {
            $data['custom_price'] = (float)$data['custom_price'];
        }
        if (isset($data['custom_images']) && $data['custom_images']) {
            $images = explode("\n", $data['custom_images']);
            $images = array_map('trim', $images);
            $images = array_filter($images);
            $data['custom_images'] = '';
            if (count($images)) {
                $data['custom_images'] = implode('|', $images);
            }
        }
        // always set custom_images_send flag in order to update images on product change.
        $data['custom_images_send'] = 1;
        return $data;
    }

}
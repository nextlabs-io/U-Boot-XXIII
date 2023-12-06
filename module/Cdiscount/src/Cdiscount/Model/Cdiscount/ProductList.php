<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.07.2020
 * Time: 23:47
 */

namespace Cdiscount\Model\Cdiscount;

use Parser\Model\Html\HtmlList;
use Parser\Model\Html\Tag;

class ProductList extends HtmlList
{
    public $fields = [
        'checkbox' => [
            'field' => 'cdiscount_product_id',
            'title' => '<input type="checkbox" name="filter[checkAll]" value="1" class="product-checkbox-selector-all" id="select-visible"/>',
            'options' => ['width' => '3%', 'id' => 'row_cdiscount_product_id', 'data-row' => 'l.cdiscount_product_id'],
            'item_options' => ['align' => 'center'],

        ],
        'actions' => [
            'field' => 'actions',
            'title' => 'Actions',
            'options' => [],
            'item_options' => ['align' => 'center'],
        ],

        'category' => [
            'field' => 'cdiscount_category_id',
            'title' => 'Category',
            'options' => ['id' => 'row_category', 'data-row' => 'category'],
            'item_options' => ['align' => 'center'],

        ],

        'url' => [
            'field' => 'url',
            'title' => '',
            'options' => ['width' => '40%', 'id' => 'row_url', 'data-row' => 'url'],
            'item_options' => ['align' => 'center'],

        ],
        'descritpion' => [
            'field' => 'description',
            'title' => 'Data',
            'options' => ['align' => 'center'],
            'item_options' => ['align' => 'center'],
        ],
        'price' => [
            'field' => 'price',
            'title' => 'Price',
            'options' => ['align' => 'center'],
            'item_options' => ['align' => 'center'],
        ],
        'stock' => [
            'field' => 'stock',
            'title' => 'Stock',
            'options' => ['align' => 'center'],
            'item_options' => ['align' => 'center'],
        ],
        'status' => [
            'field' => 'status',
            'title' => 'Status',
            'options' => [],
            'item_options' => ['align' => 'center'],

        ],
        'created' => [
            'field' => 'created',
            'title' => 'Created; Updated',
            'options' => ['id' => 'row_created', 'data-row' => 'l.created'],
            'item_options' => ['align' => 'center'],

        ],

        'checkbox-del' => [
            'field' => 'cdiscount_product_id_del',
            'title' => '<input type="submit" class="btn btn-default" value="Reset" name="resetFilter" /><br /><input type="submit" class="btn btn-default" value="Filter" name="filter-button" />',
            'options' => ['width' => '3%', 'id' => 'row_cdiscount_product_id', 'data-row' => 'l.cdiscount_product_id'],
            'item_options' => ['align' => 'center'],

        ],

    ];

    public $filterFields = [

    ];

    public $orderFields = [];

    public function getHtmlCdiscountProductIdDel($item, $fieldId)
    {
        if (!isset($this->inputs['delete-input'])) {
            $this->inputs['delete-input'] = '<input type="hidden" name="category_to_delete" id="category_to_delete" value=""/>';
        }
        // adding script which handles onclick event
        if (!isset($this->scripts['delete-script'])) {
            $this->scripts['delete-script'] = '
    $(\'.delete-button\').click(function (event) {
        var categoryId =  $(this).attr(\'data-value\');
        $(\'#category_to_delete\').val(categoryId);
        $(\'#category-form\').submit();
    });';
        }

        $string = Tag::html('', 'input', ['value' => 'Delete', 'type' => 'button', 'class' => 'btn btn-default delete-button', 'data-value' => $item['cdiscount_product_id']], true);


        return $string;

    }

    public function getHtmlCdiscountProductId($item, $fieldId)
    {
        if (!isset($this->scripts['check-all-script'])) {
            $this->scripts['check-all-script'] = '
            $(\'#select-visible\').click(function (event) {
                console.log($(\'#select-visible\').is(\':checked\'));
                    $(\'.product-checkbox-selector\').each(function () {
                        if($(\'#select-visible\').is(\':checked\')){
                            var checked = \'checked\';
                        } else {
                            var checked = \'\';
                        }
                        this.checked = checked;
                    });
            });';
        }
        $string = '<input type="checkbox" name="filter[' . $fieldId . '][' . $item[$fieldId] . ']" value="1" class="product-checkbox-selector" id="item' . $item[$fieldId] . '"/> &nbsp;';

        return $string;

    }

    public function getHtmlUrl($item, $fieldId)
    {
        $options = ['href' => $item[$fieldId], 'target' => 'blank'];
        $content = $item['title'] ?? $fieldId;
        $content = strip_tags($content);
        $content = Tag::html($content, 'a', $options);
        if ($item['amazonUrl'] ?? null) {
            $amazonOptions = ['href' => $item['amazonUrl'], 'target' => 'blank'];
            $content .= '<br>' . Tag::html('amazon page', 'a', $amazonOptions);
        }
        return $content;
    }

    public function getHtmlCdiscountCategoryId($item, $fieldId)
    {
        $content = Tag::html('id:' . $item[$fieldId], 'span', []);
        if ($item['categoryTitle'] ?? null) {
            $content .= Tag::html(' '.$item['categoryTitle'], 'strong', []);
        }
        return $content;
    }

    public function getHtmlPrice($item, $fieldId)
    {
        $string = Tag::html($item[$fieldId], 'span');
        if ($item['amazonPrice'] ?? null) {
            $string .= '<br>' . Tag::html('amzn:' . $item['amazonPrice'], 'span');
        }
        return $string;
    }

    public function getHtmlStock($item, $fieldId)
    {
        $string = Tag::html($item[$fieldId], 'span');
        if ($item['amazonStock'] ?? null) {
            $string .= '<br>' . Tag::html('amzn:' . $item['amazonStock'], 'span');
        }
        return $string;
    }

    public function getHtmlCreated($item, $fieldId)
    {
        return $this->timeAgoDate($item, $fieldId) . ';' . $this->timeAgoDate($item, 'updated');
    }

    public function getHtmlStatus($item, $fieldId)
    {
        $list = [
            Category::STATUS_NEVER_CHECKED => 'not checked',
            Category::STATUS_SUCCESS => '<span class="border-green">finished</span>',
            Category::STATUS_IN_PROGRESS => '<span class="blue">in progress</span>',
            Category::STATUS_UNKNOWN_ERROR => 'unknown error',
            Category::STATUS_FAILED => 'failed to process',
            Category::STATUS_NOT_FOUND => 'not found',
            Category::STATUS_FAILED_TO_EXTRACT_FIELDS => 'failed to extract fields',
            Category::STATUS_CURRENTLY_IN_PROGRESS => '<span class="red">working now...</span>',
        ];
        $string = $list[$item[$fieldId]];
        if ($item['log'] ?? null) {
            $string .= '<br>' . $item['log'];
        }
        return $string;
    }

    public function getHtmlActions($item, $fieldId)
    {
        // adding input hidden which will carry category id to delete, looks nasty.
//        if(!isset($this->inputs['delete-input'])){
//            $this->inputs['delete-input'] = '<input type="hidden" name="category_to_delete" id="category_to_delete" value=""/>';
//        }
//        // adding script which handles onclick event
//        if(!isset($this->scripts['delete-script'])){
//            $this->scripts['delete-script'] = '
//    $(\'.delete-button\').click(function (event) {
//        var categoryId =  $(this).attr(\'data-value\');
//        $(\'#category_to_delete\').val(categoryId);
//        $(\'#category-form\').submit();
//    });';
//        }
        if (!isset($this->inputs['refresh-input'])) {
            $this->inputs['refresh-input'] = '<input type="hidden" name="category_to_refresh" id="category_to_refresh" value=""/>';
        }
        if (!isset($this->scripts['refresh-script'])) {
            $this->scripts['refresh-script'] = '
    $(\'.refresh-button\').click(function (event) {
        var cdiscount_product_id =  $(this).attr(\'data-value\');
        $(\'#category_to_refresh\').val(cdiscount_product_id);
        $(\'#category-form\').submit();
    });';
        }

        $string = Tag::html('', 'input', ['value' => 'Refresh', 'type' => 'button', 'class' => 'btn btn-default refresh-button', 'data-value' => $item['cdiscount_product_id']]);
        $route ='cdiscount';
        $params = ['action' => 'sync'];
        $options = ['query' => 'id='.$item['cdiscount_product_id']];

        $url = $this->controller->generateRouteUrl($route, $params, $options);
        $string .= Tag::html('Sync', 'a', ['href' => $url, 'class' => 'btn btn-default delete-button', 'target' => '_blank'] );


        return $string;

    }

    public function getHtmlDescription($item, $fieldId)
    {
        $string = '';

        $amazonCheck = $item['amazon_check'];
        $amazonColor = $amazonCheck ? 'fa fa-check-square' : 'red info';

        $keepaCheck = $item['keepa_check'];
        $keepaColor = $keepaCheck ? 'green info' : 'red info';


        $route ='cdiscount';
        $params = ['action' => 'scrapeAmazon'];
        $options = ['query' => 'id='.$item['cdiscount_product_id']];

        $url = $this->controller->generateRouteUrl($route, $params, $options);

        $amazonContent = Tag::html(' amazon', 'a', ['href' => $url, 'target' => '_blank'] );
        $string .= Tag::html($amazonContent, 'span', ['class' => $amazonColor]);
        $string .= '&nbsp;'.Tag::html('keepa', 'span', ['class' => $keepaColor]);


        if ($item['ean'] ?? null) {
// todo add a link to amazon with search by ean
            $string .= Tag::html(' ean:' . $item['ean'] . ' ', 'span', ['class' => 'green info']);
        }
        if ($item['asin'] ?? null) {
// todo add a link to product on amazon
////            $product = new \Parser\Model\Product($this->globalConfig)
//            $href = ''
            $string .= Tag::html(' asin:' . $item['asin'] . ' ', 'span', ['class' => 'green info']);
        }
        return $string;
    }

    public function getHtmlProfile($item, $fieldId)
    {
        $content = $item[$fieldId];
        return $content;
    }
}


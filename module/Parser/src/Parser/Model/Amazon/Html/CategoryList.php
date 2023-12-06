<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.07.2020
 * Time: 23:47
 */

namespace Parser\Model\Amazon\Html;

use Parser\Model\Html\HtmlList;
use Parser\Model\Html\Tag;
use Parser\Model\Amazon\Category;

class CategoryList extends HtmlList
{
    public $fields = [
        'checkbox' => [
            'field' => 'amazon_category_id',
            'title' => '<input type="checkbox" name="filter[checkAll]" value="1" class="product-checkbox-selector-all" id="select-visible"/>',
            'options' => ['width' => '3%', 'id' => 'row_category_best_buy_id', 'data-row' => 'l.amazon_category_id'],
            'item_options' => ['align' => 'center'],

        ],
        'actions' => [
            'field' => 'actions',
            'title' => 'Actions',
            'options' => [],
            'item_options' => ['align' => 'center'],
        ],

        'profile' => [
            'field' => 'profile',
            'title' => 'Profile',
            'options' => ['width' => '10%', 'id' => 'row_profile', 'data-row' => 'profile'],
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
            'field' => 'amazon_category_id_del',
            'title' => '<input type="submit" class="btn btn-default" value="Reset" name="resetFilter" /><br /><input type="submit" class="btn btn-default" value="Filter" name="filter-button" />',
            'options' => ['width' => '3%', 'id' => 'row_category_best_buy_id', 'data-row' => 'l.amazon_category_id'],
            'item_options' => ['align' => 'center'],

        ],

    ];

    public $filterFields = [

    ];

    public $orderFields = [];

    public function getHtmlAmazonCategoryIdDel($item, $fieldId)
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

        $string = Tag::html('', 'input', ['value' => 'Delete', 'type' => 'button', 'class' => 'btn btn-default delete-button', 'data-value' => $item['amazon_category_id']], true);


        return $string;

    }

    public function getHtmlAmazonCategoryId($item, $fieldId)
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

        if($item['web_hierarchy_location_codes'] ?? null) {
            $content .= '<br>Location Codes ' . $item['web_hierarchy_location_codes'];
        }
        if($item['marketplace_category'] ?? null) {
            $content .= '<br>Marketplace Cat ' . $item['marketplace_category'];
        }
        return $content;
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
        var amazon_category_id =  $(this).attr(\'data-value\');
        $(\'#category_to_refresh\').val(amazon_category_id);
        $(\'#category-form\').submit();
    });';
        }
//        $string = Tag::html('', 'input', ['value' => 'Delete', 'type' => 'button', 'class' => 'btn btn-default delete-button', 'data-value' => $item['amazon_category_id']], true);

        $string = Tag::html('', 'input', ['value' => 'Refresh', 'type' => 'button', 'class' => 'btn btn-default refresh-button', 'data-value' => $item['amazon_category_id']]);
        return $string;

    }

    public function getHtmlDescription($item, $fieldId)
    {
        $found = $item['found'];
        $string = '';
        if ($item['totalPages'] ?? null) {
            $string .= ' Total pages:' . $item['totalPages'] . ';';
        }

        if ($item['checked'] ?? null) {
            $string .= ' page:' . $item['checked'] . ';';
        }
        if ($found) {
            $string .= ' found products: ' . $found . ';';
        }
        if ($item['product_qty'] !== null) {
            $string .= ' unique asins:' . $item['product_qty'];
        }
//        $string .= Tag::html('; scraped: '. $scrapedQty, 'span', ['class' => 'blue']);
//        $string .= Tag::html('; asins: '. $asinQty, 'span', ['class' => 'red']);
        return $string;
    }

    public function getHtmlProfile($item, $fieldId)
    {
        $content = $item[$fieldId];
        return $content;
    }
}
<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.07.2020
 * Time: 23:47
 */

namespace BestBuy\Model\BestBuy;

use Parser\Model\Html\HtmlList;
use Parser\Model\Html\Tag;

class CategoryList extends HtmlList
{
    public $fields = [
        'checkbox' => [
            'field' => 'category_best_buy_id',
            'title' => '<input type="checkbox" name="filter[checkAll]" value="1" class="product-checkbox-selector-all" id="select-visible"/>',
            'options' => ['width' => '3%', 'id' => 'row_category_best_buy_id', 'data-row' => 'l.category_best_buy_id'],
            'item_options' => ['align' => 'center'],

        ],
        'bb_category' => [
            'field' => 'bb_category',
            'title' => 'BB_Category',
            'options' => ['width' => '10%', 'id' => 'row_bb_category', 'data-row' => 'l.bb_category'],
            'item_options' => ['align' => 'center'],

        ],
        'url' => [
            'field' => 'url',
            'title' => 'Url',
            'options' => ['width' => '15%', 'id' => 'row_url', 'data-row' => 'url'],
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
            'title' => 'Created',
            'options' => ['id' => 'row_created', 'data-row' => 'l.created'],
            'item_options' => ['align' => 'center'],

        ],
        'actions' => [
            'field' => 'actions',
            'title' => '<input type="submit" class="btn btn-default" value="Reset" name="resetFilter" /><br /><input type="submit" class="btn btn-default" value="Filter" name="filter-button" /> ',
            'options' => [],
            'item_options' => ['align' => 'center'],
        ],
    ];

    public $filterFields = [
        'bb_category' => '',
    ];

    public $orderFields = [];

    public function getHtmlCategoryBestBuyId($item, $fieldId)
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
        return '<input type="checkbox" name="filter[' . $fieldId . '][' . $item[$fieldId] . ']" value="1" class="product-checkbox-selector" id="item' . $item[$fieldId] . '"/>';
    }

    public function getHtmlUrl($item, $fieldId)
    {
        $options = ['href' => $item[$fieldId], 'target' => 'blank'];
        $content = $item['title'] ?? $fieldId;
        return Tag::html($content, 'a', $options);
    }

    public function getHtmlCreated($item, $fieldId)
    {
        return $this->timeAgoDate($item, $fieldId);
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
        return $list[$item[$fieldId]];
    }

    public function getHtmlActions($item, $fieldId){
        // adding input hidden which will carry category id to delete, looks nasty.
        if(!isset($this->inputs['delete-input'])){
            $this->inputs['delete-input'] = '<input type="hidden" name="category_to_delete" id="category_to_delete" value=""/>';
        }
        if(!isset($this->inputs['refresh-input'])){
            $this->inputs['refresh-input'] = '<input type="hidden" name="category_to_refresh" id="category_to_refresh" value=""/>';
        }
        // adding script which handles onclick event
        if(!isset($this->scripts['delete-script'])){
            $this->scripts['delete-script'] = '
    $(\'.delete-button\').click(function (event) {
        var categoryId =  $(this).attr(\'data-value\');
        $(\'#category_to_delete\').val(categoryId);
        $(\'#category-form\').submit();
    });';
        }
        if(!isset($this->scripts['refresh-script'])){
            $this->scripts['refresh-script'] = '
    $(\'.refresh-button\').click(function (event) {
        var categoryId =  $(this).attr(\'data-value\');
        $(\'#category_to_refresh\').val(categoryId);
        $(\'#category-form\').submit();
    });';
        }

        $string = Tag::html('', 'input', ['value' => 'Refresh', 'type' => 'button', 'class' => 'btn btn-default refresh-button', 'data-value' => $item['category_best_buy_id']]);
        $string .= '<br />' . Tag::html('', 'input', ['value' => 'Del', 'type' => 'button', 'class' => 'btn btn-default delete-button', 'data-value' => $item['category_best_buy_id']], true);
        return $string;

    }

    public function getHtmlDescription($item, $fieldId){
        $completedList = [
            Category::STATUS_SUCCESS,
            Category::STATUS_UNKNOWN_ERROR,
            Category::STATUS_FAILED,
            Category::STATUS_NOT_FOUND,
            Category::STATUS_FAILED_TO_EXTRACT_FIELDS,
        ];
        $completedStatus = in_array($item['status'], $completedList) ? 1 : 0;
        $currentPage = $item['page'];
        $lastPage = $item['last_page'];
        $asinQty = $item['asin_qty'];
        $total = $item['product_qty'];

        $amazonCheck = $item['amazon_qty'];
        $amazonColor = ($completedStatus && $amazonCheck == $total) ? 'green' : 'red' ;

        $keepaCheck = $item['keepa_qty'];
        $keepaColor = ($completedStatus && ($keepaCheck == $total)) ? 'green' : 'red' ;

        $scrapedQty = $item['scraped_best_buy_qty'];
        $scrapedColor = ($completedStatus && ($scrapedQty == $total)) ? 'green' : 'red' ;



        $string = 'totalPages: ' . $lastPage. '; cur Page:'. $currentPage.'; found products: '. $item['product_qty'];
        $string .= Tag::html('; asins: '. $asinQty, 'span', ['class' => 'blue']);

        $string .= Tag::html('; scraped: '. $scrapedQty, 'span', ['class' => $scrapedColor]);
        $string .= Tag::html('; checked amzn: '. $amazonCheck, 'span', ['class' => $amazonColor]);
        $string .= Tag::html('; checked keepa: '. $keepaCheck, 'span', ['class' => $keepaColor]);
        return $string;
    }
}
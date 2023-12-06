<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 22.07.2020
 * Time: 19:47
 */

namespace BestBuy\Model\BestBuy;


use Parser\Model\Html\HtmlList;

class ProductList extends HtmlList
{
    public $fields = [
        'checkbox' => [
            'field' => 'product_best_buy_id',
            'title' => 'Id',
            'options' => ['width' => '3%', 'id' => 'row_category_best_buy_id', 'data-row' => 'l.category_best_buy_id'],
            'item_options' => ['align' => 'center'],

        ],
        'bb_category' => [
            'field' => 'bb_category',
            'title' => 'BB_Category',
            'options' => ['width' => '10%', 'id' => 'row_bb_category', 'data-row' => 'l.bb_category'],
            'item_options' => ['align' => 'center'],

        ],
        'bb_product' => [
            'field' => 'bb_product',
            'title' => 'BB_Product',
            'options' => ['width' => '10%', 'id' => 'row_bb_product', 'data-row' => 'l.bb_category'],
            'item_options' => ['align' => 'center'],
        ],
        'url' => [
            'field' => 'url',
            'title' => 'Url',
            'options' => ['width' => '15%', 'id' => 'row_url', 'data-row' => 'url'],
            'item_options' => ['align' => 'center'],

        ],
        'title' => [
            'field' => 'title',
            'title' => 'Title',
            'options' => ['align' => 'center'],
            'item_options' => ['align' => 'center'],
        ],
        'asin' => [
            'field' => 'asin',
            'title' => 'Asin',
            'options' => ['width' => '10%', 'id' => 'row_bb_asin', 'data-row' => 'l.asin'],
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
            'title' => 'Actions',
            'options' => [],
            'item_options' => ['align' => 'center'],
        ],
    ];

    public $filterFields = [
        'bb_category' => '',
    ];

    public $orderFields = [];
}
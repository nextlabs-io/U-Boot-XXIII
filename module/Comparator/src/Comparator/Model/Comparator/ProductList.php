<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 20.07.2020
 * Time: 23:47
 */

namespace Comparator\Model\Comparator;

use Parser\Model\Amazon\Html\Helper as HtmlCategoryHelper;
use Parser\Model\Html\HtmlList;
use Parser\Model\Html\Paging;
use Parser\Model\Html\Tag;

class ProductList extends HtmlList
{
    public $fields = [
        'checkbox' => [
            'field' => 'comparator_product_id',
            'title' => '<input type="checkbox" name="filter[checkAll]" value="1" class="product-checkbox-selector-all" id="select-visible"/>',
            'options' => ['width' => '3%', 'id' => 'row_comparator_product_id', 'data-row' => 'l.comparator_product_id'],
            'item_options' => ['align' => 'center'],

        ],
        'actions' => [
            'field' => 'actions',
            'title' => 'Actions',
            'options' => [],
            'item_options' => ['align' => 'center'],
        ],

        'url' => [
            'field' => 'url',
            'title' => '',
            'options' => ['width' => '30%', 'id' => 'row_url', 'data-row' => 'url'],
            'item_options' => ['align' => 'left'],

        ],
        'descritpion' => [
            'field' => 'description',
            'title' => 'Data',
            'options' => ['width' => '30%', 'id' => 'row_data', 'data-row' => 'data'],
            'item_options' => ['align' => 'left'],
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
            'field' => 'comparator_product_id_del',
            'title' => '<input type="submit" class="btn btn-default" value="Reset" name="resetFilter" /><br /><input type="submit" class="btn btn-default" value="Filter" name="filter-button" />',
            'options' => ['width' => '3%', 'id' => 'row_comparator_product_id', 'data-row' => 'l.comparator_product_id'],
            'item_options' => ['align' => 'center'],

        ],

    ];

    public $filterFields = [

    ];

    public $orderFields = [];
    /**
     * run self::getTablePage() to initialize
     * @var \Laminas\View\Model\ViewModel
     */
    public $pagingView;
    /**
     * run self::getTablePage() to initialize
     * @var string
     */
    public $perPageSelect;

    public function getHtmlComparatorProductId($item, $fieldId)
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
        $route = 'comparator';
        $params = ['action' => 'showitem'];


        $options = ['query' => 'id=' . $item['comparator_product_id']];

        $url = $this->controller->generateRouteUrl($route, $params, $options);

        $options = ['href' => $url, 'target' => 'blank'];
        $content = $item['title'] ?? 'no title';
        $content = strip_tags($content);
        $content = Tag::html($content, 'a', $options);

        $content .= '<br> <strong> DropshipProvider:'. $item['data_source'].'</strong>';

        $content .= '<br>';
        $imagesAttributes = ['image', 'keepa_image', 'amazonImage'];
        foreach($imagesAttributes as $imAtt) {
            if ($item[$imAtt] ?? null) {
                $imageSrc = $this->getFirstImage($item[$imAtt]);
                $imageOptions = ['height' => '50px', 'src' => $imageSrc, 'loading'=>"lazy", 'alt' => $imAtt];
                $content .= Tag::html($imAtt, 'img', $imageOptions, 1).'&nbsp;';
            }
        }

        return $content;
    }

    private function getFirstImage($image)
    {
        if ($image) {
            $list = explode('|', $image);
            return $list[0];
        }
        return null;
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
            Product::STATUS_NEVER_CHECKED => 'not checked',
            Product::STATUS_SUCCESS => '<span class="border-green">finished</span>',
            Product::STATUS_IN_PROGRESS => '<span class="blue">in progress</span>',
            Product::STATUS_UNKNOWN_ERROR => 'unknown error',
            Product::STATUS_FAILED => 'failed to process',
            Product::STATUS_NOT_FOUND => 'not found',
            Product::STATUS_FAILED_TO_EXTRACT_FIELDS => 'failed to extract fields',
            Product::STATUS_CURRENTLY_IN_PROGRESS => '<span class="red">working now...</span>',
        ];
        $string = $list[$item[$fieldId]];
        if ($item['log'] ?? null) {
            $string .= '<br>' . $item['log'];
        }
        return $string;
    }

    public function getHtmlActions($item, $fieldId)
    {
        if (!isset($this->inputs['refresh-input'])) {
            $this->inputs['refresh-input'] = '<input type="hidden" name="product_to_refresh" id="product_to_refresh" value=""/>';
        }
        if (!isset($this->scripts['refresh-script'])) {
            $this->scripts['refresh-script'] = '
    $(\'.refresh-button\').click(function (event) {
        var comparator_product_id =  $(this).attr(\'data-value\');
        $(\'#product_to_refresh\').val(comparator_product_id);
        $(\'#category-form\').submit();
    });';
        }

        $string = Tag::html('', 'input', ['value' => 'Refresh', 'type' => 'button', 'class' => 'btn btn-default refresh-button', 'data-value' => $item['comparator_product_id']]);
//        $route ='comparator';
//        $params = ['action' => 'sync'];
//        $options = ['query' => 'id='.$item['comparator_product_id']];
//
//        $url = $this->controller->generateRouteUrl($route, $params, $options);
//        $string .= Tag::html('Sync', 'a', ['href' => $url, 'class' => 'btn btn-default delete-button', 'target' => '_blank'] );


        return $string;

    }

    public function getHtmlDescription($item, $fieldId)
    {
        $string = '';

        $amazonCheck = $item['amazon_check'];
        $amazonColor = $amazonCheck ? 'fa fa-check-square' : 'red info';

        $keepaCheck = $item['keepa_check'];
        $keepaColor = $keepaCheck ? 'fa fa-check-square' : 'red info';


        $route = 'comparator';
        $params = ['action' => 'scrapeAmazon'];

        $paramsKeepa = ['action' => 'scrapeKeepa'];
        $options = ['query' => 'id=' . $item['comparator_product_id']];

        $url = $this->controller->generateRouteUrl($route, $params, $options);
        $urlKeepa = $this->controller->generateRouteUrl($route, $paramsKeepa, $options);

        $amazonContent = Tag::html(' amazon', 'a', ['href' => $url, 'target' => '_blank']);
        $string .= Tag::html($amazonContent, 'span', ['class' => $amazonColor]);
        $keepaContent = Tag::html(' keepa', 'a', ['href' => $urlKeepa, 'target' => '_blank']);
        $string .= '&nbsp;' . Tag::html($keepaContent, 'span', ['class' => $keepaColor]);


        $attributesToDisplay = ['locale', 'ean', 'upc', 'asin', 'brand', 'model', 'minimum_qty', 'amazonPrime', 'amazonShipping'];
        $string .= '<br>';
        foreach ($attributesToDisplay as $att) {
            if ($item[$att] ?? null) {
                $string .= '&nbsp;' . Tag::html($att . ':' . $item[$att] . ' ', 'span', ['class' => 'green info']);
            }
        }

        if ($item['amazonUrl'] ?? null) {
            $amazonOptions = ['href' => $item['amazonUrl'], 'target' => 'blank'];
            $string .= '<br>' . Tag::html(Tag::html('goto amazon page', 'a', $amazonOptions), 'strong', ['style' => 'color=black']);
        }
        return $string;
    }

    public function getHtmlProfile($item, $fieldId)
    {
        $content = $item[$fieldId];
        return $content;
    }

    public function getPageData(array $filter, Product $product)
    {
        $perPage = $filter['per-page'] ?? 100;
        $page = $filter['page'] ?? 1;
        $productItems = $product->getProductList($filter);
//        pr($productItems);die();
        $paging = new Paging($filter['page'], $product->getTotalResults(), $perPage);
        $pagingView = $paging->getAsHTML();
        $perPageSelect = $paging->getPerPageSelectorDropdown($perPage);

        $this->filterFields = ['status' => '<br>' . $product->getStatusDropdown($filter['status']),
            'url' => $product->getUrlTableFilterFields($filter),
//            'category' => $product->getCategoryFilterField($filter),
            'descritpion' => $product->getDescriptionFilterField($filter),

        ];
        $table = $this->getTable($productItems, [
            'scripts' => HtmlCategoryHelper::getSimpleOnchangeSubmit('filter-status', 'category-form')
                . HtmlCategoryHelper::getSimpleOnchangeSubmit('per-page', 'category-form')
                . HtmlCategoryHelper::getPagingScript('page-input', 'category-form'),
            'inputs' => HtmlCategoryHelper::getPageInput('page-input', 'filter[page]', $page)
        ]);
        $this->pagingView = $pagingView;
        $this->perPageSelect = $perPageSelect;

        return $table;
    }
}


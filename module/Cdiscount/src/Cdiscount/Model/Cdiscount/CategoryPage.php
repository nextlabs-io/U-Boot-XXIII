<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 07.10.2020
 * Time: 12:10
 */

namespace Cdiscount\Model\Cdiscount;


use Parser\Model\Amazon\CategoryPage as AmazonCategoryPage;

class CategoryPage extends AmazonCategoryPage
{
    public $tableKey = 'cdiscount_category_page_id';
    public $categoryIdKey = 'cdiscount_category_id';

    public function __construct($adapter)
    {
        parent::__construct($adapter, 'cdiscount_category_page');
    }




}
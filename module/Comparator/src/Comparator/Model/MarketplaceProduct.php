<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 25.06.2020
 * Time: 20:15
 */

namespace Comparator\Model;

use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;


class MarketplaceProduct extends TableGateway
{
    public static $tableKey = 'marketplace_product_id';
    public $lastInsertValue;
    public $data;
    public $config;
    public $customDefinedFields = [
        'shop_sku',
        '_Title_BB_Category_Root_EN',
        '_Short_Description_BB_Category_Root_EN',
        '_Brand_Name_Category_Root_EN',
        '_Primary_UPC_Category_Root_EN',
        '_Model_Number_Category_Root_EN',
        '_Manufacturers_Part_Number_Category_Root_EN',
        '_Seller_Image_URL_Categor_Root_EN',
        '_Type_24735_CAT_30177_EN',
        '_ChargerType_680058_CAT_32083_EN',
        '_Web_Hierarchy_Location_Category_Root_EN',

    ];
    protected $fields = [
        'created',
        'asin',
        'locale',
        'BBYCat',
        'shop_sku',
        '_Title_BB_Category_Root_EN',
        '_Short_Description_BB_Category_Root_EN',
        '_Brand_Name_Category_Root_EN',
        '_Primary_UPC_Category_Root_EN',
        '_Model_Number_Category_Root_EN',
        '_Manufacturers_Part_Number_Category_Root_EN',
        '_Seller_Image_URL_Category_Root_EN',
        '_Type_24735_CAT_30177_EN',
        '_ChargerType_680058_CAT_32083_EN',
        '_Web_Hierarchy_Location_Category_Root_EN',
        'sku',
        'product-id',
        'product-id-type',
        'description',
        'internal-description',
        'price',
        'price-additional-info',
        'quantity',
        'min-quantity-alert',
        'state',
        'available-start-date',
        'available-end-date',
        'logistic-class',
        'discount-price',
        'discount-start-date',
        'discount-end-date',
        'update-delete',
        'manufacturer-warranty',
        'ehf-amount-ab',
        'ehf-amount-bc',
        'ehf-amount-mb',
        'ehf-amount-nb',
        'ehf-amount-nl',
        'ehf-amount-ns',
        'ehf-amount-nt',
        'ehf-amount-nu',
        'ehf-amount-on',
        'ehf-amount-pe',
        'ehf-amount-qc',
        'ehf-amount-sk',
        'ehf-amount-yt',
        'pim',

    ];
    protected $defaultValues = [
        'min-quantity-alert' => 0,
        'state' => 'New',
        'logistic-class' => null,
        'discount-price' => null,
        'discount-start-date' => null,
        'discount-end-date' => null,
        'update-delete' => 'Update',
        'manufacturer-warranty' => '45',
        'ehf-amount-ab' => 0.25,
        'ehf-amount-bc' => 0.25,
        'ehf-amount-mb' => 0.25,
        'ehf-amount-nb' => 0.25,
        'ehf-amount-nl' => 0.25,
        'ehf-amount-ns' => 0.25,
        'ehf-amount-nt' => 0.25,
        'ehf-amount-nu' => 0.25,
        'ehf-amount-on' => 0.25,
        'ehf-amount-pe' => 0.25,
        'ehf-amount-qc' => 0.25,
        'ehf-amount-sk' => 0.25,
        'ehf-amount-yt' => 0.25,
        'pim' => null,
    ];

    public function __construct($db)
    {
        $table = 'marketplace_product';
        parent::__construct($table, $db);
    }

    public static function getClassByAttribute($attribute)
    {
        $className = self::getClassName($attribute);
        $fullClassName = "Comparator\Model\Attributes\\" . $className;
        if (class_exists($fullClassName)) {
            return new $fullClassName();
        }
        return null;
    }

    /**
     * @param $attribute string
     * @return string
     */
    public static function getClassName($attribute): string
    {
        return str_replace('_', '', $attribute);
    }

    /**
     * @param $asins array
     * @param $locale string
     * @param $data array
     */
    public function add($asins, $locale, $data): void
    {
        foreach ($asins as $asin) {
            $ids = ['asin' => $asin, 'locale' => $locale];
            $data = $this->addDefaultValues($data);
            $this->insertOrUpdate($ids, $data);
        }
    }

    /**
     * add default values to the raw of data
     * @param $output
     * @return array
     */
    public function addDefaultValues($output): array
    {
        foreach ($this->fields as $field) {
            if (isset($this->defaultValues[$field]) && ! isset($output[$field])) {
                $output[$field] = $this->defaultValues[$field];
            }
        }
        return $output;
    }

    public function insertOrUpdate($ids, $data): MarketplaceProduct
    {
        $data = $this->filterData($data);
        $result = $this->select($ids);
        if (! $result->current()) {
            $data['created'] = new Expression('NOW()');
            $this->insert(array_merge($ids, $data));
        } else {
            $this->update($data, $ids);
        }
        return $this;
    }

    public function filterData($data): array
    {
        $output = [];
        foreach ($this->fields as $field) {
            if (isset($data[$field])) {
                $output[$field] = $data[$field];
            }
        }
        // remove ids values
        unset($output['asin'], $output['locale']);
        return $output;
    }

    public function getManageableFields()
    {
        // return fields which are editable via input form TODO
    }

    /**
     * @param $asin
     * @param $locale
     * @param $data
     */
    public function fillFromProductTable($asin, $locale, $data): void
    {
        // we got data from synchronization, and now should calculate missing fields.
        $toSave = [];
        if ($category = $data['category'] ?? '') {
            $toSave['BBYCat'] = $this->extractCategory($category);
        }
        $toSave['shop_sku'] = $toSave['sku'] = $toSave['product-id'] = $asin;

        // 126 characters title
        if ($title = $data['title'] ?? '') {
            $toSave['_Title_BB_Category_Root_EN'] = Helper::cutLiteralString($title, 126);
        }
        if ($shortDesc = $data['short_description'] ?? '') {
            $toSave['_Short_Description_BB_Category_Root_EN'] = Helper::cutLiteralString($shortDesc, 400);
        }
        if ($brand = $this->extractBrand($data)) {
            $toSave['_Brand_Name_Category_Root_EN'] = $brand;
        }
        if ($upc = $this->extractUPC($data)) {
            $toSave['_Primary_UPC_Category_Root_EN'] = $upc;
        }
        if ($model = $this->extractModel($data)) {
            $toSave['_Model_Number_Category_Root_EN'] = $model;
        }
        if ($partNum = $this->extractPartNum($data)) {
            $toSave['_Manufacturers_Part_Number_Category_Root_EN'] = $partNum;
        }
        if ($image = $this->extractImage($data)) {
            $toSave['_Seller_Image_URL_Category_Root_EN'] = $image;
        }
        // TODO figure out which to use SHOP_SKU, SKU, UPC-A
        $toSave['product-id-type'] = 'SHOP_SKU';
        if ($description = $data['description'] ?? '') {
            $toSave['description'] = Helper::cutLiteralString($description, 2000);
        }
        if ($price = $this->extractPrice($data)) {
            $toSave['price'] = $price;
        }
        if ($stock = $data['stock']) {
            $toSave['quantity'] = $stock;
        }
        $this->insertOrUpdate(['asin' => $asin, 'locale' => $locale], $toSave);
    }

    protected function extractCategory($category)
    {
        $list = explode('|', $category);
        $list = array_map('trim', $list);
        return implode('/', $list);
    }

    protected function extractBrand($data)
    {
        return $this->simpleExtract($data, ['brand', 'made_by', 'manufacturer'], 20);
    }

    public function simpleExtract(array $data, array $sequence, Int $limit = null)
    {
        foreach ($sequence as $fieldName) {
            $fieldVal = $data[$fieldName] ?? '';
            if ($fieldVal) {
                return $this->simpleLimiter($fieldVal, $limit);
            }
        }
        return '';
    }

    public function simpleLimiter($string, $limit)
    {
        if ($limit && strlen($string) > $limit) {
            $string = substr($string, 0, $limit);
        }
        return $string;
    }

    protected function extractUPC($data)
    {
        return $this->simpleExtract($data, ['upc', 'ean']);
    }

    protected function extractModel($data)
    {
        return $this->simpleExtract($data, ['model', 'mpn'], 20);
    }

    protected function extractPartNum($data)
    {
        return $this->simpleExtract($data, ['mpn', 'model'], 30);
    }

    protected function extractImage($data)
    {
        $images = $this->simpleExtract($data, ['images']);
        if ($images) {
            $list = explode("|", $images);
            return array_pop(array_reverse($list));
        }
        return '';
    }

    protected function extractPrice($data)
    {
        $price = $data['price'];
        if ($price) {
            return '$' . str_replace('.', ',', $price);
        }
        return '';
    }

    /**
     * @param String $asin
     * @param String $locale
     * @return array|\ArrayObject|null
     */
    public function load($asin, $locale)
    {
        $rowSet = $this->select(['asin' => $asin, 'locale' => $locale]);
        return $rowSet->current();
    }
}
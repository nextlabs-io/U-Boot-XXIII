<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 12.10.2020
 * Time: 14:48
 */

namespace BestBuy\Model\BestBuy;


use Parser\Model\DefaultTablePage;
use Parser\Model\Helper\Config;
use yii\db\Exception;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use function GuzzleHttp\Psr7\str;

class ProductKeepaData extends DefaultTablePage
{
    /**
     * @var ProductKeepa
     */
    public $keepa;
    public $globalConfig;
    public $tableKey = 'product_keepa_data_id';
    public $ignoreConfigFile = true;

    public function __construct(Config $config)
    {
        $this->globalConfig = $config;

        $this->keepa = new ProductKeepa($config);
        $table = 'product_keepa_data';
        parent::__construct('', $config, $table, $this->tableKey);
        array_push($this->fields, ...['locale', 'log', 'short_description', 'long_description'], ...$this->keepa->keepaColumns);
    }

    public function exportKeepaTable()
    {
        // get all products in the product_keepa, adjust fields and copy to product_keepa_data
        // delete all previously exported
        $this->delete([]);
        $qty = 0;
        $sql = new Sql($this->getAdapter());
        $select = $sql->select($this->keepa->getTable());
        $select->where(['status' => $this->keepa::STATUS_SUCCESS]);
        $select->columns(['qty' => new Expression('COUNT(*)')]);
        $qtyRes = $this->selectWith($select);
        if ($qtyRes->current()) {
            $qty = $qtyRes->current()['qty'];
        }
        if ($qty) {
            $perPage = 100;
            $totalPages = ((int)($qty / $perPage)) ?: 1;

            for ($page = 1; $page <= $totalPages; $page++) {
                $select = $sql->select($this->keepa->getTable());
                $select->where(['status' => $this->keepa::STATUS_SUCCESS]);
                $select->limit($perPage);
                if (($page - 1) * $perPage) {
                    $select->offset(($page - 1) * $perPage);
                }
                $res = $this->selectWith($select);
                while ($item = $res->current()) {
                    $this->checkAndInsertItem($item);
                    $res->next();
                }
            }
        }
        return $qty;
    }

    /**
     * @param array $item
     * REMOVE from +title , description , +features - asin, urls, tags
     * @throws Exception
     */
    private function checkAndInsertItem($item)
    {
        $data = $this->processData($item);
        $fixedData = [];
        foreach ($data as $field => $value) {
            $data[$field] = $value = trim(Helper::replace4byte($value));
            if (in_array($field, ['features', 'description', 'title'])) {

                $replacements = ['Amazon.ca', 'Amazon.com', 'Amazon.fr', 'Product Description', 'AmazonProductDescription'];
                if ($data['asin'] ?? null) {
                    $replacements[] = $data['asin'];
                }
                $value = str_replace($replacements, '', $value);
                $value = \Parser\Model\Helper\Helper::stripDomains($value);
                $value = trim(strip_tags($value));

            }
            if ($field === 'features' || $field === 'description') {
                $value = str_replace('&#8226;', '<br>&#8226;', $value);
            }
            $fixedData[$field] = $value;
        }
        $longDescArray = [];
        foreach (['title', 'description', 'features'] as $longDescField) {
            if ($fixedData[$longDescField] ?? null) {
                $value = $fixedData[$longDescField];
                if(strpos($value, '<br>') === 0){
                    $value = substr($value, 4, strlen($value));
                }
                $longDescArray[] = $value;
            }
        }
        $fixedData['long_description'] = $longDescArray ? implode('<br>', $longDescArray) : '';
        if($fixedData['features'] ?? null){
            $fixedData['long_description'] .= $fixedData['features'];
        }
        $fixedData['short_description'] = Helper::literalExtract($fixedData, ['features', 'title'], 400);
        $fixedData['description'] = Helper::literalExtract($fixedData, ['description', 'title'], 2000);
        $fixedData['title'] = Helper::cutLiteralString($fixedData['title'], 126);

        $fixedData['upc'] = Helper::directExtract($fixedData, ['upc', 'ean'], 30);
        $fixedData['model'] = Helper::directExtract($fixedData, ['model', 'asin'], 20);
        $fixedData['part_number'] = Helper::directExtract($fixedData, ['part_number', 'mpn', 'model', 'asin'], 30);
        $unique = ['asin' => $fixedData['asin'], 'locale' => $fixedData['locale']];
        $fixedData['content'] = null;
        try {
            $this->insertOrUpdate($unique, $fixedData);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage() . " \r\n " . print_r($fixedData, 1));
        }


    }
}
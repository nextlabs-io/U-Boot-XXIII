<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 01.05.2018
 * Time: 15:06
 */

namespace Parser\Model\Amazon;

use ApaiIO\ApaiIO;
use ApaiIO\Configuration\GenericConfiguration;
use ApaiIO\Operations\Lookup;
use GuzzleHttp\Exception\ClientException as GException;
use Parser\Model\SimpleObject;
use yii\db\Exception;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

/**
 * Class Product gets the data from Amazon by ASIN
 * @package Parser\Model\Amazon
 */
class Product extends SimpleObject
{
    /**
     * @var config holds amazon credentials: access key, secret key, associate tag, locale
     */
    public $config;
    /**
     * db is used to store temp loaded data related to products
     */
    public $db;

    public function __construct($config, $db)
    {
        $this->config = (object)$config['settings'];
        $this->db = $db;
    }


    /**
     * @deprecated
     * @param $locale
     * @return bool
     * @throws \Exception
     */
    public static function checkIfRunning($locale): bool
    {
        $fileName = 'data/parser/api_run.' . $locale;

        if (!file_exists($fileName)) {
            touch($fileName);
            return false;
        }
        $d = new \DateTime();
        $current = $d->getTimestamp();
        $delta = $current - filemtime($fileName);
        // if file is older than 15 minutes
        if ($delta > 60 * 15) {
            unlink($fileName);
            touch($fileName);
            return false;
        }
        return true;
    }

    /**
     * @param $list array of asins
     * @return bool
     * Function takes the unlimited list of asins and repetely gets the data from Amazon
     * The list is split into chunks of 10 products and stores the data into a temp sql panel.
     */
    public function getData($list)
    {
        /**
         * @var $list  array of asins
         */
        if (!is_array($list)) {
            $list = [$list];
        }

        /* delete items from amazon_product which were removed from product*/
        $query = 'SELECT ap.amazon_product_id FROM amazon_product AS ap LEFT JOIN product AS p USING(asin,locale) WHERE p.product_id IS NULL';
        $sql = new Sql($this->db);
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        $toDeleteList = [];
        while ($itemToD = $result->current()) {
            $toDeleteList[] = $itemToD['amazon_product_id'];
            $result->next();
        }
        if (count($toDeleteList)) {
            $where = new Where();
            $where->in('amazon_product_id', $toDeleteList);
            $delete = $sql->delete('amazon_product')->where($where);
            $stmt = $sql->prepareStatementForSqlObject($delete);
            $stmt->execute();
        }

        $conf = new GenericConfiguration();

        $client = new \GuzzleHttp\Client();
        $request = new \ApaiIO\Request\GuzzleRequest($client);
        // creating a connection to amazon

        try {
            $conf
                ->setCountry($this->config->amazon_locale)
                ->setAccessKey($this->config->amazon_key)
                ->setSecretKey($this->config->amazon_secret)
                ->setAssociateTag($this->config->amazon_tag)
                ->setRequest($request)
                ->setResponseTransformer(new \ApaiIO\ResponseTransformer\XmlToArray());
        } catch (\Exception $e) {
            $this->addError($e);
            return false;
        }
        //$respGroups = explode(",", "Small,Offers,Images,EditorialReview,BrowseNodes,Reviews,ItemAttributes,VariationMatrix");
        $chunks = array_chunk($list, 10);
        $itemsRetrieved = 0;
        foreach ($chunks as $smallList) {
            $data = $this->callApi($smallList, $conf);
            pr($this->getErrors());
            pr($data);

            // analyze data and save it to db
            if (is_array($data) && isset($data['Items']['Item'])) {
                $items = isset($data['Items']['Item']['ASIN']) ? [$data['Items']['Item']] : $data['Items']['Item'];
                $processed = [];
                foreach ($items as $product) {
                    $this->saveProduct($product['ASIN'], $this->config->locale, $product);
                    $itemsRetrieved++;
                    $processed[] = $product['ASIN'];
                }
                $failedList = array_diff($smallList, $processed);
                if (count($failedList)) {
                    foreach ($failedList as $failed) {
                        $this->saveProduct($failed, $this->config->locale, []);
                    }
                }
            } elseif (!$data) {
                // call function return false, means some error with the api response.
                break;
            } else {
                foreach ($smallList as $failed) {
                    $this->saveProduct($failed, $this->config->locale, []);
                }
            }

        }
        return $itemsRetrieved;

    }

    /**
     * @param        $e \Exception
     * @param string $msg
     * @return $this
     * altering addError function
     */
    public function addError($e, $msg = "")
    {
        if (!$msg) {
            $msg = $e->getMessage();
        }
        $message = [$msg, $e->getFile(), $e->getLine(), $e->getCode()];
        $message = implode(" ", $message);
        return parent::addError($message);
    }

    /**
     * @param $list  array of asins, up to 10, if more, will be ignored
     * @param $conf
     * @return bool|mixed - false or array of the api response
     */

    private function callApi($list, $conf)
    {
        sleep(2);
        $respGroups = explode(',', $this->config->amazon_responseGroup);
        $lookup = new Lookup();

        if (count($list) > 10) {
            $list = array_slice($list, 0, 10);
        }
        $lookup->setItemId(implode(",", $list));
        $lookup->setResponseGroup($respGroups);

        $apaiIO = new ApaiIO($conf);
        for ($i = 0; $i < 10; $i++) {
            try {
                $formattedResponse = $apaiIO->runOperation($lookup);
                return $formattedResponse;
            } catch (GException  $e) {
                if (!$this->handleError($e)) {
                    // if the error could not be handled, log it and return false;
                    $this->addError($e, $e->getResponse()->getBody()->getContents());
                    return false;
                }
            } catch (\Exception $e) {
                if (!$this->handleError($e)) {
                    // if the error could not be handled, log it and return false;
                    $this->addError($e, $e->getMessage());
                    return false;
                }
            }
            sleep(5);
        }
    }

    /**
     * @param $e
     * @return bool
     * function analyzes error received from Amazon API and gives a clue on how to handle it, to repeat the action or to throw the exception
     */
    private function handleError($e)
    {
        if (strpos($e->getMessage(), '503 Service Unavailable')) {
            return true;
        }
        return false;
    }

    /**
     * @param $asin
     * @param $locale
     * @param $data
     * @param bool $virtual
     *
     * @return array|bool
     */
    public function saveProduct($asin, $locale, $data, $virtual = false)
    {
        $sql = new Sql($this->db);
        $where = new Where();
        $where->equalTo('asin', $asin);
        $where->equalTo('locale', $locale);
        $select = $sql->select('amazon_product')->where($where);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $fields = ['data' => $this->zipData($data), 'api_response' => count($data) ? 1 : 0];
        if (isset($data['api_response'])) {
            $fields['api_response'] = $data['api_response'];
            unset($data['api_response']);
        }
        if (isset($this->config->amazon_fields) || is_array($this->config->amazon_fields)) {
            foreach ($this->config->amazon_fields as $key => $field) {
                $fields[$key] = '';
                if (isset($data['ItemAttributes'][$field])) {
                    if (is_array($data['ItemAttributes'][$field])) {
                        $string = '';
                        foreach ($data['ItemAttributes'][$field] as $fKey => $fVal) {
                            $string .= $fKey . ' ' . $fVal . "\n";
                        }
                        $fields[$key] = $string;
                    } else {
                        $fields[$key] = $data['ItemAttributes'][$field];
                    }
                }
            }
        }
        // $virtual mode for bulk update
        if ($virtual) {
            $fields['asin'] = $asin;
            $fields['locale'] = $locale;
            $fields['created'] = date('Y-m-d H:i:s');
            $fields['modified'] = date('Y-m-d H:i:s');
            return $fields;
        }

        $result = $stmt->execute();
        if ($result->current()) {
            $fields['modified'] = date('Y-m-d H:i:s');
            $update = $sql->update('amazon_product')
                ->where(['asin' => $asin, 'locale' => $locale])
                ->set($fields);
            $stmt = $sql->prepareStatementForSqlObject($update);
        } else {
            $fields['created'] = date('Y-m-d H:i:s');
            $fields['modified'] = date('Y-m-d H:i:s');
            $fields['asin'] = $asin;
            $fields['locale'] = $locale;
            if ($virtual) {
                return $fields;
            }
            $insert = $sql->insert('amazon_product')
                ->values($fields);
            $stmt = $sql->prepareStatementForSqlObject($insert);
        }
        try {
            $stmt->execute();
        } catch (\Exception $e) {
            $this->addError($e);
        }

        return !$this->hasErrors();
    }

    private function zipData($data)
    {
        return gzcompress(serialize($data), 2);
    }

    /**
     * @param $asin
     * @param $locale
     * @param $data
     * @param array $fieldList
     * @return array|bool
     * @throws Exception
     */
    public function getArrayForBulkUpdate($asin, $locale, $data, $fieldList = [])
    {
        if (!$asin || !$locale) {
            throw new Exception('no asin or locale');
        }
        if (empty($fieldList)) {
            //!todo need to move the list to other location, it is not right to have it here, when we already have a list in the config file
            $fieldList = ['asin', 'locale', 'api_response', 'title', 'ean', 'upc', 'brand', 'manufacturer', 'model', 'mpn', 'short_description', 'data', 'modified', 'created', 'item_dimensions', 'package_dimensions', 'size', 'ean_list', 'upc_list'];
        }
        $fieldsEmpty = array_fill_keys($fieldList, null);
        if (!array_key_exists('asin', $fieldsEmpty) || !array_key_exists('locale', $fieldsEmpty)) {
            pr('fieldsEmpty');
            pr($fieldsEmpty);
            throw new Exception('no asin or locale in custom field list');
        }
        $fields = $this->saveProduct($asin, $locale, $data, true);
        // we need to make sure, that there is no key in fields which is in the fieldList
        $fields = array_intersect_key($fields, $fieldsEmpty);
        // adding actual fields above empty array
        $fields = array_merge($fieldsEmpty, $fields);
        return $fields;
    }

    public function simpleUpdate($asin, $locale, $data)
    {
        $sql = new Sql($this->db);
        $where = new Where();
        $where->equalTo('asin', $asin);
        $where->equalTo('locale', $locale);
        $select = $sql->select('amazon_product')->where($where);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $fields = $data;
        if ($result->current()) {
            $fields['modified'] = date('Y-m-d H:i:s');
            $update = $sql->update('amazon_product')
                ->where(['asin' => $asin, 'locale' => $locale])
                ->set($fields);
            $stmt = $sql->prepareStatementForSqlObject($update);
        } else {
            $fields['created'] = date('Y-m-d H:i:s');
            $fields['modified'] = date('Y-m-d H:i:s');
            $fields['asin'] = $asin;
            $fields['locale'] = $locale;
            $insert = $sql->insert('amazon_product')
                ->values($fields);
            $stmt = $sql->prepareStatementForSqlObject($insert);
        }
        try {
            $stmt->execute();
        } catch (\Exception $e) {
            $this->addError($e);
        }


    }

    /**
     * @param $data array
     * @throws Exception
     */
    public function bulkUpdate($data): void
    {
        if (count($data)) {
            $keys = array_keys($data[0]);
            // nasty magic, making a row query with bulk insert, tell me how to do it via ZF
            $query = 'REPLACE INTO `amazon_product` (`' . implode('`, `', $keys) . '`) VALUES ';
            $questionArray = array_fill_keys($keys, '?');
            $questionString = '(' . implode(', ', $questionArray) . ') ';
            $countItems = count($data);
            pr('bulkUpdate about to replace '. $countItems);
            $arrOfQuestionsPerItem = array_fill(0, $countItems, $questionString);
            $query .= implode(', ', $arrOfQuestionsPerItem);
            $listToInsert = [];
            foreach ($data as $item) {
                $item = array_values($item);
                $listToInsert = array_merge($listToInsert, $item);
            }
            /** @var \Laminas\Db\Adapter\Adapter $db */
            $db = $this->db;
            $db->query($query, $listToInsert);
        }
    }

    public function getUnprocessedList($locale, $limit = 100)
    {
        $sql = new Sql($this->db);

        $where = new Where();

        $where->nest()
            ->isNull('ap.amazon_product_id')
            ->or
            ->isNull('ap.data')
            ->unnest()
            ->and
            ->equalTo('p.locale', $locale);

        $select = $sql->select(['p' => 'product'])
            ->join(['ap' => 'amazon_product'], 'ap.asin=p.asin AND ap.locale=p.locale',
                ['amazon_product_id', 'data'], Join::JOIN_LEFT)
            ->where($where)
            ->limit($limit);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = [];
        while ($result->current()) {
            $data = $result->current();
            $list[] = $data['asin'];
            $result->next();
        }
        return $list;
    }

    /**
     * @param       $asin
     * @param       $locale
     * @param array $columns
     * @return array
     */
    public function loadProductFromDbWithoutEmptyFields($asin, $locale, $columns = [])
    {
        $item = $this->loadProductFromDb($asin, $locale, $columns);
        // we do not need empty elements here
        if (count($item)) {
            array_filter($item);
        }
        return $item;
    }

    /**
     * @param       $asin
     * @param       $locale
     * @param array $columns
     * @return array
     */
    public function loadProductFromDb($asin, $locale, $columns = [])
    {
        $sql = new Sql($this->db);
        $where = new Where();
        $where->equalTo('asin', $asin);
        $where->equalTo('locale', $locale);
        $select = $sql->select('amazon_product')->where($where);
        if (count($columns)) {
            $select->columns($columns);
        }
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        if ($item = $result->current()) {
            try {
                if (isset($item['data'])) {
                    $item['data'] = $this->unzipData($item['data']);
                }
            } catch (\Exception $e) {
                $this->addError($e);
            }
            return $item;
        }
        return [];
    }

    private function unzipData($string)
    {
        try {
            return @unserialize(gzuncompress($string));
        } catch (\Exception $e) {
            $this->addError($e);
            return false;
        }
    }

    public function checkMissingAttribute($attributes, $locale)
    {
        if (!count($attributes)) {
            return;
        }
        $sql = new Sql($this->db);
        $whereList = [];
        foreach ($attributes as $key => $field) {
            $whereList[] = $key . " IS NULL ";
        }
        $string = "(" . implode(" or ", $whereList) . " ) AND data IS NOT NULL";

        $query = "SELECT * from `amazon_product` WHERE " . $string . " LIMIT 1000";

        $select = $sql->select('amazon_product')
            ->limit(1);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $stmt->setSql($query);
        //print_r($stmt->getSql());
        $result = $stmt->execute();
        $list = [];
        while ($result->current()) {
            $data = $result->current();
            $list[] = $data;
            $result->next();
        }

        if (count($list)) {
            foreach ($list as $product) {
                $data = $this->unzipData($product['data']);
                $fields = [];
                foreach ($attributes as $key => $field) {
                    if (isset($data['ItemAttributes'][$field])) {
                        $fields[$key] = is_array($data['ItemAttributes'][$field]) ? implode("\n",
                            $data['ItemAttributes'][$field]) : $data['ItemAttributes'][$field];
                    }
                }
                if (count($fields)) {

                    $fields['modified'] = date("Y-m-d H:i:s");
                    //echo "<pre>";
                    //print_r($fields);
                    //print_r($product['amazon_product_id']);
                    $where = new Where();
                    $where->equalTo('amazon_product_id', $product['amazon_product_id']);
                    $update = $sql->update('amazon_product')->set($fields)->where($where);
                    $stmt = $sql->prepareStatementForSqlObject($update);
                    $result = $stmt->execute();
                    //print_r($result);
                }
            }
        }
        return count($list);


    }

    private function setResponse($list, $locale, $response = 0)
    {
        $sql = new Sql($this->db);
        $where = new Where();
        $where->in('asin', array_values($list))
            ->equalTo('locale', $locale);
        $update = $sql->update('amazon_product')
            ->where($where)
            ->set([
                'api_response' => $response,
                'modified' => date('Y-m-d H:i:s'),
                'created' => date('Y-m-d H:i:s'),
            ]);
        $stmt = $sql->prepareStatementForSqlObject($update);
        return $stmt->execute();
    }
}
<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 13.07.2019
 * Time: 16:01
 */

namespace Parser\Model\Amazon\Centric;


use Parser\Model\Amazon\Product as Amroduct;
use yii\db\Exception;
use Laminas\Http\Exception\RuntimeException;

class CentricAPI extends CentricAPIAbsctract
{
    protected $campaignData = [];

    /**
     * @param integer $campaignId
     * @param string $locale
     * @throws Exception
     */
    public function processCampaigns($campaignId = null, $locale = null): void
    {
        // get campaigns data;
        $this->getStatusRequest($campaignId);
        $campaign = $this->getCampaignData();
        pr('campaign status');
        pr($campaign);
        $locale = $locale ?: 'ca';
        $localeConfig = $this->config->getLocaleConfig($locale);
        $campaignId = $campaign['campaignId'];
        if ($campaign['status'] == Campaign::Empty) {
            // we can add products to it and run process
            // 1. get products which has to be processed
            $flow = new Amroduct($localeConfig, $this->getAdapter());
            $limit = $this->config->getConfig('centric')['centricProcessLimit'] ?? 100;
            $list = $flow->getUnprocessedList($locale, $limit);
            if (count($list)) {
                $listToSend = [];
                foreach ($list as $item) {
                    $listToSend[] = ['identifier' => $item, 'type' => 'asin'];
                }
                $addResponse = $this->addProduct($listToSend, $campaignId);
                // we got good add response for products, however, we have to check first
                if (isset($addResponse['Data']['data'])) {
                    // run through response and create amroduct lines with proper api_response
                    /**
                     * [0] => Array
                     * (
                     * [type] => product
                     * [attributes] => Array
                     * (
                     * [id] => 774574413
                     * [identifier] => B07GHMQDCJ
                     * [status] => success
                     * )
                     *
                     * )*/
                    $listToBulkProductInsertIntoAmazonProductTable = [];
                    foreach ($addResponse['Data']['data'] as $item) {
                        $asin = $item['attributes']['identifier'];
                        $status = $item['attributes']['status'];
                        if ($status === 'success') {
                            $dataToSave = ['api_response' => ProductApiResponse::SuccessToAddToCampaign];
                        } else {
                            $dataToSave = ['api_response' => ProductApiResponse::FailToAddToCampaign];
                        }
                        $listToBulkProductInsertIntoAmazonProductTable[] = $flow->getArrayForBulkUpdate($asin, $locale, $dataToSave, ['asin', 'locale', 'api_response', 'created', 'modified']);
                    }
                    pr($addResponse);
                    if (count($listToBulkProductInsertIntoAmazonProductTable)) {
                        $flow->bulkUpdate($listToBulkProductInsertIntoAmazonProductTable);
                    }
                    $this->runProcess($campaignId);
                } else {
                    // failed response? no products were added, we just do nothing here.
                    pr($addResponse);
                }

            }
        } elseif ($campaign['status'] == Campaign::FullCompleted) {
            // put products data into database and empty the campaign
            $localeConfig = $this->config->getLocaleConfig($locale);

            $flow = new Amroduct($localeConfig, $this->getAdapter());
            //prepareProductDataForSave
            $productListData = $this->getAllProducts([], $campaignId);
            pr($productListData);
            $listToBulkProductInsertIntoAmazonProductTable = [];
            if (is_array($productListData) && count($productListData)) {
                foreach ($productListData as $item) {
                    $errorMsg = $item['attributes']['error_msg'];
                    $asin = $item['attributes']['initial_identifier'];
                    $id = $item['id'];
                    if ($errorMsg) {
                        // update empty data
                        $dataToSave['api_response'] = ProductApiResponse::NotFoundOnCentric;
                    } else {
                        $dataToSave = $this->prepareProductDataForSave($item['attributes'], $locale);
                        $dataToSave['api_response'] = ProductApiResponse::FoundOnCentric;
                    }
                    $listToBulkProductInsertIntoAmazonProductTable[] = $flow->getArrayForBulkUpdate($asin, $locale, $dataToSave);
                }
                if (count($listToBulkProductInsertIntoAmazonProductTable)) {
                    $flow->bulkUpdate($listToBulkProductInsertIntoAmazonProductTable);
                }
                $this->deleteProducts($campaignId);
            }


        } elseif ($campaign['status'] == Campaign::FullWaiting) {
            // run process for the campaign
            $this->runProcess($campaignId);
        } elseif ($campaign['status'] == Campaign::FullInProgress) {
            // just ignore it, until it came working.
        }


    }

    /**
     * @param null $campaignId
     * @return array
     * @throws \Exception
     */
    public function getStatusRequest($campaignId = null): array
    {
        $action = self::getStatus;
        $data = [];
        if ($campaignId) {
            $data['campaign_id'] = $campaignId;
        }
        $response = $this->sendRequest($action, $data);
        if ($response['Code'] !== 200) {
            pr($response);
            throw new \Exception('non 200 response code on getStatusRequest');
        }
        $campaign = $response['Data'];
        $campaign['campaignId'] = $campaignId;
        $campaign['status'] = Campaign::getCampaignStatus($campaign);
        $this->setCampaignData($campaign);
        return $campaign;
    }

    /**
     * @return array
     */
    public function getCampaignData(): array
    {
        return $this->campaignData;
    }

    /**
     * @param array $campaignData
     */
    public function setCampaignData(array $campaignData): void
    {
        $this->campaignData = $campaignData;
    }

    /**
     * @param $data array
     * @param null $campaignId
     * @return array|null
     * @throws \Exception
     */
    public function addProduct($data, $campaignId = null): ?array
    {
        if (empty($data)) {
            return null;
        }
        $data['identifiers'] = $data;
        $action = self::addProducts;
        if ($campaignId) {
            $data['campaign_id'] = $campaignId;
        }
        return $this->sendRequest($action, $data);
    }

    public function runProcess($campaignId = null)
    {
        $action = self::runProcess;
        $data = [];
        if ($campaignId) {
            $data['campaign_id'] = $campaignId;
        }
        return $this->sendRequest($action, $data);
    }

    public function getAllProducts($options, $campaignId)
    {
        // the product limit is 1000 or other, we need to process all items.
        /*{
  "data": [],
  "links": {
    "first": "https:\/\/v3.synccentric.com\/api\/v3\/products?page=1",
    "last": "https:\/\/v3.synccentric.com\/api\/v3\/products?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": null,
    "last_page": 1,
    "path": "https:\/\/v3.synccentric.com\/api\/v3\/products",
    "per_page": 100,
    "to": null,
    "total": 0
  }
}*/
        $firstResponse = $this->getProducts($options, $campaignId);
        $list = $firstResponse['data'];
        $meta = $firstResponse['meta'];
        $lastPage = $meta['last_page'];
        pr($meta);
        // getting all other pages
        $attempts = 0;
        $maxFailAttempts = 10;
        $attemptDelay = 5;
        if ($lastPage > 1) {
            $i = 2;
            // starting from the second page
            while ($i <= $lastPage) {
                $options['page'] = $i;
                try {
                    $pageResponse = $this->getProducts($options, $campaignId);
                    $pageList = $pageResponse['data'] ?? [];
                    if (is_array($pageList)) {
                        $list = array_merge($list, $pageList);
                    }
                    $i++;
                } catch (\Exception $exception) {
                    // wait and rerun if got a fail
                    $attempts++;
                    pr('got fail, see if another attempt will help');

                    if ($attempts > $maxFailAttempts) {
                        throw new Exception('too many request fails: ' . $exception->getMessage());
                    }
                    sleep($attemptDelay);
                }

            }
        }
        return $list;
    }

    public function getProducts($options, $campaignId = null)
    {
        /**
         * {
         * "campaign_id": "id",
         * "product_status": "all",
         * "page": 1,
         * "fields": [
         * "asin",
         * "upc",
         * "ean",
         * "brand",
         * "manufacturer"
         * ]
         * }
         */
        $action = self::getAllProducts;

        $data = [
            'product_status' => 'all',
            'retrieve_other_identifiers' => 1,
            'fields' => [
                'asin', 'upc', 'ean', 'mpn', 'title', 'features', 'size', 'model', 'brand', 'manufacturer',
                'package_dimensions_height', 'package_dimensions_length',
                'package_dimensions_weight', 'package_dimensions_width',
                'item_dimensions_height', 'item_dimensions_length', 'item_dimensions_weight', 'item_dimensions_width', 'ean_list', 'upc_list'
            ]
        ];
        /*
         * taking request options from config
         * */
        $cenConfig = $this->config->getConfig('centric');

        $requestDataAdditionalOptions = $cenConfig['requestDataAdditionalOptions'] ?? [];

        $requestFieldsString = $cenConfig['requestFields'] ?? '';
        $fields = $requestFieldsString ? preg_split ('/(\s*,*\s*)*,+(\s*,*\s*)*/', $requestFieldsString) : [];


        if (is_array($requestDataAdditionalOptions)) {
            pr('taking data from config');
            $data = $requestDataAdditionalOptions;
        }
        if (is_array($fields)) {
            pr('taking fields from config');
            $data['fields'] = $fields;
        }
        pr($data);
        if ($campaignId) {
            $data['campaign_id'] = $campaignId;
        }
        if ($options) {
            $data = array_merge($data, $options);
        }
        $response = $this->sendRequest($action, $data);
        return $response['Data'] ?? [];
    }

    public function prepareProductDataForSave($data, $locale)
    {
        // We get this
        /*                    [initial_identifier] => B07GHMQDCJ
                    [asin] => B07GHMQDCJ
                    [upc] =>
                    [ean] => 8809613763942
                    [mpn] => 064CS24873
                    [title] => Spigen Ultra Hybrid Works with Apple iPhone XR Case (2018) - Crystal Clear
                    [features] => Hybrid design made of rigid back and flexible bumper | Slim protection stays pocket and grip-friendly | Long-lasting clarity resistant to yellowing | Supports wireless charging and compatible with Glas.tR | iPhone XR Case Designed for Apple iPhone XR (6.1 inches)
                    [size] =>
                    [model] => 064CS24873
                    [brand] => Spigen
                    [package_dimensions_height] => 0.7874015740
                    [package_dimensions_length] => 6.2992125920
                    [package_dimensions_weight] => 0.220462262
                    [package_dimensions_width] => 3.5433070830
                    [error_msg] => */
        // need to transfer into this
        /*
            <mpn>MPN</mpn>
            <ean>EAN</ean>
            <upc>UPC</upc>
            <model>Model</model>
            <manufacturer>Manufacturer</manufacturer>
            <short_description>Feature</short_description>
            <brand>Brand</brand>
            <title>Title</title>
            <size>Size</size>
            <item_dimensions>ItemDimensions</item_dimensions>
            <package_dimensions>PackageDimensions</package_dimensions>
        */
        $localeConfig = $this->config->getLocaleConfig($locale);
        $listOfFields = $localeConfig['settings']['amazon_fields'] ?? [];
        $listToReturn = [];
        if ($listOfFields) {
            foreach ($listOfFields as $key => $field) {
                if (isset($data[$key]) && $data[$key]) {
                    $listToReturn[$field] = $data[$key];
                }
            }
            if (isset($listOfFields['item_dimensions'])) {
                /*[item_dimensions_height] => 0.7874015740
                    [item_dimensions_length] => 6.2992125920
                    [item_dimensions_weight] => 0.220462262
                    [item_dimensions_width] => 3.5433070830*/
                $listToReturn[$listOfFields['item_dimensions']] = 'w:' . $data['item_dimensions_width'] . '; ' .
                    'h:' . $data['item_dimensions_height'] . '; ' .
                    'l:' . $data['item_dimensions_length'] . '; ' .
                    'wei:' . $data['item_dimensions_weight'];

            }
            if (isset($listOfFields['package_dimensions'])) {
                /*[package_dimensions_height] => 0.7874015740
                    [package_dimensions_length] => 6.2992125920
                    [package_dimensions_weight] => 0.220462262
                    [package_dimensions_width] => 3.5433070830*/
                $listToReturn[$listOfFields['package_dimensions']] = 'w:' . $data['package_dimensions_width'] . '; ' .
                    'h:' . $data['package_dimensions_height'] . '; ' .
                    'l:' . $data['package_dimensions_length'] . '; ' .
                    'wei:' . $data['package_dimensions_weight'];
            }
            if (isset($listOfFields['short_description'])) {
                $listToReturn[$listOfFields['short_description']] = $data['features'] ?? '';
            }
        }
        return ['ItemAttributes' => $listToReturn];
    }

    /**
     * @param null $campaignId
     * @return array|null
     * @throws \Exception
     */
    public function deleteProducts($campaignId = null)
    {
        $action = self::deleteAllProducts;
        $data = [];
        if ($campaignId) {
            $data['campaign_id'] = $campaignId;
        }
        return $this->sendRequest($action, $data);
    }

    /**
     * @param $id
     * @param null $campaignId
     * @return array|null
     * @throws \Exception
     */
    public function deleteProduct($id, $campaignId = null)
    {
        if (!$id) {
            return null;
        }
        $action = self::deleteProduct;
        $data = [];
        if ($campaignId) {
            $data['campaign_id'] = $campaignId;
        }
        return $this->sendRequest($action, $data, $id);
    }

    public function deleteCampaign($campaignId)
    {

    }

    public function createCampaign()
    {
        // setting a new campaign, setting up locale option etc.
    }

    public function cancelProcess($campaignId = null)
    {

    }
}
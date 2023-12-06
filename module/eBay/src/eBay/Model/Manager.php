<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.11.2018
 * Time: 16:13
 */

namespace eBay\Model;

//use DTS\eBaySDK\Shopping\Services;
//use DTS\eBaySDK\Shopping\Types;

use DTS\eBaySDK\Constants;
use DTS\eBaySDK\Finding\Services\FindingService;
use DTS\eBaySDK\Finding\Types;
use GuzzleHttp\Exception\RequestException;

class Manager
{
    public $connectMessage;
    private $service;
    private $request;

    public function __construct()
    {
        // TODO move credentials to the config
        $this->service = new FindingService([
            'apiVersion' => '1.13.0',
            'globalId' => Constants\GlobalIds::US,
            'sandbox' => false,
            'credentials' => [
                'appId' => 'EugeneKa-webexper-PRD-ef95f0a8e-810d5d61',
                'certId' => '0609b3cd-dc9f-42bb-aafb-5735730a53b7',
                'devId' => 'PRD-f95f0a8ef854-349e-4f5b-979a-903f',
//                'appId' => 'EugeneKa-webexper-SBX-7c23583ec-89783a50',
//                'certId' => '0609b3cd-dc9f-42bb-aafb-5735730a53b7',
//                'devId' => 'SBX-c23583ec0377-ebe1-4669-b09b-e9da',
            ],
        ]);
        //$this->request = new Types\GeteBayTimeRequestType();
        //$service = new FindingService();
    }

    public function testConnection()
    {
        //$response = $this->service->geteBayTime($this->request);
        //return sprintf("The official eBay time is: %s\n", $response->Timestamp->format('H:i (\G\M\T) \o\n l jS Y'));
    }

    /**
     * @param $word string
     * @return array|bool
     */
    public function testRequest($word)
    {
        $request = new Types\FindItemsAdvancedRequest();
        $request->keywords = $word;
        $request->sortOrder = 'WatchCountDecreaseSort';
        $request->outputSelector = ['PictureURLSuperSize', 'SellerInfo', 'UnitPriceInfo', 'AspectHistogram'];
        //$request->itemFilter = ["MinPrice" => "100"];
        $request->paginationInput = new Types\PaginationInput();
        $request->paginationInput->entriesPerPage = 100;
        $request->paginationInput->pageNumber = 1;
        try {
            $response = $this->service->findItemsAdvanced($request);
            $list = [];
            foreach ($response->searchResult->item as $item) {
                $list[] = $item;
            }
            return $list;
        } catch (\GuzzleHttp\Exception\ClientException  $e) {
            $this->connectMessage = $e->getResponse()->getBody()->getContents();
            return false;
        } catch (RequestException $e) {
            $this->connectMessage = $e->getMessage();
            return false;
        } catch (\Exception $e) {
            $this->connectMessage = $e->getMessage();
            return false;
        }

    }
}
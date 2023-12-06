<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 07.09.2017
 * Time: 16:51
 */

namespace Parser\Model;


use Parser\Model\Amazon\Attributes\FastTrack;
use Parser\Model\Amazon\Attributes\ProductDimensions;
use Parser\Model\Amazon\Attributes\ShippingWeight;
use Parser\Model\Amazon\Attributes\Weight;
use Parser\Model\Amazon\ProductMarker;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\Logger;
use Laminas\Json\Json as ZJson;

class ProductDetails extends SimpleObject
{
    public $config;
    public $xpath;
    public $logger;
    public $asin;

    public function __construct(Logger $logger, $asin = null)
    {
        $this->logger = $logger;
        $this->asin = $asin;
    }

    public function parse($content, $config): array
    {
        if (!$content) {
            return [
                'title' => '',
                'stock' => 0,
                'stockString' => '',
                'isAddon' => false,
                'category' => '',
                'description' => '',
                'short_description' => '',
                'mpn' => '',
                'made_by' => '',
                'images' => '',
                'delivery_data' => '',
                'shippingPrice' => '',
            ];
        }
        $this->xpath = null;

        $this->config = $config;

        $paths = (object)$this->config['productPage']['paths'];

        // regular stock extraction
        $stockHtml = implode('', $this->extractFromUl($content, $paths->stock));

        $fastTrackHtml = implode('', $this->extractFromUl($content, $paths->fastTrack));
        $fastTrack = strip_tags($fastTrackHtml);

        $fTrack = new FastTrack();
        $fastTrackTo = $fTrack->getDate($fastTrack);
        $fastTrackFrom = date('M d', time());
        $fastTrackDays = $fTrack->days;


        // get stock from dropdown options
        $stockDropDownCount = 0;
        if (isset($paths->stockDropDownOptions)) {
            $stockDropDownOptions = $this->getResourceByXpath($content, $paths->stockDropDownOptions);
            if (is_object($stockDropDownOptions) && count($stockDropDownOptions)) {
                $stockDropDownCount = count($stockDropDownOptions);
            }
        }
        $stock = self::getStock($stockHtml, $this->config['productPage']['stockTags'], $stockDropDownCount);

        $titleHtml = $this->_getContentFromHTMLbyXpath($content, $paths->title);

        $title = trim(strip_tags($titleHtml));

        /* Merchant Info*/
        $merchantInfoHtml = $this->_getContentFromHTMLbyXpath($content, $paths->merchantInfo);
        $merchantInfo = trim(strip_tags($merchantInfoHtml));

        /* If the product is Addon*/
        $isAddon = $this->_getContentFromHTMLbyXpath($content, $paths->addOn);
        $isAddon = trim(strip_tags($isAddon));
        $isAddon = (bool)$isAddon;

        $featureSign = $this->config['productPage']['featureSign'] ?? '';

        /* Details */
        $details = $this->extractFromUl($content, $paths->content);

        $mpn = '';

        /* product information */


        $informationLabels = $this->extractFromUl($content, $paths->contentTableLabel);
        $informationValues = $this->extractFromUl($content, $paths->contentTableValue);
        if (count($informationLabels)) {
            foreach ($informationLabels as $key => $label) {
                if (isset($informationValues[$key])) {
                    $details[] = $label . ': ' . $informationValues[$key];
                }
            }
        }


        $reviewsQty = (int)strip_tags($this->_getContentFromHTMLbyXpath($content, $paths->customerReviews));

        /* weight and dimensions */
        $dimension_data = [
            'weight' => '',
            'shipping_weight' => '',
            'dimension' => '',
        ];
        $weight = null;

        $dimension = null;
        if (isset($this->config['attributes']['checkWeight']) && $this->config['attributes']['checkWeight']) {
            $weightObject = new Weight($this->config, $this->xpath);
            $weightData = $weightObject->extract();
            $dimension_data['weight'] = $weightObject->string;

            $shipWeight = new ShippingWeight($this->config, $this->xpath);
            $shipWeightData = $shipWeight->extract();
            $dimension_data['shipping_weight'] = $shipWeight->string;

            if (!$weightData && !$shipWeightData) {
                // need to log this.
                if ($this->asin && $title) {
                    $this->logger->add($this->asin, 'missing weight data');
                    //print_r("weight missing");
                }
                $weight = null;
            } else {
                // we have one of the shipping data
                $weight = $shipWeightData ?: $weightData;
                if (isset($this->config['attributes']['weightLimit']) && $weight > $this->config['attributes']['weightLimit']) {
                    $weight = null;
                }

            }
        }

        if (isset($this->config['attributes']['checkDimension'])
            && $this->config['attributes']['checkDimension']) {
            $prodDim = new ProductDimensions($this->config, $this->xpath);
            $dimension = $prodDim->extract();
            $dimension_data['dimension'] = $prodDim->string;
            if (!$dimension) {
                if ($this->asin && $title) {
                    $this->logger->add($this->asin, 'missing dimension data');
                    //print_r("dimensions missing");
                }
                $dimension = null;
            } else if (isset($this->config['attributes']['dimensionLimit']) && $dimension > $this->config['attributes']['dimensionLimit']) {
                $dimension = null;
            }
        }
        $skips = $this->config['productPage']['skip'];
        /* Features */
        $shortDescription = $this->extractFromUl($content, $paths->features);
        $details = array_merge($details, $shortDescription);
        if (count($details)) {
            foreach ($details as $k => $detail) {
                if (strpos($detail, $paths->mpnTag) !== false) {
                    $mpn = trim(str_replace($paths->mpnTag, '', $detail));
                    unset($details[$k]);
                    continue;
                }
                foreach ($skips as $valueToReplace) {
                    if (strpos($detail, $valueToReplace) !== false) {
                        unset($details[$k]);
                        continue 2;
                    }
                }
                if ($featureSign) {
                    $details[$k] = $featureSign . $detail;
                }

            }
        }
        if (isset($this->config['productPage']['combinedDescription']['sections']['secondary']['fields']['field']) && is_array($this->config['productPage']['combinedDescription']['sections']['secondary']['fields']['field'])) {
            $tmpShortDesc = is_array($details) ? implode(" \n", $details) : $details;
            $fieldsToMove = $this->config['productPage']['combinedDescription']['sections']['secondary']['fields']['field'];
            foreach ($fieldsToMove as $field) {
                if (strpos($tmpShortDesc, $field) !== false) {
                    // found the marker
                    foreach ($details as $key => $item) {
                        if (strpos($item, $field) !== false) {
                            unset($details[$key]);
                            array_push($details, $item);
                        }
                    }
                }
            }
        }

        $details = array_values($details);

        $shortDescription = is_array($details) ? implode(" \n", $details) : $details;

        /* description */
        $description = $this->extractFromUl($content, $paths->description);

        /* remove links from details and implode */
        $description = is_array($description) ? implode(" \n", $description) : $description;
        $description = str_replace('Product description', '', $description);

        /* category */
        $category = $this->extractCategoryFromUl($content, $paths->category);
        /* made by tag */
        $madeBy = '';
        $madeByLink = $this->getFirstElementByXpath($content, $paths->madeby);
        if ($madeByLink) {
            if ($madeByLink->textContent) {
                $madeBy = $madeByLink->textContent;
            } else {
                $madebyHref = $madeByLink->getAttribute('href');
                $madebyHref = substr($madebyHref, 1);
                $madeBy = substr($madebyHref, 0, strpos($madebyHref, '/'));
            }
        }
        if (isset($paths->shipping)) {
            $shippingPath = $paths->shipping;
            $shippingPriceObject = $this->getFirstElementByXpath($content, $shippingPath);
            if ($shippingPriceObject && isset($shippingPriceObject->textContent)) {
                $shippingPriceContent = $shippingPriceObject->textContent;
                $shippingPrice = self::getFloat($shippingPriceContent);
            } else {
                $shippingPrice = 0;
            }
        } else {
            $shippingPrice = 0;
        }


        /* Delivery Data */
        $deliveryData = '';
        if (isset($paths->deliveryData)) {
            $deliveryObject = $this->getFirstElementByXpath($content, $paths->deliveryData);
            if ($deliveryObject) {
                $deliveryData = trim($deliveryObject->textContent);
            }
        }
        /* Images */
        $images = $this->extractImages($content, $paths);

        /* prices */
        $pricePaths = (object)$this->config['productPage']['priceTags'];

        $prices = [];
        foreach ($pricePaths as $path) {
            $priceHtml = $this->_getContentFromHTMLbyXpath($content, $path);
	    $prices[] = $this->getFloat($priceHtml);
        }
        $regularPriceTag = $this->config['productPage']['regularPrice'] ?? '';
        $regularPrice = 0;
        if ($regularPriceTag) {
            $regularPriceHtml = $this->_getContentFromHTMLbyXpath($content, $regularPriceTag);
            $regularPrice = $this->getFloat($regularPriceHtml);
        }
        $importFeeTag = $this->config['productPage']['importFee'] ?? '';
        $importFee = null;
        if ($importFeeTag) {
            $importFeeTagHtml = $this->_getContentFromHTMLbyXpath($content, $importFeeTag);
            $importFee = $this->getFloat($importFeeTagHtml);
        }

        $price = 0;
        foreach ($prices as $priceCandidate) {

            if ($priceCandidate > 0) {
                if ($price === 0) {
                    $price = $priceCandidate;
                } elseif ($priceCandidate < $price) {
                    $price = $priceCandidate;
                }
            }
            if ($regularPrice < $priceCandidate) {
                $regularPrice = $priceCandidate;
            }
        }


        if ($this->asin) {
            $pm = new ProductMarker($this->xpath, $this->config);
            [$marker, $asinCheck] = $pm->check($this->asin);
            if (!$marker) {
                $stock = 0;
                $stockHtml = 'missing product marker';
            } elseif (!$asinCheck) {
                $stock = 0;
                $stockHtml = 'wrong variation content received';
            }
        }

        /* total data */

        $data = [
            'title' => Helper::stripDomains($title),
            //'merchantInfo' => $merchantInfo,
            'stock' => $stock,
            'stockString' => $stockHtml,
            'fast_track' => $fastTrack,
            'fast_track_to' => $fastTrackTo,
            'fast_track_from' => $fastTrackFrom,
            'fast_track_days' => $fastTrackDays,
            'isAddon' => $isAddon,
            'category' => is_array($category) ? implode(' | ', $category) : $category,
            'description' => Helper::stripDomains($description),
            'short_description' => Helper::stripDomains($shortDescription),
            'mpn' => Helper::stripDomains($mpn),
            'made_by' => Helper::stripDomains($madeBy),
            'images' => $images,
            'weight' => $weight,
            'dimension' => $dimension,
            'dimension_data' => serialize($dimension_data),
            'delivery_data' => $deliveryData,
            'reviews_qty' => $reviewsQty,
            'prime' => '',
        ];
        if ($shippingPrice) {
            $data['shippingPrice'] = $shippingPrice;
        }

        // setting up product page prices.
        if ($importFee !== null) {
            $data['product_page_import_fee'] = $importFee;
        } else {
            $data['product_page_import_fee'] = 0;
        }
        if ($price) {
            $data['product_page_price'] = $price;
        }
        if ($regularPrice) {
            $data['regular_price'] = $regularPrice;
        }
        $merchantInfo = $this->getMerchantInfo($content);
        if ($merchantInfo) {
            $data = array_merge($data, $merchantInfo);
        }
        return $data;
    }

    public function extractFromUl($html, $path): array
    {
        $debug = false;
        if ($path == ".//div[@id='bylineInfo_feature_div']//a") {
            $debug = true;
        }
        $res = $this->getResourceByXpath($html, $path);
        $features = [];
        if ($res->length ?? null) {
            foreach ($res as $element) {

                $textContent = self::extractTextContent($element, $debug);
                $features[] = self::extendedTrim($textContent);
            }
        }
        return $features;
    }

    /**
     * @param $html
     * @param $path
     * @return \DOMNodeList|false
     */
    public function getResourceByXpath($html, $path)
    {
        /* TODO fix to remove broken html, move html tag to config files */
        $html = Helper::removeTableTag('id="HLCXComparisonTable"', $html);
        if (!$this->xpath) {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            @$dom->loadHTML($html);
            $this->xpath = new \DOMXPath($dom);
        }
        return $this->xpath->query($path);
    }

    public static function extractTextContent($element, $debug = false)
    {
        $text = '';
        if ($element->hasChildNodes()) {
            if ($debug) {
                pr($element->nodeName);
            }
            foreach ($element->childNodes as $childNode) {
                $text .= self::extractTextContent($childNode, $debug);
            }
        } else {
            if ($debug) {
                pr($element->nodeName);
            }
            if (isset($element->nodeName)) {
                if ($element->nodeName == '#text') {
                    $text .= $element->textContent;
                }
            } else {
                $text .= $element->textContent;
            }
        }
        return $text;
    }

    public static function extendedTrim($string)
    {
        $data = explode("\n", $string);
        $newData = [];
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $v = trim($v);
                if ($v) {
                    $newData[] = $v;
                }
            }
        }
        return implode(' ', $newData);
    }

    public static function getStock($stock, $paths, $stockDropDownCount)
    {
        //        $stockDropdown keeps the qty which is placed in the dropdown of qty select on the product page.


        foreach ($paths as $stockPattern) {
            if (is_array($stockPattern) && isset($stockPattern['pattern'])) {
                [$match, $qty] = self::processStockPattern($stockPattern, $stock, $stockDropDownCount);
                if ($match) {
                    return $qty;
                }
            }
        }

        // deprecated
        if (isset($paths['stockFull']) && $stock && (strpos($stock, $paths['stockFull']) !== false)) {
            return $stockDropDownCount ?: $paths['stockFullQty'];
        }

        $stocks = $paths['stock'] ?? '';
        if ($stock && $stocks) {
            preg_match($stocks, $stock, $matches);
            if (isset($matches[1]) && ((int)$matches[1])) {
                return $stockDropDownCount ?: (int)$matches[1];
            }
        }
        if ($stock && isset($paths['stockUsualDays'])) {
            preg_match($paths['stockUsualDays'], $stock, $matches);
            if (isset($matches[0]) && $matches[0]) {
                return $paths['stockUsualDaysQty'] ?? $paths['stockFullQty'];
            }
        }
        return 0;
    }

    /**
     * @param $stockPattern
     * @param $stockString
     * @param $stockDropDownCount
     * @return array
     */
    public static function processStockPattern($stockPattern, $stockString, $stockDropDownCount): array
    {
        $strategy = $stockPattern['strategy'] ?? null;
        $pattern = $stockPattern['pattern'];
        $qtyMarker = $stockPattern['qty'] ?? null;
        $check = $stockPattern['check'] ?? false;
        if ($strategy === 'strpos') {
            if ($stockString && (strpos($stockString, $pattern) !== false)) {
                return [1, $stockDropDownCount ?: $qtyMarker];
            }
        }

        if ($strategy === 'pregmatch' && $check !== false) {
            preg_match($pattern, $stockString, $matches);
            if (isset($matches[$check]) && ($matches[$check])) {
                $qtyFound = $qtyMarker !== 'match' ? $qtyMarker : $matches[$check];
                $qty = $stockDropDownCount ?: $qtyFound;
                return [1, $qty];
            }
        }
        $months = $stockPattern['stock_months'] ?? '';
        if ($strategy === 'in_stock_on_date' && $months && $check) {
            $months = explode(',', $months);
            $months = array_map('trim', $months);

            if ($stockString && (strpos($stockString, $pattern) !== false)) {
                $match = str_replace($months, '', $stockString) !== $stockString;
                $stockString = str_replace(',', '', $stockString);
                $stockStringArray = explode(' ', $stockString);
                if ($match) {
                    $intersect = array_intersect($stockStringArray, $months);
                    // found some date marker, now there are two options - dates range or just date
                    $month = array_shift($intersect);
                    $key = array_search($month, $stockStringArray, true);
                    $day = $stockStringArray[$key + 1] ?? '';
                    $day = $day ? (int)$day : '';
                    $ft = new FastTrack();
                    $days = $ft->calculateDaysToDeliver($months, $day, $months);
                    if ($days && $days <= $check) {
                        $qty = $stockDropDownCount ?: $qtyMarker;
                        return [1, $qty];
                    }
                }


            }
        }
        return [false, 0];
    }

    public function _getContentFromHTMLbyXpath($html, $path)
    {
        $res = $this->getResourceByXpath($html, $path);
        return self::_getContentFromElement($res, '%s');
    }

    public static function _getContentFromElement($res, $htmlWrap)
    {
        $productDescription = '';
        $i = 0;
        $separator = '';
        if ($res->length) {
            foreach ($res as $element) {
                $xDoc = new \DOMDocument('1.0', 'UTF-8');
                $cloned = @$element->cloneNode(true);
                $xDoc->appendChild($xDoc->importNode($cloned, true));
                if ($i) {
                    $separator = '';
                }
                $productDescription .= $separator . $xDoc->saveHTML();
                $i++;
            }
            $productDescription = sprintf($htmlWrap, $productDescription);
        }
        return $productDescription;
    }

    public function extractCategoryFromUl($html, $path)
    {
        $data = $this->extractFromUl($html, $path);
        if (count($data)) {
            $data = array_values($data);
        }
        return $data;
    }

    public function getFirstElementByXpath($html, $path)
    {
        $res = $this->getResourceByXpath($html, $path);
        if ($res->item(0)) {
            return $res->item(0);
        }
        return null;
    }

    /**
     * @param $str
     * @return float value with checking locale config (thousand sep and decimal point)
     */
    public function getFloat($str)
    {
        $price = Helper::getFloat($str, $this->config['productPage']);
        return $price;
    }

    public function extractImages($html, $paths)
    {

        $content = Helper::getJsonObjectFromHtml($html, '\'colorImages\':', '\'colorToAsin\': ');
        $content = str_replace('\'initial\'', '"initial"', trim($content));
        if (substr($content, -1) === ',') {
            $content = substr($content, 0, -1);
        }
        $json = json_decode($content, false, 512, JSON_INVALID_UTF8_IGNORE);
        if (json_last_error() === JSON_ERROR_NONE) {
            $images = [];
            if (isset($json->initial) && count($json->initial)) {
                foreach ($json->initial as $value) {
                    if (isset($value->hiRes)) {
                        $images[] = $value->hiRes;
                    } elseif (isset($value->large)) {
                        $images[] = $value->large;
                    }
                }
            }
            return implode('|', $images);
        } else {

            // try mobile
            $path = '//div[@data-a-image-name="altImage"]';
            $res = $this->getResourceByXpath($html, $path);
            if ($res && $res->count()) {
                $images = [];
                foreach ($res as $item) {
                    if ($item->getAttribute('data-zoom-hires')) {
                        $images[] = $item->getAttribute('data-zoom-hires');
                    }
                }
                return implode('|', $images);
            } else {
                // Создаем массив с ошибками.
                $constants = get_defined_constants(true);
                $json_errors = array();
                foreach ($constants["json"] as $name => $value) {
                    if (!strncmp($name, "JSON_ERROR_", 11)) {
                        $json_errors[$value] = $name;
                    }
                }
                // note, if product is move to active - this one will trigger all the time.
                $this->logger->add($this->asin, 'images parse error: ' . $json_errors[json_last_error()]);

            }

        }

//        $json = Helper::JsonDecode($content, 1);
        return '';

    }

    public function getMerchantInfo($content)
    {

        $locale = $this->config['settings']['locale'] ?? 'it';
        $amazonMerchantMarker = 'Amazon';
        $data = ['merchantName' => '', 'merchantId' => '', 'prime' => false, 'delivery' => ''];

        $merchantBlockPathLink = ".//div[@id='merchant-info']//a/@href";
        $merchantBlockPathContent = ".//div[@id='merchant-info']//a";
        $merchantBlockContent = ".//div[@id='merchant-info']";
        $link = $this->getFirstElementByXpath($content, $merchantBlockPathLink);
        $linkContentDomeElement = $this->getFirstElementByXpath($content, $merchantBlockPathContent);
        if ($link && $link->textContent ?? null) {
            $url = $link->textContent;
            $urlData = parse_url($url);
            parse_str($urlData['query'], $queryData);
            $data['merchantId'] = $queryData['seller'] ?? '';
            if (isset($queryData['isAmazonFulfilled'])) {
                $data['prime'] = (bool)$queryData['isAmazonFulfilled'];
            }

        }
        if ($linkContentDomeElement) {
            $data['merchantName'] = trim($linkContentDomeElement->textContent);
        }

        $merchantContent = $this->getFirstElementByXpath($content, $merchantBlockContent);
        if ($merchantContent) {
            $delivery = trim($merchantContent->textContent);
            if (strpos($delivery, $amazonMerchantMarker) !== false) {
                // that is the amazon
                $data['merchantName'] = $data['merchantName'] ?: 'Amazon';
                $data['merchantId'] = $data['merchantId'] ?: ProductOffer::_getAmazonMerchantId($locale);
                $data['prime'] = true;
            }
            $data['delivery'] = $delivery;
            // processing country check
        }
        return $data;
    }

    /**
     * Check if the product page is related to the exact asin we are looking for.
     * @param $content
     * @param $asin
     * @return bool
     */
    public function isContentRelatedToAsin($contentAsin, $asin)
    {
        return strpos($contentAsin->textContent, $asin) !== false;
    }

    public function detectProductMarker($content, $asin)
    {
        // TODO move path to config
        $contentAsin = $this->getFirstElementByXpath($content, '//link[@rel="canonical"]/@href');
    }

    public function extractImagesByPath($html, $path)
    {
        $matches = [];
        preg_match_all($path, $html, $matches);
        //print_r($matches);
        $features = [];
        if (isset($matches[1])) {
            foreach ($matches[1] as $value) {
                $features[$value] = $value;
            }
            $features = array_values($features);

        }
        return $features;
    }
}

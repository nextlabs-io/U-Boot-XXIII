<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 07.09.2017
 * Time: 15:05
 */

namespace Parser\Model;


use Parser\Model\Helper\Helper;
use Parser\Model\Helper\Logger;

/**
 * Class ProductOffer
 * @package Parser\Model
 *
 * simpleObject messaging is used to collect sync log information
 */
class ProductOffer extends SimpleObject
{
    public $config;
    public $logger;
    public $asin;
    public $returnUrl;
    public $xpath;

    public function __construct(Logger $logger, $asin)
    {
        // we need these to log some data, like count offers.
        $this->logger = $logger;
        $this->asin = $asin;
    }

    /**
     * @param $content
     * @param $config
     * @param $locale
     * @return array
     */
    public function parse($content, $config, $locale): array
    {
        $this->config = $config;
        $paths = (object)$this->config['offersPage']['paths'];
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);
        $this->xpath = $xpath;
        $res = $xpath->query($paths->offer); // --- offers
        $this->logger->add($this->asin, 'count offers:' . $res->length);
        $this->addMessage('parsing offers, found offers:' . $res->length);
        $iterator = 0;
        $returnProductUrl = '';
        if (isset($paths->returnUrl)) {
            $urlObject = $this->getFirstElementByXpath($content, $paths->returnUrl);
            if ($urlObject) {
                $returnProductUrl = $urlObject->getAttribute('href');
            }
        }
        $this->returnUrl = $returnProductUrl;
        $data = [];
        foreach ($res as $element) {

            $iterator++;
            // skip first element, which holds all elements data, !TODO check why.
            if ($iterator === 1 && count($res) > 1) {
                continue;
            }


            $price = $xpath->query($paths->price, $element);
            $prime = $xpath->query($paths->prime, $element);
            $prime = (null !== $prime->item(0));
            if (isset($paths->prime2)) {
                $prime2 = $xpath->query($paths->prime2, $element);
                $prime2 = (null !== $prime2->item(0));
            } else {
                $prime2 = false;
            }

            $shipping = $xpath->query($paths->shipping, $element);
            $condition = $xpath->query($paths->condition, $element);
            $seller = $xpath->query($paths->seller, $element);
            $offerID = $xpath->query($paths->offerID, $element);

            $sellerName = $xpath->query($paths->sellerName, $element);
            $deliveryData = $xpath->query($paths->delivery, $element);

            $addOn = $xpath->query($paths->isAddon, $element);
            $addOn = (null !== $addOn->item(0)) ? (bool)trim($addOn->item(0)->textContent) : false;
            if (null === $price->item(0)) {
                continue;
            }

            $price = $this->_getFloatPrice($price->item(0)->textContent);

            $i = 0;
            $merchantUrls = [];
            while (null !== $seller->item($i)) {
                $merchantUrls[] = $seller->item($i)->getAttribute('href');
                $i++;
            }
            unset($seller);
            $merchantId = self::_getMerchantIDFromURL($merchantUrls);

            // do not retreive the shipping price
            if($shipping) {
                $shippingPrice = self::_getFloatPrice($shipping->item(0)->textContent);
            } else {
                $shippingPrice = 0;
            }

            $offerListingId = (null !== $offerID->item(0)) ? $offerID->item(0)->getAttribute('value') : null;

            $conditionStr = trim($condition->item(0)->textContent);


            if (!$merchantId) {
                $merchantId = self::_getAmazonMerchantId($locale) ? self::_getAmazonMerchantId($locale) : 'Amazon';
            }

            $name = (null !== $sellerName->item(0)) ? $sellerName->item(0)->textContent : 'Amazon';

            $delivery = Helper::extendedTrim(Helper::_getContentFromElement($deliveryData, '%s'));
            $regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
            $links = [];
            /* $matches[2] = array of link addresses
             $matches[3] = array of link text - including HTML code */
            if (preg_match_all("/$regexp/siU", $delivery, $matches) && isset($matches[2], $matches[3])) {
                $links = array_combine($matches[3], $matches[2]);
            }
            $delivery = trim(strip_tags($delivery));
//            if (count($links)) {
//                foreach ($links as $title => $link) {
//                    $delivery .= "\n" . $title . ':' . $link;
//                }
//            }


            $key = $merchantId . '_' . $offerListingId . '_' . $price;

            $merchant = [
                'merchantId' => $merchantId,
                'merchantName' => $name,
                'offer_page_price' => $price,
                'prime' => ($prime || $prime2) ? true : false,
                'shippingPrice' => $shippingPrice,
                'shipping' => trim(str_replace('&amp;', '',
                    strip_tags(ProductDetails::_getContentFromElement($shipping, '%s')))),
                'delivery' => $delivery,
                'isAddon' => $addOn,
            ];
            $data[$key] = $merchant;
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

    public function _getFloatPrice($str)
    {
        $config = isset($this->config['offersPage']['dec_point']) ? $this->config['offersPage'] : $this->config['productPage'];
        return Helper::getFloat($str, $config);
    }

    public static function _getMerchantIDFromURL($urls)
    {
        if (is_array($urls) && count($urls)) {
            foreach ($urls as $k => $v) {
                if (strpos($v, '&seller') !== false) {
                    // found url with seller attribute
                    $urlData = parse_url($v);
                    $query = explode('&', $urlData['query']);
                    foreach ($query as $attribute) {
                        if (strpos($attribute, 'seller') !== false) {
                            return str_replace('seller=', '', $attribute);
                        }
                    }
                }
            }
        }
        return null;
    }

    public static function _getAmazonMerchantId($locale)
    {
        $data = [
            'com' => 'ATVPDKIKX0DER',
            'fr' => 'A1X6FK5RDHNB96',
            'it' => 'A11IL2PNWYJU7H',
            'cn' => 'A1AJ19PSB66TGU',
            'jp' => 'AN1VRQENFRJN5',
            'in' => '',
            'uk' => 'A3P5ROKL5A1OLE',
            'ca' => 'A3DWYIK6Y9EEQB',
            'de' => 'A3JWKAKR8XB7XF',
            'es' => 'A1AT7YVPFBWXBL',
        ];
        return $data[$locale] ?? '';
    }

}
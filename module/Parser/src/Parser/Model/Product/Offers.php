<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 28.10.2020
 * Time: 12:37
 */

namespace Parser\Model\Product;


use Parser\Model\Amazon\ProductMarker;
use Parser\Model\Helper\Helper;
use Parser\Model\SimpleObject;
use Parser\Model\Web\Browser;

/**
 * Class Offers This class is aware of the price/stock or other definitions in the product page, which can't be found in the offers page. And provides a
 *
 * @package Parser\Model\Product
 */
class Offers extends SimpleObject
{

    private $offers;
    private $browser;


    public function __construct($offers, Browser $browser)
    {
        // offers are ordered by the offer value
        $this->offers = $offers;
        $this->browser = $browser;
    }

    public function getOffersToCheck($checkedInStockOrUncheckedOffers)
    {
        // sample, if we get the offer with import fee, we recalculate offerValue according to it.
        $finalPrices = [];
        // if we have at least one import fee, we have to check all offers until we get the lowest price.
        $checked = [];
        $unchecked = [];

        $importFeePresent = $this->checkImportFeePresent($checkedInStockOrUncheckedOffers);
        // scan all offers for now
        return true;
        if ($importFeePresent) {
            [$finalPrices, $uncheckedPrices, $checked, $unchecked] = $this->extractOffersData($checkedInStockOrUncheckedOffers);
            if ($unchecked) {
                // we still have unchecked, but if there is a checked offer. which has a minimum of all possible prices - return false;
                // get minimum price of checked and compare to unchecked.
                asort($finalPrices);
                $minimumCheckedPrice = min($finalPrices);
                $minimumUncheckedPrice = min($uncheckedPrices);
                if ($minimumCheckedPrice <= $minimumUncheckedPrice) {
                    return false;
                }
                return true;
            } else {
                // no more offers to check
                return false;
            }

        } else {
            // do not check the other offer;
            return false;
        }
        // finalPrices is a term to compare. taking lowest.

    }

    public function checkImportFeePresent($offers)
    {
        foreach ($offers as $offer) {
            if ($offer['data']['product_page_import_fee'] ?? null) {
                return true;
            }
        }
        return false;
    }

    private function extractOffersData($checkedInStockOrUncheckedOffers)
    {
        $finalPrices = [];
        $uncheckedPrices = [];
        $checked = [];
        $unchecked = [];
        foreach ($checkedInStockOrUncheckedOffers as $key => $offer) {
            if (isset($offer['data'])) {
                // offer was checked.
                $finalPrice = $this->getFinalPrice($offer['data']);
                $finalPrices[$key] = $finalPrice;
                $checked[$key] = $offer;
            } else {
                $unchecked[$key] = $offer;
                $uncheckedPrices[$key] = $offer['offer_page_price'];
            }
        }
        return [$finalPrices, $uncheckedPrices, $checked, $unchecked];
    }

    public function getFinalPrice($data)
    {
        $importFee = $data['product_page_import_fee'] ?? 0;
        $price = $data['product_page_price'] ?? 0;
        return $price + $importFee;
    }

    public function getProperOffer($offersToConsider)
    {
        [$finalPrices, $uncheckedPrices, $checked, $unchecked] = $this->extractOffersData($offersToConsider);
        if (!$checked) {
            return null;
        }
        arsort($finalPrices);
        end($finalPrices);
        $properOfferKey = key($finalPrices);
        $this->addMessage('taking offer after price comparison'. $properOfferKey);
        return $checked[$properOfferKey]['data'];
    }
}
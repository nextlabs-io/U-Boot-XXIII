<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 05.10.2020
 * Time: 16:55
 */

namespace Parser\Model\Amazon\Category;


use Parser\Model\Helper\Helper;
use Parser\Model\SimpleObject;

/**
 * get contents of the amazon category page and extracts all links of the certain filter selector
 * Class CategoryFilterSelector
 * @package Parser\Model\Amazon\Category
 */
class CategoryFilterSelector extends SimpleObject
{
    public static $querySelector = 'rh';
    public static $blockPath = '//div[@id="filters"]/div//span[contains(@class, "a-text-bold")]';
    public $domain;
    public $xpath;
    public $dom;
    public $clearUrl;
    public $defaultSelection1;
    public $defaultSelection2;
    public $defaultSelection;
    /**
     * @var array|false|int|string|null
     */
    private $defaultSelectionRh;
    /**
     * @var array
     */
    private $totalItems;

    public function __construct($content, $domain = 'https://www.amazon.ca')
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($content);
        $xpath = $this->xpath = new \DOMXPath($dom);
        $this->domain = $domain;

    }

    /**
     * @param array $selectorTitle
     * @return array
     */
    public function process($selectorTitle =  ['Phone Compatibility', 'Cell Phone Compatibility']): array
    {

        $blockId = $this->checkMarker($selectorTitle);
        if (!$blockId) {
            return [];
        }
        $blockDataId = str_replace('-title', '', $blockId);
        $ulsDataPath = '//div[@id="filters"]/ul';
        $titlePath = 'li//a';
        $list = [];
        $ulData = $this->xpath->query($ulsDataPath);
        if ($ulData && count($ulData)) {
            foreach ($ulData as $element) {
                $title = $element->getAttribute('aria-labelledby');
                $titles = $this->xpath->query($titlePath, $element);
                $clearUrl = '';
                if ($title === $blockId) {
                    foreach ($titles as $k => $link) {
                        $list[$k]['link'] = $link->getAttribute('href');
                        $list[$k]['title'] = trim($link->textContent);
                        if ($list[$k]['title'] === 'Clear') {
                            $clearUrl = $list[$k]['link'];
                            $this->clearUrl = $clearUrl;
                            $clearUrlData = self::extractUrls($this->clearUrl, $this->domain);
                            $this->defaultSelectionRh = $clearUrlData[self::$querySelector] ?? [];
                            unset($list[$k]);
                        } else {
                            $def1Data = self::extractUrls($list[$k]['link'], $this->domain);
                            if ($def1Data[self::$querySelector] ?? null) {
                                foreach ($def1Data[self::$querySelector] as $elem) {
                                    if (strpos($elem, $blockDataId) !== false) {
                                        $item = str_replace($blockDataId . ':', '', $elem);
                                        $selectedItems = explode('|', $item);
                                        $this->totalItems[] = $selectedItems;
                                        $this->defaultSelection[count($selectedItems)][] = $selectedItems;
                                    }
                                }
                            }
                        }
                    }
                    ksort($this->defaultSelection);
                    if (count($this->defaultSelection) > 1) {
                        // we have something selected, so, we must filter selected items and substract them
                        $items = array_pop($this->defaultSelection);
                        $common = [];
                        foreach ($items as $item) {
                            if (!$common) {
                                $common = $item;
                            } else {
                                $common = array_intersect($common, $item);
                            }
                        }
                    } else {
                        $common = [];
                    }
                    $clearUrlData = $this->defaultSelectionRh;
                    $domain = $this->domain;
                    $list = array_map(static function ($v) use ($common, $blockDataId, $domain) {
                        $commonQty = count($common);
                        $urlData = self::extractUrls($v['link'], $domain);
                        $rh = $urlData['rh'] ?? [];
                        if ($rh) {
                            foreach ($rh as $key => $elem) {
                                if (strpos($elem, $blockDataId) !== false) {
                                    $item = str_replace($blockDataId . ':', '', $elem);
                                    $selectedItems = explode('|', $item);
                                    if (count($selectedItems) > $commonQty) {
                                        $itemId = array_diff($selectedItems, $common);
                                    } else {
                                        $itemId = array_diff($common, $selectedItems);
                                    }
                                    $rh[$key] = $blockDataId . ':' . implode('|', $itemId);
                                    $v['itemIds'] = $itemId;
                                }
                            }
                            $urlData[self::$querySelector] = $rh;
                        }
                        if ($v['itemIds'] ?? null) {
                            // found details
                            $v['message'] = 'success';
                            $v['resultLink'] = self::combineUrl($urlData);
                        } else {
                            $v['message'] = 'failed to find path';
                        }
                        return $v;
                    }, $list);
                }
            }
        }
        return $list;
    }

    public function checkMarker($selectorTitle = ['Phone Compatibility', 'Cell Phone Compatibility'])
    {
        $xpath = $this->xpath;
        $blockData = $xpath->query(self::$blockPath);
        //         `Phone Compatibility` here is a marker of the required filters section
        /**
         * DOMNodeList Object
         * (
         * [length] => 5
         * )
         *
         * Phone Compatibility
         * From Our Brands
         * Condition
         * Seller
         * Availability
         */
        $blockId = '';
        $blockDataId = '';
        if ($blockData && count($blockData)) {
            foreach ($blockData as $element) {
                if (in_array($element->textContent, $selectorTitle)) {
                    $blockId = $element->parentNode->getAttribute('id');
//                    $blockDataId = str_replace('-title', '', $blockId);
                    break;
                }
            }
        }
        if (!$blockId) {
            $this->addMessage('no block found');
            return false;
        }
        return $blockId;
    }

    public static function extractUrls($link, $domain)
    {
        $link = $domain . $link;
        $data = parse_url($link);
        parse_str($data['query'], $data['params']);
        unset($data['params']['qid'], $data['params']['ref']);
        if ($data['params'][self::$querySelector] ?? null) {
            $data[self::$querySelector] = explode(',', $data['params'][self::$querySelector]);
        }
        return $data;
    }

    public static function combineUrl($data)
    {
        if ($data[self::$querySelector] ?? null) {
            $data['params'][self::$querySelector] = implode(',', $data[self::$querySelector]);
        }
        if ($data['params'] ?? null) {
            $data['query'] = http_build_query($data['params']);
        }
        $link = $data['scheme'] . '://' . $data['host'];
        if ($data['path'] ?? null) {
            $link .= $data['path'];
        }
        if ($data['query'] ?? null) {
            $link .= '?' . $data['query'];
        }
        return $link;
    }
}
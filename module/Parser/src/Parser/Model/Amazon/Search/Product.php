<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 20.07.18
 * Time: 13:36
 */

namespace Parser\Model\Amazon\Search;

use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;

/**
 * Class Product
 * @package Parser\Model\Amazon\Search
 */
class Product
{
    /* @var \Laminas\Db\Adapter\Adapter $db */
    public $db;
    /* @var Config $config */
    public $config;
    public $locale;
    public $parent;
    public $variationContent;
    public $variations = [];

    /**
     * Product constructor.
     * @param Config $config
     * @param String $locale
     */
    public function __construct(Config $config, $locale = "")
    {
        $this->config = $config;
        $this->locale = $locale;
        $this->db = $config->getDb();
    }

    public static function getStringVariationAttributes($data)
    {
        return serialize($data);
    }

    public static function getVariationAttributesFromString($string)
    {
        return unserialize($string);
    }

    /**
     * @param $html
     * @return string
     * @throws \Exception
     */
    public function getParentAndVariations($html)
    {
        $data = [];

        $crawlConfig = $this->config->getCrawlConfig($this->locale);
        $content = $this->getVariationContent($html);
        $this->variationContent = $content;
        $json = Helper::JsonDecode($content);
        $parent = $this->checkParent($json);
        if ($parent) {
            $this->parent = $parent;
            $list = $this->extractVariations($json);
            if (count($list)) {
                // check and add new asins, if they are exist change syncable to PreSync and set parent asin
                $this->variations = $list;
            }

        }
        return $this->parent;
    }

    /**
     * @param $html
     * @return bool|string
     * @throws \Exception
     */
    private function getVariationContent($html)
    {
        $crawlConfig = $this->config->getCrawlConfig($this->locale);

        if (isset($crawlConfig['product']['variationContainerStart'])) {
            $start = $crawlConfig['product']['variationContainerStart'];
            $end = $crawlConfig['product']['variationContainerEnd'];
            return Helper::getJsonObjectFromHtml($html, $start, $end);
        }
        return $html;
    }

    /**
     * @param $json \stdClass
     * @return bool|string
     * @throws \Exception
     */
    private function checkParent($json)
    {
        $crawlConfig = $this->config->getCrawlConfig($this->locale);
        if (isset($crawlConfig['product']['parentAsinTag']) && isset($json->{$crawlConfig['product']['parentAsinTag']})) {
            return $json->{$crawlConfig['product']['parentAsinTag']};
        }
        return false;
    }

    /**
     * @param $json \stdClass
     * @return array
     * @throws \Exception
     */

    private function extractVariations($json)
    {
        $crawlConfig = $this->config->getCrawlConfig($this->locale);
        $list = [];
        if (isset($json->dimensionValuesDisplayData)) {
            foreach ($json->dimensionValuesDisplayData as $asin => $data) {
                foreach ($data as $attr_code => $value) {
                    if (isset($json->dimensions[$attr_code])) {
                        $list[$asin][$json->dimensions[$attr_code]] = $value;
                    }
                }
            }
        }
        pr($list);
        return $list;
    }

    public function processPreSynced($html)
    {
        return [];
    }

    public function checkFilters($content)
    {
        // here we must decide if the product is good for us.
        return true;
    }
}
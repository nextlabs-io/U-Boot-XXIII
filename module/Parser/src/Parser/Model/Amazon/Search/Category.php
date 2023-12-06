<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 20.07.18
 * Time: 13:36
 */

namespace Parser\Model\Amazon\Search;


use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Magento\ProductToStore;
use Parser\Model\Product as Gen;
use Parser\Model\SimpleObject;
use Parser\Model\Web\Browser;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\Db\Sql\Where;

/**
 * Class Category
 * @package Parser\Model\Amazon\Search
 * class to crawl amazon categories for asins.
 */
class Category extends SimpleObject
{
    /**
     * @var array list of found asins
     */
    public $list = [];
    private $config;
    private $db;
    private $localeConfig;
    private $proxy;
    private $userAgent;
    private $locale;

    /**
     * Category constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = $config->getDb();

        $this->userAgent = new UserAgent($this->db);
        $this->proxy = new Proxy($this->db, $config);
        $this->proxy->loadAvailableProxy();

        $this->db = $this->proxy->getDb();

        if ($this->proxy->hasErrors()) {
            $this->loadErrors($this->proxy);
        }


    }

    public function getCategory($url, $syncStatus = ProductSyncable::SYNCABLE_PREFOUND, $magentoList = [],
                                $autoPaging = 1, $addOptions = [])
    {
        try {
            $this->getConfigByUrl($url);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return $this;
        }
        $urlData = parse_url($url);
        $urlDom = $urlData['scheme'] . '://' . $urlData['host'];

        $globalConfig = $this->config->getConfig();
        $browserConfig = $globalConfig['captcha'];
        $browserConfig['cookie_file'] = 'category_page_crawler';
        $browserConfig['data_dir'] = '/data/parser/cookie';
//        $browserConfig['debugMode'] = $this->config->getProperty('DebugMode');
//        $browserConfig['mode'] = 'developer';
        $browserConfig['timeout'] = 30;
        $browser = new Browser($url, $browserConfig, $this->proxy, $this->userAgent);



        $br = new Browser($url, $browserConfig, $this->proxy, $this->userAgent);
        $br->setGroup('category-page');
        $br->getAdvancedPage();
        $content = $br->getContent();
        $this->getAsinsFromCategory($content);
        if (isset($this->localeConfig['category']['maxCategoryPage'])
            && $this->localeConfig['category']['maxCategoryPage']) {
            $maxCategoryPage = $this->localeConfig['category']['maxCategoryPage'];
        } else {
            $maxCategoryPage = 100;
        }
        if (count($this->list)) {
            $i = 0;
            if ($autoPaging) {
                while ($url = $this->getNextPageUrl($content, $urlDom)) {
                    $i++;
                    $br->getAdvancedPage($url);
                    $content = $br->getContent();
                    $this->getAsinsFromCategory($content);
                    if ($i > $maxCategoryPage) {
                        break;
                    }
                }
            }
            $product = new Gen($this->config, $this->proxy, $this->userAgent, "aaa", $this->locale);

            $product->addMessage(count($this->list) . ' Found for processing');
            $data = ['syncable' => $syncStatus];
            if ($addOptions) {
                $data = array_merge($data, $addOptions);
            }
            $product->addNewProducts($this->list, $data);

            // process magento store associations
            $where = new Where();
            $where->in('asin', $this->list);
            $where->equalTo('locale', $this->locale);
            ProductToStore::associateProducts($this->db, $where, $magentoList);

            // change sync status of existing products
            $product->updateList($where, ['syncable' => $syncStatus]);


            $this->appendMessagesFromObject($product, 1);
        }
        if (!count($this->list)) {
            $this->addMessage('Empty category');
        }
        return $this;
    }

    /**
     * @param $url
     * @return $this
     * @throws \Exception
     */

    public function getConfigByUrl($url)
    {
        $locale = $this->config->getLocaleByUrl($url);
        $this->locale = $locale;
        $this->localeConfig = $this->config->getCrawlConfig($locale);
        return $this;
    }

    /**
     * @param $content
     * @return bool
     */

    public function getAsinsFromCategory($content): bool
    {
        $productTag = $this->localeConfig['category']['productTag'];
        $asinTag = $this->localeConfig['category']['asinTag'];
        $res = Helper::getResourceByXpath($content, $productTag);
        if (count($res)) {
            foreach ($res as $element) {
                if ($asin = $element->getAttribute($asinTag)) {
                    if (Helper::validateAsin($asin)) {
                        $this->list[] = $asin;
                    }
                }
            }
            return true;
        }
        // no asins found
        return false;
    }

    /**
     * @param $content
     * @param $host
     * @return bool|string
     */

    public function getNextPageUrl($content, $host)
    {
        $url = '';
        $nextPage = $this->localeConfig['category']['pagingTag'];
        $nextPage2 = $this->localeConfig['category']['pagingTag2'] ?? null;
        $res = Helper::getResourceByXpath($content, $nextPage);
        if ($res->item(0)) {
            $url = $res->item(0)->getAttribute('href');
        } elseif ($nextPage2) {
            $res = Helper::getResourceByXpath($content, $nextPage2);
            if ($res->item(0)) {
                $url = $res->item(0)->getAttribute('href');
            }
        }
        if ($url) {
            return $host . $url;
        } else {
            return false;
        }
    }
}
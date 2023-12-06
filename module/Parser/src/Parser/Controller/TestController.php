<?php /** @noinspection PhpUnused */

/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 08.06.2020
 * Time: 20:16
 */

namespace Parser\Controller;

use Parser\Model\Amazon\Attributes\FastTrack;
use Parser\Model\Amazon\Category;
use Parser\Model\Amazon\CategoryPage;
use Parser\Model\Amazon\Centric\CentricAPI;
use Parser\Model\Amazon\Centric\ProductApiResponse;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\TablePageLogger;
use Parser\Model\Product;
use Parser\Model\ProductDetails;
use Parser\Model\ProductSync;
use Parser\Model\Telegram\TelegramBot;
use Parser\Model\Web\Browser;
use Parser\Model\Web\PhantomBrowser;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\PuppeteerBrowser;
use Parser\Model\Web\SeleniumBrowser;
use Parser\Model\Web\UserAgent;
use Parser\Model\Web\WebClient;
use Parser\Model\Web\WebPage;
use yii\log\Logger;
use Laminas\Db\Sql\Predicate\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\View\Model\ViewModel;
use Laminas\Console\Request as ConsoleRequest;

class TestController extends AbstractController
{
    public $proxy;
    public $userAgent;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->proxy = new Proxy($this->config->getDb(), $this->config);
        $this->userAgent = new UserAgent($this->config->getDb());

        $this->authActions = [];
    }

    public function indexAction()
    {
        echo 'a!';
        return $this->returnZeroTemplate();
    }

    public function loadProxyAction(): ViewModel
    {
        die();
        $file = 'data/parser/config/proxy.txt';
        /**
         * @var $proxy Proxy
         */
        $proxy = $this->proxy;
        $data = $proxy->uploadProxyListFromFile($file);
        return new ViewModel([
            'data' => $data,
        ]);

    }

    public function fasttrackAction()
    {
//        $fString = 'Get it as soon as June 19 - July 6 when you choose Standard Shipping at checkout.';
//        $fString = 'Want it Tuesday, June 9? Order it in the next  and choose Two-Day Shipping at checkout.';
        $fString = 'Arrives:  Aug 28 - Sep 21 Fastest delivery: Aug 13 - 18';
        $fTrack = new FastTrack();
        $date = $fTrack->getDate($fString);
        $days = $fTrack->days;
        pr($date);
        pr($days);
        die();

        $sql = new Sql($this->config->getDb());
        $select = $sql->select('product')->columns(['fast_track', 'fast_track_to', 'fast_track_from', 'updated_date', 'product_id']);
        $where = new Where();
//        $where->isNull('fast_track_to');
        $where->isNotNull('fast_track');
        $select->order('next_update_date asc')->limit(40000)->where($where);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $product = new Product($this->config, $this->proxy, $this->userAgent, '', 'ca');
        $collection = $stmt->execute();
        while ($result = $collection->current()) {
            $fTrack = new FastTrack();
            $fast_track_to = $fTrack->getDate($result['fast_track']);
            if (!$fast_track_to) {
                $fast_track_to = 'failed to process';
            }
            $product->updateList(
                ['product_id' => [$result['product_id']]],
                ['fast_track_to' => $fast_track_to, 'fast_track_from' => date('M d', strtotime($result['updated_date']))]
            );

            $collection->next();
        }

        return $this->returnZeroTemplate([$date]);
    }

    public function testAmazonProductAction()
    {
        $locale = 'de';
        $flow = new \Parser\Model\Amazon\Product($this->config->getLocaleConfig($locale), $this->config->getDb());
//        $data = $flow->loadProductFromDb('B002E3KK74', 'de', ['data']);
//        pr($data);
        $apiKey = $this->config->getConfig('centric')['centricApiKey'] ?? null;
        $centric = new CentricAPI($this->config->getDb(), $this->config, $apiKey);
        $productListData = $centric->getAllProducts([], 23028);
//        pr($productListData);
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
                    $dataToSave = $centric->prepareProductDataForSave($item['attributes'], $locale);
                    $dataToSave['api_response'] = ProductApiResponse::FoundOnCentric;
                }
                $listToBulkProductInsertIntoAmazonProductTable[] = $flow->getArrayForBulkUpdate($asin, $locale, $dataToSave);
            }
            if (count($listToBulkProductInsertIntoAmazonProductTable)) {
                pr($listToBulkProductInsertIntoAmazonProductTable);
                $flow->bulkUpdate($listToBulkProductInsertIntoAmazonProductTable);
            }
        }
        return $this->returnZeroTemplate();
    }

    public function testBestbuyAction()
    {

        $url = 'https://www.bestbuy.ca/en-ca/category/iphone-11-pro-max-cases/15879042';
        $this->proxy->loadAvailableProxy();
        $browser = new Browser($url, $this->proxy, $this->userAgent, ['mode' => 'developer']);
        // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.
        $content = $browser->getAdvancedPage()->getContent();
        $path = ".//div[contains(@class,'x-productListItem')]//a/@href";
        $res = Helper::getResourceByXpath($content, $path);
        if (count($res)) {
            foreach ($res as $element) {
                pr($element);
            }
        }


        return $this->zeroTemplate();
    }

    public function testErrorLogAction()
    {
        Helper::logException(new \Exception('test exception'));
        return $this->zeroTemplate();
    }

    public function testDropAction()
    {

        $sql = new Sql($this->config->getDb());

        $query = "UPDATE product SET sync_flag = 1
WHERE sync_flag=0 AND next_update_date < NOW() and  LAST_INSERT_ID(product_id) order by next_update_date ASC LIMIT 1";


        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $stmt->setSql($query);
        $result = $stmt->execute();
        pr($result);
        pr($result->getGeneratedValue());

        return $this->zeroTemplate();
    }

    public function removeAction()
    {
        $ps = new ProductSync($this->config);
        pr($ps->removeRegistration(['product_id' => 846726]));
        return $this->zeroTemplate();
    }

    /*
        public function aAction()
        {
            $tables['products-and-offers-en_US_Cellular_Phones_RequIrednadOnly'] = [
                'BBYCat',
                'shop_sku',
                '_Title_BB_Category_Root_EN',
                '_Short_Description_BB_Category_Root_EN',
                '_Brand_Name_Category_Root_EN',
                '_Primary_UPC_Category_Root_EN',
                '_Model_Number_Category_Root_EN',
                '_Manufacturers_Part_Number_Category_Root_EN',
                '_Seller_Image_URL_Category_Root_EN',
                '_Long_Description_BB_Category_Root_EN',
                '_Title_BB_Category_Root_FR',
                '_Short_Description_BB_Category_Root_FR',
                '_Long_Description_BB_Category_Root_FR',
                '_Web_Hierarchy_Location_Category_Root_EN',
                '_CableLength_15742473_CAT_15742111_EN',
                '_CableLengthInches_15742515_CAT_15742111_EN',
                '_EndType2_15742746_CAT_15742111_EN',
                '_WhatsintheBox_4667_CAT_29000_EN',
                '_WhatsintheBox_4667_CAT_29000_FR',
                '_WirelessCarrier_32147_CAT_29000_EN',
                '_ProductCondition_5469157_CAT_29000_EN',
                '_Type_24735_CAT_30177_EN',
                '_Colour_5105_CAT_30177_EN',
                '_WhatsintheBox_4667_CAT_30177_EN',
                '_ChargerType_680058_CAT_32083_EN',
                'sku',
                'product-id',
                'product-id-type',
                'description',
                'internal-description',
                'price',
                'price-additional-info',
                'quantity',
                'min-quantity-alert',
                'state',
                'available-start-date',
                'available-end-date',
                'logistic-class',
                'discount-price',
                'discount-start-date',
                'discount-end-date',
                'update-delete',
                'manufacturer-warranty',
                'ehf-amount-ab',
                'ehf-amount-bc',
                'ehf-amount-mb',
                'ehf-amount-nb',
                'ehf-amount-nl',
                'ehf-amount-ns',
                'ehf-amount-nt',
                'ehf-amount-nu',
                'ehf-amount-on',
                'ehf-amount-pe',
                'ehf-amount-qc',
                'ehf-amount-sk',
                'ehf-amount-yt',
                'pim',
            ];
            $tables['products-and-offers-en_US_Cellular_Phones_RequIrednadAMandatory'] = [
                'BBYCat', 'shop_sku', '_Title_BB_Category_Root_EN', '_Short_Description_BB_Category_Root_EN', '_Brand_Name_Category_Root_EN', '_Primary_UPC_Category_Root_EN', '_Model_Number_Category_Root_EN', '_Manufacturers_Part_Number_Category_Root_EN', '_Seller_Image_URL_Category_Root_EN', '_Long_Description_BB_Category_Root_EN', '_Title_BB_Category_Root_FR', '_Short_Description_BB_Category_Root_FR', '_Long_Description_BB_Category_Root_FR', '_Web_Hierarchy_Location_Category_Root_EN', '_CableLength_15742473_CAT_15742111_EN', '_CableLengthInches_15742515_CAT_15742111_EN', '_EndType2_15742746_CAT_15742111_EN', '_WhatsintheBox_4667_CAT_29000_EN', '_WhatsintheBox_4667_CAT_29000_FR', '_WirelessCarrier_32147_CAT_29000_EN', '_ProductCondition_5469157_CAT_29000_EN', '_Type_24735_CAT_30177_EN', '_Colour_5105_CAT_30177_EN', '_WhatsintheBox_4667_CAT_30177_EN', '_ChargerType_680058_CAT_32083_EN', 'sku', 'product-id', 'product-id-type', 'description', 'internal-description', 'price', 'price-additional-info', 'quantity', 'min-quantity-alert', 'state', 'available-start-date', 'available-end-date', 'logistic-class', 'discount-price', 'discount-start-date', 'discount-end-date', 'update-delete', 'manufacturer-warranty', 'ehf-amount-ab', 'ehf-amount-bc', 'ehf-amount-mb', 'ehf-amount-nb', 'ehf-amount-nl', 'ehf-amount-ns', 'ehf-amount-nt', 'ehf-amount-nu', 'ehf-amount-on', 'ehf-amount-pe', 'ehf-amount-qc', 'ehf-amount-sk', 'ehf-amount-yt', 'pim'
            ];
            $fields = [
                'created',
                'asin',
                'locale',
                'BBYCat',
                'shop_sku',
                '_Title_BB_Category_Root_EN',
                '_Short_Description_BB_Category_Root_EN',
                '_Brand_Name_Category_Root_EN',
                '_Primary_UPC_Category_Root_EN',
                '_Model_Number_Category_Root_EN',
                '_Manufacturers_Part_Number_Category_Root_EN',
                '_Seller_Image_URL_Category_Root_EN',
                '_Type_24735_CAT_30177_EN',
                '_ChargerType_680058_CAT_32083_EN',
                '_Web_Hierarchy_Location_Category_Root_EN',
                'sku',
                'product-id',
                'product-id-type',
                'description',
                'internal-description',
                'price',
                'price-additional-info',
                'quantity',
                'min-quantity-alert',
                'state',
                'available-start-date',
                'available-end-date',
                'logistic-class',
                'discount-price',
                'discount-start-date',
                'discount-end-date',
                'update-delete',
                'manufacturer-warranty',
                'ehf-amount-ab',
                'ehf-amount-bc',
                'ehf-amount-mb',
                'ehf-amount-nb',
                'ehf-amount-nl',
                'ehf-amount-ns',
                'ehf-amount-nt',
                'ehf-amount-nu',
                'ehf-amount-on',
                'ehf-amount-pe',
                'ehf-amount-qc',
                'ehf-amount-sk',
                'ehf-amount-yt',
                'pim',

            ];
            pr(array_intersect($tables['products-and-offers-en_US_Cellular_Phones_RequIrednadOnly'], $fields));
            pr(array_diff($tables['products-and-offers-en_US_Cellular_Phones_RequIrednadOnly'], $fields));
            pr(array_diff($fields, $tables['products-and-offers-en_US_Cellular_Phones_RequIrednadOnly']));

    //        pr(array_intersect($tables['products-and-offers-en_US_Cellular_Phones_RequIrednadAMandatory'], $fields));
    //        pr(array_diff($tables['products-and-offers-en_US_Cellular_Phones_RequIrednadAMandatory'], $fields));
    //
    //        pr(array_intersect($tables['products-and-offers-en_US_Cellular_Phones_RequIrednadAMandatory'], $tables['products-and-offers-en_US_Cellular_Phones_RequIrednadOnly']));
    //        pr(array_diff($tables['products-and-offers-en_US_Cellular_Phones_RequIrednadAMandatory'], $tables['products-and-offers-en_US_Cellular_Phones_RequIrednadOnly']));


            return $this->zeroTemplate();
        }
    */
    public function testcurlAction()
    {
        $wp = new WebPage('https://web-experiment.info');

        $parts = parse_url('https://web-experiment.info');
        $host = isset($parts['host']) ? $parts['host'] : "";
        if ($host) {
            $header[] = "Host: " . $host;
        }
        $header[] = "Content-Type: text/html;charset=UTF-8";
        $header[] = "Accept-Language:en-US,en;q=0.5";
        $header[] = "Accept-Encoding: gzip";
        $header[] = "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8";
        $header[] = "Cache-Control:max-age=0";
        $header[] = "Connection:keep-alive";
        $wp->setProperty('Header', $header);
        $content = $wp->getContentFromWeb();
        pr($wp->resultHTML);
//        echo $wp->content;
        return $this->zeroTemplate();

    }

    public function phantomAction()
    {
        $url = 'https://www.amazon.ca/s?k=iphone%2BXR%2Bcases&i=electronics&rh=n%3A667823011&pf_rd_i=3379552011&pf_rd_m=A3DWYIK6Y9EEQB&pf_rd_p=afeaa7cc-fe8c-4767-90d5-74e18ff9cd09&pf_rd_p=afeaa7cc-fe8c-4767-90d5-74e18ff9cd09&pf_rd_r=RHHHWFAZF6VSRP518HXP&pf_rd_r=RHHHWFAZF6VSRP518HXP&pf_rd_s=merchandised-search-leftnav&pf_rd_t=101&ref=amb_link_11';
        $url = 'https://www.amazon.ca/iphone-XR-cases-Electronics/s?k=iphone%2BXR%2Bcases&rh=n%3A667823011';
        $this->proxy->loadAvailableProxy();
        $browser = new Browser($url, $this->proxy, $this->userAgent, []);
        // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.
        $browser->setPhantomFlag(1)->getAdvancedPage($url);
        $content = $browser->content;
//        $content = $browser->getPhantomPage($url);
        $path = ".//h1";
        $res = Helper::getFirstElementByXpath($content, $path);
        pr($res->nodeValue);

        pr(strlen($content));
        if (strlen($content) < 1000) {
            pr($content);
        }
        return $this->zeroTemplate();
    }

    public function fsAction()
    {
        $ip = '127.0.0.1';
        $port = '9051';
        $fp = fsockopen($ip, $port, $error_number, $err_string, 10);
        pr($fp);

        fclose($fp);
        return $this->zeroTemplate();
    }

    public function consoleScrapeAction()
    {
        $request = $this->getRequest();
        pr($request);
        die();
        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        if (!$request instanceof ConsoleRequest) {
            throw new \RuntimeException('You can only use this action from a console!');
        }
        $category = new Category($this->config);
        $categoryData = $category->scrape();

//        pr($categoryData);

        // Get user email from console and check if the user used --verbose or -v flag
        $categoryId = $request->getParam('categoryId');
        $verbose = $request->getParam('verbose') || $request->getParam('v');
        return 'finished scraping';
    }

    public function testUAAction()
    {
        $this->userAgent->getUserAgent();
        pr($this->userAgent->getProperties());
        return $this->zeroTemplate();
    }

    public function testUrlAction()
    {
        $url = "https://www.amazon.ca/s?i=electronics&bbn=13542515011&rh=n%3A667823011%2Cn%3A677211011%2Cn%3A3379552011%2Cn%3A3379553011%2Cn%3A3379560011%2Cn%3A13542515011%2Cp_n_feature_nine_browse-bin%3A21219659011%7C21219663011%7C21219664011%7C21219667011%7C21219668011%7C21219673011%2Cp_85%3A5690392011%2Cp_72%3A11192167011&dc&fst=as%3Aoff&qid=1601421121&rnid=8884049011&ref=sr_nr_p_n_feature_nine_browse-bin_7";

        $data = parse_url($url);
        $data['urldecoded'] = urldecode($data['query']);
        $url = $data['scheme'] . "://" . $data['host'] . $data['path'];
        if ($data['query']) {
            $url .= '?' . $data['urldecoded'];
        }

        pr($data);
        pr($url);

        return $this->zeroTemplate();
    }

    public function parserAction()
    {
        $content = file_get_contents('data/log/test.html');
        $logger = new \Parser\Model\Helper\Logger($this->config->getDb(), []);
        $pd = new ProductDetails($logger);
        $result = $pd->extractFromUl($content, ".//*[@class='content']/ul/li");
        pr($result);

//        pr($content);
//        $phones = new Category\CategoryFilterSelector($content);
//        $list = $phones->process();
//        pr($list);


        return $this->zeroTemplate();
    }

    public function demoAction()
    {
        Helper::stripDomains('a');
        $cp = new CategoryPage($this->config->getDb());
        $data = $cp->getPageCandidate(1);
        pr($data);

        return $this->zeroTemplate();
    }

    public function showAmazonCategoryAction()
    {
        $category = new Category($this->config);
        $categoryId = $this->params()->fromQuery('id');

        $result = $category->select([$category->getTableKey() => $categoryId]);
        $data = $result->current();
        $json = $data['json'];
        $json = unserialize($json);
        $stats = $category->getPagesStats($json['pages']);
        pr($stats);
        pr($json);
        return $this->zeroTemplate();

    }

    public function discountAction()
    {
        $url = 'https://www.cdiscount.com/electromenager/aspirateurs-nettoyeurs/aspirateurs-balais/l-1101410.html#_his_';
        $this->proxy->loadAvailableProxy();
        $browserOptions = ['mode' => 'developer'];
        $browserOptions['phantomFlag'] = 1;
        $browserOptions['phantomBinary'] = '/usr/local/bin/phantomjs';
        //$browserOptions['debugMode'] = 1;
        $browser = new Browser($url, $this->proxy, $this->userAgent, $browserOptions);
        // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.

        if (($browserOptions['phantomFlag'] ?? null) && ($browserOptions['phantomBinary'] ?? null)) {
            $browser->setPhantomFlag($browserOptions['phantomFlag']);
            $browser->setProperty('PhantomBinary', $browserOptions['phantomBinary']);
        }
        $browser->setProperty('UserAgentGroups', ['default']);

        $content = $browser->getAdvancedPage()->getContent();
        file_put_contents('data/log/discount.html', $content);


//        $res = Helper::getResourceByXpath($content, $path);
//        if (count($res)) {
//            foreach ($res as $element) {
//                pr($element);
//            }
//        }


        return $this->zeroTemplate();

    }

    /**
     * @throws \Exception
     */
    public function teleAction()
    {
        $telegramSettings = $this->config->getConfig('telegram');
        $bot = new TelegramBot($this->config, $telegramSettings['telegramBotName'], $telegramSettings['telegramBotKey']);
        $result = $bot->setWebHook('https://amazon-parser.web-experiment.info/aska/hookWeBot');
        pr($result);

        return $this->zeroTemplate();
    }


    public function telesendAction(){
        $telegramSettings = $this->config->getConfig('telegram');
        $bot = new TelegramBot($this->config, $telegramSettings['telegramBotName'], $telegramSettings['telegramBotKey']);
        $chatId = $telegramSettings['telegramBotChatId'];
        $msg = '<b>????????? ??????????</b>
????? cnps7X LED+
<b>???? 1300 ???.</b>
<b>?????? ???? ?????? ???? 1500 ???.</b>
https://avito.ru/biysk/tovary_dlya_kompyutera/kuler_cnps7x_led_2035170122
';
        $result = $bot->sendMessage($msg, $chatId, 1);
        pr($result);

        return $this->zeroTemplate();

    }


    /**
     * @return ViewModel
     * @throws \Exception
     */
    public function hookWeBotAction() {
        $telegramSettings = $this->config->getConfig('telegram');
        $bot = new TelegramBot($this->config, $telegramSettings['telegramBotName'], $telegramSettings['telegramBotKey']);

        $bot->handleHook();
        return $this->zeroTemplate();
    }

    public function urlAction(){
        $url = 'https://www.amazon.ca/dp/B07YSBY45M/ref=olp_product_details?_encoding=UTF8&th=1&psc=1&smid=A28HU4HO6DHBQB';
        $this->proxy->loadAvailableProxy();
        $browser = new Browser($url, $this->proxy, $this->userAgent, []);
        // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.
        $browser->getAdvancedPage($url);
        $content = $browser->content;
//        $content = $browser->getPhantomPage($url);
        pr($content);
        return $this->zeroTemplate();

    }

    public function blowAction()
    {
        $url = 'https://amazon-parser.web-experiment.info/blow.php?http=1';
//        $url = 'https://www.amazon.co.uk/dp/B00HNTDL70/ref=br_asw_pdt-4?ie=UTF8&psc=1&m=A3P5ROKL5A1OLE';
//        $this->proxy->setAllowedGroups(['torCa']);
        $this->proxy->loadAvailableProxy();
        $proxyUrl = $this->proxy->getProperty('ip');
        $proxyPort = $this->proxy->getProperty('port');
        $proxyTorPort = $this->proxy->getProperty('tor_auth_port');
        $proxyTorAuth = $this->proxy->getProperty('tor_auth');
        $browser = new Browser($url, $this->proxy, $this->userAgent, []);
        // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.
//        $client = new WebClient([]);
//        $client->resetTorProxy($proxyUrl, $proxyTorPort, $proxyTorAuth);
        $content = $browser->getAdvancedPage()->getContent();
        //$browser->setProperty('Referer', "https://amazon-parser.web-experiment.info/");
        //$content = ($content);
        pr('proxy');
        pr($proxyUrl . ':'. $proxyPort);
        echo "<pre><textarea style='width: 100%;height:100%;'>";
        echo $content;
        echo "</textarea>";

        return $this->zeroTemplate();

    }

    public function testSeleniumAction(){

        $url = 'https://amazon-parser.web-experiment.info/blow.php?http=1';
//        $url = 'https://www.cdiscount.com/electromenager/aspirateurs-nettoyeurs/aspirateurs-balais/l-1101410.html#_his_';
//        $url = 'https://www.cdiscount.com/jeux-pc-video-console/xbox-series-x/console-xbox-series-s-512-go-2eme-manette-xbox/f-1035201-bunxbsswhite.html#cm_sp=PA:4497997:3:BUNXBSSWHITE';
        $this->proxy->setAllowedGroups(['tor', 'torCA']);
        $this->proxy->loadAvailableProxy();
        $proxyHost = $this->proxy->getProperty('ip');
        $proxyPort = $this->proxy->getProperty('port');
        $proxyType = $proxyHost === '127.0.0.1' ? 'socks5' : 'html';

        if($proxyHost === '127.0.0.1') {
            $proxyTorPort = $this->proxy->getProperty('tor_auth_port');
            $proxyTorAuth = $this->proxy->getProperty('tor_auth');
            $client = new WebClient([]);
            $client->resetTorProxy($proxyHost, $proxyTorPort, $proxyTorAuth);
        }
        $chromeDriverPath = getcwd().'/phantom/chromedriver';
        $webDriver = new SeleniumBrowser('python', $chromeDriverPath, 'amazon');

        $content = $webDriver->getPage($url, 'ua', $proxyHost, $proxyPort, $proxyType);
        echo "<pre><textarea style='width: 100%;height:100%;'>";
        echo $content;
        echo "</textarea>";
        return $this->zeroTemplate();

    }

    public function testPhantomAction(){

        $url = 'https://amazon-parser.web-experiment.info/blow.php?http=1';
//        $url = 'https://www.cdiscount.com/electromenager/aspirateurs-nettoyeurs/aspirateurs-balais/l-1101410.html#_his_';
//        $url = 'https://www.cdiscount.com/jeux-pc-video-console/xbox-series-x/console-xbox-series-s-512-go-2eme-manette-xbox/f-1035201-bunxbsswhite.html#cm_sp=PA:4497997:3:BUNXBSSWHITE';
//        $this->proxy->setAllowedGroups(['scraperFree', 'scraperFreeCA']);
        $this->proxy->setAllowedGroups(['proxyrack']);
        $this->proxy->loadAvailableProxy();
        $proxyHost = $this->proxy->getProperty('ip');
        $proxyPort = $this->proxy->getProperty('port');
        $proxyType = $proxyHost === '127.0.0.1' ? 'none' : 'html';


        if($proxyHost === '127.0.0.1') {
            $proxyTorPort = $this->proxy->getProperty('tor_auth_port');
            $proxyTorAuth = $this->proxy->getProperty('tor_auth');
            $client = new WebClient([]);
            $client->resetTorProxy($proxyHost, $proxyTorPort, $proxyTorAuth);
        }
        $category = new Category($this->config);
        $phantomBinary = $category->getConfig('settings', 'phantomBinary');

        $webDriver = new PhantomBrowser($phantomBinary, 1);
        $this->userAgent->getUserAgent();
        $ua  = $this->userAgent->getProperty('value');
        $content = $webDriver->getPage($url, $ua, $proxyHost, $proxyPort, $proxyType);
        echo "<pre><textarea style='width: 100%;height:100%;'>";
        echo $content;
        echo "</textarea>";
        return $this->zeroTemplate();

    }

    public function testErrorAction(){
        $category = new Category($this->config);
        $content = 'registered content';
        $category->registerError('test', $content, 'parsing_profile');
        $content = $category->getRegisteredErrorContent('test', 'parsing_profile');
        pr($content);
        return $this->zeroTemplate();
    }

    public function testBrowsersAction(){
        // Make sure that we are running in a console and the user has not tricked our
        // application into running this action from a public web server.
        $request = $this->getRequest();
        if ($request instanceof ConsoleRequest) {
            $allowedProxyList = $request->getParam('proxy');

        } else{
            $allowedProxyList = $this->params()->fromQuery('proxy');
        }
        if($allowedProxyList){
            $allowedProxyList = explode(',', $allowedProxyList);
            $this->proxy->setAllowedGroups($allowedProxyList);
        }
        $url = 'https://amazon-parser.web-experiment.info/blow.php?http=1';
        $this->proxy->loadAvailableProxy();
        $proxyHost = trim($this->proxy->getProperty('ip'));
        $proxyPort = $this->proxy->getProperty('port');
        $proxyTypePhantom = ($proxyHost == '127.0.0.1') ? 'socks5' : 'http';
        pr($proxyTypePhantom);
        pr($proxyHost);

        if($proxyHost === '127.0.0.1') {
            $proxyTorPort = $this->proxy->getProperty('tor_auth_port');
            $proxyTorAuth = $this->proxy->getProperty('tor_auth');
            $client = new WebClient([]);
            $client->resetTorProxy($proxyHost, $proxyTorPort, $proxyTorAuth);
        }
        $this->userAgent->getUserAgent();
        $ua  = $this->userAgent->getProperty('value');
        $toShow = '';
        /*
         * phantom
         */
        pr('<b>pup</b>');
        $category = new Category($this->config);
//        $phantomBinary = $category->getConfig('settings', 'phantomBinary');
        $webDriver = new PuppeteerBrowser('node', 1);
        $content = $webDriver->getPage($url, $ua, $proxyHost, $proxyPort, $proxyTypePhantom);
        $toShow .= "pup \r\n". $content;

        /*
         * selenium
         */
        pr('<b>selenium</b>');
        $proxyTypeSelenium = $proxyHost == '127.0.0.1' ? 'socks5' : 'html';
        $chromeDriverPath = getcwd().'/phantom/chromedriver';
        $webDriver = new SeleniumBrowser('python3', $chromeDriverPath, 'avito');

        $content = $webDriver->getPage($url, 'ua', $proxyHost, $proxyPort, $proxyTypeSelenium);
        $toShow .= "\r\n selenium \r\n". $content;

        /*
         * selenium firefox
         */
//        pr('<b>selenium firefox</b>');
//        $proxyHost = trim($proxyHost);
//        $proxyTypeSelenium = ($proxyHost === '?127.0.0.1') ? 'socks5' : 'none';
//        $driverPath = getcwd().'/phantom/geckodriver';
//        $webDriver = new SeleniumBrowser('/usr/bin/python3', $driverPath, 'cdiscount_firefox');
//
//        $content = $webDriver->getPage($url, 'ua', $proxyHost, $proxyPort, $proxyTypeSelenium);
//        $toShow .= "\r\n selenium firefox \r\n". $content;

        /*
         * curl
         */
        pr('<b>curl</b>');
        $browser = new Browser($url, $this->proxy, $this->userAgent, []);
        $content = $browser->getAdvancedPage()->getContent();
        $toShow .= "\r\n curl \r\n".$content;
        echo "<pre><textarea style='width: 100%;height:100%;'>";
        echo $toShow;
        echo "</textarea>";

        return $this->zeroTemplate();

    }
    public function testCatFixAction(){
        $cat = new Category($this->config);
        $cat->fixInProgressHangingItems();
        pr("a");
        return $this->zeroTemplate();

    }
    public function testEventLoggerAction(){
        $eventLogger =  new TablePageLogger('amazon_category_page',  $this->config->getDb());
        $eventLogger->addEvent(10, 1, 1, 'category page sync with phantom');
        pr('finished');
        return $this->zeroTemplate();
    }

    public function checkJsonAction(){
        $file = file_get_contents('phantom/pup.json');
        $json = json_decode($file);
        pr($json);
        return $this->zeroTemplate();
    }

    public function quicktestAction(){
        $localeConfig = $this->config->getLocaleConfig('ca');
        pr($localeConfig);
        return $this->zeroTemplate();
    }


}

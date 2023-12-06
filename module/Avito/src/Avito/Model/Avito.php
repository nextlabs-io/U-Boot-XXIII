<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 17.09.2017
 * Time: 1:52
 */

namespace Avito\Model;

use Avito\Model\Telegram\NewMessageTemplate;
use Avito\Model\Telegram\OldMessageTemplate;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Helper\ProcessLimiter;
use Parser\Model\SimpleObject;
use Parser\Model\Telegram\TelegramBot;
use Parser\Model\Web\Browser;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Parser\Model\Web\WebClient;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Dom\DOMXPath;
use Laminas\Mail\Message as Message;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;

// todo refactor module to a default table page

class Avito extends SimpleObject
{
    // class to parse items on Avito and to get email notifications when new item with propper requirements comes out.
    private $proxy;
    private $userAgent;
    private $config;
    private $configPath = 'data/avito/';
    private $messages = [];
    private $db;
    private $emailSettings;

    /**
     * @var Config
     */
    private $globalConfig;

    public function __construct(Config $config)
    {
        $this->globalConfig = $config;
        /**
         * @var $proxy Proxy
         */
        $this->proxy = new Proxy($this->globalConfig->getDb(), $this->globalConfig);
        $this->userAgent = new UserAgent($this->globalConfig->getDb());
        $this->proxy->loadAvailableProxy();


        $configFile = $this->configPath . 'config.xml';

        if (!file_exists($configFile)) {
            // can not perform parsing without locale file.
            $this->addError('No locale config file found');
        } else {
            $this->config = Helper::loadConfig($configFile);
        }
        $this->proxy->loadAvailableProxy();
        $this->db = $this->proxy->getDb();

        if ($this->proxy->hasErrors()) {
            $this->loadErrors($this->proxy);
        }
        $defaultEmailArray = ['name' => '', 'host' => '', 'username' => '', 'password' => '', 'port' => '', 'sendEmail' => ''];
        if (isset($this->config['email'])) {
            $defaultEmailArray = array_merge($defaultEmailArray, $this->config['email']);
        }
        $this->emailSettings = (object)$defaultEmailArray;
    }

    public static function _getContentFromHTMLbyXpath($html, $path)
    {
        $res = self::getResourceByXpath($html, $path);
        return self::_getContentFromElement($res, '%s');
    }

    public static function getResourceByXpath($html, $path, $context = null)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        return $xpath->query($path, $context);
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

    public static function extractField(&$data = [], $html, $path, $fieldName, $attribute = null, $context = null)
    {
        $res = self::getResourceByXpath($html, $path, $context);
        if ($res->length) {
            foreach ($res as $key => $item) {
                $val = self::extendedTrim($attribute ? $item->getAttribute($attribute) : $item->textContent);

                $data[$key][$fieldName] = $attribute == 'href' ? 'https://avito.ru' . $val : $val;
            }
        }
        foreach ($data as $key => $item) {
            if (!isset($data[$key][$fieldName])) {
                $data[$key][$fieldName] = 'non';
            }
        }
        return $data;

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
        $newString = implode(' ', $newData);
        return $newString;
    }

    public function getOffers(ProcessLimiter $limiter)
    {
        $offers = $this->config['settings']['offers']['offer'] ?? [];
        if (!isset($offers[0])) {
            $offers = [0 => $offers];
        }
        $data = [];
        if (is_array($offers) && count($offers)) {
            foreach ($offers as $url) {
                if (isset($url['email'])) {
                    $data[$url['email']][] = $this->getOfferPage($url);
                    $limiter->touchProcess();
//                    $this->proxy->loadAvailableProxy();
                    sleep(random_int(1, 2));
                }
            }
        }

        return $data;
    }

    public function getOfferPage($url)
    {
        // get offer page, parse offers, save to db and email new ones.
        $to = $url['email'];
        $tag = $url['tag'] ?? 'default';
        $botChatId = $url['telegramChatId'] ?? $this->config['settings']['telegramBotChatId'] ?? null;
        $url = trim($url['_']);
        $baseUrl = $this->config['settings']['baseUrl'];
        if ($this->config['proxy']['maxRetries'] ?? null) {
            $this->proxy->maxRetries = $this->config['proxy']['maxRetries'];
        }
        if ($this->config['proxy']['maxProxyRetries'] ?? null) {
            $this->proxy->maxProxyRetries = $this->config['proxy']['maxProxyRetries'];
        }

        $offerUrl = $baseUrl . $url;
        pr('getting this url ' . $offerUrl);
        // proxy and user agent options are sensitive to errors.
        $browserData = [];
        $browserConfig['cookie_file'] = 'avito_' . md5($url) . '_cookie';
        $browserConfig['data_dir'] = '/data/avito';
        // enable tor avito proxy
//        $where = new Where();
//        $where->in('group', ['torAvito']);
//        $data = ['enabled' => true, 'active' => true];
//        Proxy::staticUpdate($this->db, $data, $where);

//        $this->proxy->setAllowedGroups(['torAvito']);
        $this->proxy->loadAvailableProxy();


        $proxyHost = trim($this->proxy->getProperty('ip'));
        if (($proxyTorAuth = $this->proxy->getProperty('tor_auth')) && ($proxyTorPort = $this->proxy->getProperty('tor_auth_port'))) {
            $client = new WebClient([]);
            $client->resetTorProxy($proxyHost, $proxyTorPort, $proxyTorAuth);
            sleep(10);
        }

        $browser = new Browser($offerUrl, $browserConfig, $this->proxy, $this->userAgent, ['mode' => 'live']);
        $mode = 'live';
        if ($mode === 'test' && is_file('data/avito/' . md5($url) . '.txt')) {
            echo md5($url);
            $content = file_get_contents('data/avito/' . md5($url) . '.txt');
        } else {
            // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.
            $browser->generateHeader([
                'Accept-Language' => 'ru-RU,ru;q=0.8',
            ]);
            $browser->setPuppeteerFlag(1);
            $executableScript = 'avito.ts';
            $browser->setProperty('PuppeteerExecutableScript', $executableScript);
            $browser->setProperty('PuppeteerBinary', 'node');
            $browser->setProperty('PuppeteerDevice', 'iphone6');

//            $browser->mode = 'developer';
//            $browser->debugMode = true;

            $contantMarkers = [
                ['code' => 0, 'function' => 'strlen', 'size' => '1500'],
                ['code' => 503, 'function' => 'strpos', 'pattern' => 'Доступ с вашего IP-адреса временно ограничен'],
// no need to catch captcha, it is cought by default for amazn, but if you set it here - it will not attemt to solve it, if set to solve
//            ['code' => 505, 'function' => 'strpos', 'pattern' => 'Type the characters you see in this image'],
            ];
            $browser->contentMarker = new Browser\ContentMarker($contantMarkers);

//            $browserOptions['phantomFlag'] = 1;
//            $browserOptions['phantomBinary'] = '/usr/local/bin/phantomjs';
//            if (($browserOptions['phantomFlag'] ?? null) && ($browserOptions['phantomBinary'] ?? null)) {
//                $browser->setPhantomFlag($browserOptions['phantomFlag']);
//                $browser->setProperty('PhantomBinary', $browserOptions['phantomBinary']);
//            }
            $browser->setProperty('ContentTag', 'avito_offer' . $tag);
            $browser->setTag($tag)
                ->setGroup('avito-offer')
                ->getAdvancedPage();

//            $cInfo = $browser->getProperty('CurlInfo');

            $content = $browser->getContent();

            if ($browser->code == '400') {
                // no such product
                $browser->addError('no product found');
            }
            file_put_contents('data/avito/' . md5($url) . '.txt', $content);
        }

//        $content = file_get_contents('data/parser/avito.txt');
        // data-item-id
        // need to switch logic.

        $itemsData = [];
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
//        @$dom->loadHTML($content);
        $xpath = new \DOMXPath($dom);
        // main container for items
        $itemDivpath = ".//div[@data-item-id]";

        $containerElements = $xpath->query($itemDivpath);

        $itemTitlePath2 = ".//*[@itemprop='name']";
        $itemTitlePath = ".//img[@itemprop='image']";
        $priceItemPath = ".//div[contains(@class, 'price')]/span[contains(@class, 'price')]";
        $itemLinkPath = ".//a[@itemprop='url']";
        $itemsData = [];
        if ($containerElements && count($containerElements)) {
            foreach ($containerElements as $containerElement) {
                // getting data related to each item
                $itemId = $containerElement->getAttribute('data-item-id');
                $title = $this->simpleFieldExtract($xpath, $itemTitlePath2, null, $containerElement);
                if (!$title) {
                    $title = $this->simpleFieldExtract($xpath, $itemTitlePath, 'alt', $containerElement);
                }
                $price = $this->simpleFieldExtract($xpath, $priceItemPath, null, $containerElement);
                $url = $this->simpleFieldExtract($xpath, $itemLinkPath, 'href', $containerElement);
//                pr($itemId);
//                pr($title);
//                pr($price);
//                pr($url);
                $itemsData[] = ['item_id' => $itemId, 'title' => $title, 'price' => $price, 'link' => $url];
            }
        } else {
            // trying mobile profile
            $mobileContainer = ".//div[contains(@data-marker,'item-wrapper')]";
            $priceItemPath = ".//div[@itemprop='price']";
            $itemLinkPath = ".//a[@itemprop='url']";
            $itemTitlePath = ".//img[@data-marker='item/image']";
            $containerElements = $xpath->query($mobileContainer);

            if ($containerElements && count($containerElements)) {
                foreach ($containerElements as $containerElement) {
                    // getting data related to each item
                    $itemId = explode("item-wrapper(", $containerElement->getAttribute('data-marker'));
                    if (isset($itemId[1])) {
                        $itemId = (int)$itemId[1];
                    } else {
                        $itemId = null;
                    }
                    $title = $this->simpleFieldExtract($xpath, $itemLinkPath, '', $containerElement);
//                    $title = $this->simpleFieldExtract($xpath, $itemTitlePath, 'alt', $containerElement);

                    $price = $this->simpleFieldExtract($xpath, $priceItemPath, null, $containerElement);
                    $url = $this->simpleFieldExtract($xpath, $itemLinkPath, 'href', $containerElement);
//                    pr($itemId);
//                    pr($title);
//                    pr($price);
//                    pr($url);
                    if ($itemId) {
                        $itemsData[] = ['item_id' => $itemId, 'title' => $title, 'price' => $price, 'link' => $url];
                    }
                }
            }
        }


        $existItems = $this->select($offerUrl);
        $messages = [];
        if ($mode === 'test') {
            pr($itemsData);
//            return
//                [
//                    'errors' => $browser->getStringErrorMessages(),
//                    'data' => $itemsData,
//                ];
        }
        $first = 0;
        $newItems = [];
        $oldItems = [];
        foreach ($itemsData as $itemKey => $item) {
            if (!($item['item_id'] ?? null)) {
                unset($itemsData[$itemKey]);
                continue;
            }
            if (!($item['title'] ?? null)) {
                unset($itemsData[$itemKey]);
                continue;
            }
            $item['price'] = $item['price'] ?? 'non';
            $toShrink = ['₽', 'руб.', ' '];
            $item['price'] = str_replace($toShrink, '', $item['price']);
            $item['price'] = (float)$item['price'];
            if (isset($existItems[$item['item_id']])) {
                // item already in db, need to check price
                $existItem = $existItems[$item['item_id']];
                if (!$item['price'] || $existItem['price'] == $item['price']) {
                    // prices match or missing, no need to do anything
                } else {
                    // send notification email and update item in db
//                    $this->update($existItem['avito_id'], ['price' => $item['price'], 'title' => $item['title'], 'link' => $item['link']]);
                    $this->update($existItem['avito_id'], ['price' => $item['price']]);
                    $item['oldPrice'] = 'старая цена ' . $existItem['price'];
                    $messages['updated'][] = implode(" \r\n", $item);
                    $item['oldPrice'] = $existItem['price'];
                    $oldItems[] = $item;
                }

            } else {
                // item does not exist in db, new item,
                $item['offer'] = $offerUrl;
                $this->add($item);
                $messages['new'][] = implode(" \r\n", $item);
                $newItems[] = $item;
            }
        }
        if (count($messages)) {
            $botName = $this->config['settings']['telegramBotName'] ?? null;
            $botKey = $this->config['settings']['telegramBotKey'] ?? null;


            if ($botKey && $botName) {
                $bot = new TelegramBot($this->globalConfig, $botName, $botKey);
                if ($newItems) {
//                    $result = $bot->sendMessage('New items', $botChatId);
                    foreach ($newItems as $item) {
                        if ($item['title'] ?? null) {
                            $message = NewMessageTemplate::getMessage($item);
                            $result = $bot->sendMessage($message, $botChatId, 1);
                        }
                    }
                }
                if ($oldItems) {
                    foreach ($oldItems as $item) {
                        if ($item['title'] ?? null) {
                            $message = OldMessageTemplate::getMessage($item);
                            $result = $bot->sendMessage($message, $botChatId, 1);
                        }
                    }
                }
            }
            $body = '';
            if (isset($messages['new'])) {

                $body .= "New Items \r\n";
                $body .= implode("\r\n ---------------\r\n", $messages['new']);
                $body .= "\r\n\r\n";
            }
            if (isset($messages['updated'])) {
                $body .= "Updated Items \r\n";
                $body .= implode("\r\n ---------------\r\n", $messages['updated']);
                $body .= "\r\n\r\n";
            }
            $this->sendMessage($body, 'Новости с авито', $to, $this->emailSettings);

        }

        $data = [
            'errors' => $browser->getStringErrorMessages(),
            'data' => $itemsData,
        ];

        return $data;
    }

    public function simpleFieldExtract(\DOMXPath $xpath, $path, $attribute = null, $context = null)
    {
        $res = $xpath->query($path, $context);
        if ($res->length) {
            foreach ($res as $key => $item) {
                $val = self::extendedTrim($attribute ? $item->getAttribute($attribute) : $item->textContent);
                return $attribute == 'href' ? 'https://avito.ru' . $val : $val;
            }
        }
        return '';
    }

    public function select($link)
    {
        $sql = new Sql($this->db);
        $select = $sql->select('avito')->where(['link_hash' => md5($link)]);
        $stmt = $sql->prepareStatementForSqlObject($select);

        $res = $stmt->execute();
        $list = [];

        if ($res->current()) {
            while ($res->current()) {
                $list[] = $res->current();
                $res->next();
            }
            // changing ids of items to the item id
            $newList = [];
            foreach ($list as $k => $v) {
                $newList[$v['item_id']] = $v;
            }
            $list = $newList;
        }
        return $list;
    }

    public function update($avito_id, $data)
    {
        $sql = new Sql($this->getDb());
        $update = $sql->update('avito')
            ->set($data)
            ->where(['avito_id' => $avito_id]);
        $stmt = $sql->prepareStatementForSqlObject($update);
        $result = $stmt->execute();

        return $result;
    }

    public function getDb()
    {
        return $this->db;
    }

    public function add($data)
    {
        $sql = new Sql($this->getDb());
        $insert = $sql->insert('avito')
            ->values([
                'link_hash' => md5($data['offer']),
                'item_id' => $data['item_id'],
                'link' => $data['link'],
                'title' => $data['title'],
                'price' => $data['price'],
            ]);
        $stmt = $sql->prepareStatementForSqlObject($insert);
        $result = $stmt->execute();

        return $result;
    }

    /**
     * @param           $msg
     * @param           $subject
     * @param           $to
     * @param \stdClass $emailSettings
     */
    public static function sendMessage($msg, $subject, $to, $emailSettings)
    {
        if (!$emailSettings->sendEmail) {
            return;
        }
        //pr($emailSettings);
        $message = new Message();
        $message->setEncoding('UTF-8');

        $text = new MimePart($msg);
        $text->type = 'text/plain; charset = UTF-8';

        $body = new MimeMessage();
        $body->setParts([$text]);
        $to = explode(',', $to);
        foreach ($to as $key => $item) {
            $to[$key] = trim($item);
        }
        $message->addTo($to)
            ->addFrom('webandpeople@gmail.com')
            ->setSubject($subject)
            ->setBody($body);
        $message->getHeaders()->setEncoding('UTF-8');
        $transport = new SmtpTransport();
        $ssl = '';
        if ($emailSettings->port == 587) {
            $ssl = 'tls';
        }
        if ($emailSettings->port == 465) {
            $ssl = 'ssl';
        }
        $options = new SmtpOptions([
            'name' => $emailSettings->name,
            'host' => $emailSettings->host,
            'connection_class' => 'plain',
            'port' => $emailSettings->port,
            'connection_config' => [
                'username' => $emailSettings->username,
                'password' => $emailSettings->password,
                'ssl' => $ssl,
            ],
        ]);
        $transport->setOptions($options);
        $transport->send($message);


    }

    public function extractFromUl($html, $path)
    {
        $res = self::getResourceByXpath($html, $path);
        $features = [];
        if ($res->length) {
            foreach ($res as $element) {
                $features[] = self::extendedTrim($element->nodeValue);
            }
        }
        return $features;
    }
}
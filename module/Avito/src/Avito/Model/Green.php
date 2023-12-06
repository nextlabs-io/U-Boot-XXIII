<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 20.07.18
 * Time: 20:43
 */

namespace Avito\Model;

use Parser\Model\Helper\Helper;
use Parser\Model\SimpleObject;
use Parser\Model\Web\Browser;
use Parser\Model\Web\Proxy;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class Green extends SimpleObject
{
    private $proxy;
    private $userAgent;
    private $config;
    private $configPath = 'data/avito/';
    private $messages = [];
    private $db;

    public function __construct($proxy, $userAgent)
    {
        /**
         * @var $proxy Proxy
         */
        $this->proxy = $proxy;
        $this->userAgent = $userAgent;

        $configFile = $this->configPath . "config.xml";

        if (! file_exists($configFile)) {
            // can not perform parsing without locale file.
            $this->addError("No locale config file found");
        } else {
            $this->config = Helper::loadConfig($configFile);
        }
        $this->proxy->loadAvailableProxy();

        $this->db = $this->proxy->getDb();

        if ($this->proxy->hasErrors()) {
            $this->loadErrors($this->proxy);
        }

    }

    public function processHtml($html)
    {
        $domain = "https://www.greenbook.org";
        $path = ".//article[contains(concat(' ', @class, ' '), 'article')]/a";
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($html);

        $xpath = new \DOMXPath($dom);
        $res = $xpath->query($path); // --- offers
        foreach ($res as $element) {
            $link = $element->getAttribute('href');
            if ($link) {
                $link = $domain . $link;
                $this->add(['url' => $link]);
            }
        }

    }

    public function add($data)
    {
        $sql = new Sql($this->getDb());
        $fields = ['url', 'data', 'email'];
        if (isset($data['url'])) {
            $toSave = [];
            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $toSave[$field] = $data[$field];
                }
            }
            $toSave['link_hash'] = md5($data['url']);

            $insert = $sql->insert('green')
                ->values($toSave);
            $stmt = $sql->prepareStatementForSqlObject($insert);
            try {
                $result = $stmt->execute();
            } catch (\Exception $e) {

            }
            return $result;
        }
        return false;
    }

    public function getDb()
    {
        return $this->db;
    }

    public function iterateUrls()
    {
        $i = 0;
        while ($i < 1000) {
            $sql = new Sql($this->getDb());
            $where = new Where();
            $where->isNull('email');
            $select = $sql->select('green')->where($where)->limit(1);
            $stmt = $sql->prepareStatementForSqlObject($select);
            $result = $stmt->execute();
            if ($item = $result->current()) {
                $i++;
                $url = $item['url'];
                $data = $this->processUrl($url);
                if (! isset($data['email']) || ! $data['email']) {
                    $data['email'] = 'failed';
                }

                if (strlen($data['email']) > 255) {
                    $data['email'] = substr($data['email'], 0, 254);
                }

                print_r($data['email'] . " " . $data['data'] . "\r\n");
//                print_r($data['email'] . "\r\n");

                $this->update($url, $data);

            } else {
                die('no more items to process');
            }
        }
    }

    public function processUrl($url)
    {
        //$url = "https://www.greenbook.org/company/SurveyMonkey-Audience";
        $browser = new Browser($url, ['data_dir' => 'data/parser/config/green'], $this->proxy, $this->userAgent);
        // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.

        $browser->setTag('green')->setGroup('green')->getAdvancedPage();
        $content = $browser->getContent();
        if ($browser->code == '400') {
            // no such item
            $browser->addError('no product found');
        }
        $name = strip_tags(Avito::_getContentFromHTMLbyXpath($content,
            ".//h2[contains(concat(' ', @itemprop, ' '), 'name')]"));
        $email = strip_tags(Avito::_getContentFromHTMLbyXpath($content,
            ".//a[contains(concat(' ', @itemprop, ' '), 'email')]"));
        $name = trim($name);
        $email = trim($email);
        return ['data' => $name, 'email' => $email];
    }

    public function update($url, $data)
    {
        $sql = new Sql($this->getDb());
        $update = $sql->update('green')
            ->set($data)
            ->where(['url' => $url]);
        $stmt = $sql->prepareStatementForSqlObject($update);
        $result = $stmt->execute();

        return $result;
    }

    public function getList()
    {
        $sql = new Sql($this->getDb());
        $where = new Where();
        $where->isNotNull('email');
        $select = $sql->select('green')->where($where);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();
        $list = "<pre>";
        while ($item = $result->current()) {
            $list .= $item['email'] . "\r\n";
//            $list .= $item['data'] . ";" . $item['email'] . "\r\n";
            $result->next();
        }
        return $list;

    }
}
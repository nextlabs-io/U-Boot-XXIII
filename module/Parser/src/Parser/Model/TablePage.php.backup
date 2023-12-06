<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 09.12.2018
 * Time: 13:09
 */

namespace Parser\Model;

use Parser\Model\Helper\Config;
use Parser\Model\Helper\Helper;
use Parser\Model\Web\Browser;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Dom\DOMXPath;

/**
 * Class TablePage declares a united Page and Table functionality. In other words - it is a page with some db representation
 * @package Parser\Model
 */
class TablePage extends TableGateway
{
    public $content;
    public $url;
    public $lastCallCode;
    public $fields;

    // general config settings, common for all project
    public $globalConfig;

    // specific config settings (like locale settings, etc)
    public $ignoreConfigFile;

    // object which does page scraping
    public $tag;
    public $group;
    protected $config;
    /**
     * @var Browser
     */
    protected $browser;
    /**
     * @var DOMXPath
     */
    protected $xpath;
    protected $proxy;
    protected $proxyStatus;
    protected $userAgent;
    /** @var array define the fields which has to be extracted from data sets */

    /**
     * TablePage constructor.
     * @param        $url
     * @param Config $globalConfig
     * @param        $tableName
     * @throws \Exception
     */
    public function __construct($url, Config $globalConfig, $tableName)
    {
        $this->url = $url;
        // looking for a default config file
        $configFile = 'data/' . strtolower($tableName) . '/config.xml';
        if (!$this->ignoreConfigFile) {
            if (!file_exists($configFile)) {
                // can not perform parsing without locale file.
                throw new \RuntimeException('no tablePage config file found ' . $tableName);
            } else {
                $this->config = Helper::loadConfig($configFile);
            }
        } else {
            $this->config = [];
        }
        $this->globalConfig = $globalConfig;
        $this->userAgent = new UserAgent($globalConfig->getDb());

        $this->proxy = new Proxy($globalConfig->getDb(), $globalConfig);
        $this->proxy->loadAvailableProxy();
        if ($this->proxy->hasErrors()) {
            // TODO handle proxy errors, mostly the information about proxy being not loaded.
            $this->proxyStatus = false;
        } else {
            $this->proxyStatus = true;
        }
        parent::__construct($tableName, $globalConfig->getDb());
    }

    /**
     * @param string $url
     * @param array $mode
     * @param array $header
     * @param array $browserConfig
     * @throws \Exception
     */
    public function getPage($url = '', $mode = [], $header = [], $browserConfig = [])
    {
        $url = $url ? trim($url) : $this->url;
        if (!isset($browserConfig['cookie_file'])) {
//            $browserConfig['cookie_file'] = $this->table . '-' . md5($url) . '-cookie';
        }
        $browser = new Browser($url, $browserConfig, $this->proxy, $this->userAgent, $mode);
        // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.
        if ($browserConfig['puppeteerFlag'] ?? null) {
            $browser->setPuppeteerFlag(1);
            $browser->setProperty('PuppeteerExecutableScript', $browserConfig['puppeteerExecutableScript']);
            $browser->setProperty('PuppeteerBinary', $browserConfig['puppeteerBinary']);
            $browser->setProperty('PuppeteerDevice', $browserConfig['puppeteerDevice'] ?? null);
        } elseif (($browserConfig['seleniumChromeFlag'] ?? null) && ($browserConfig['seleniumChromeBinary'] ?? null)) {
            $browser->setSeleniumChromeFlag($browserConfig['seleniumChromeFlag']);
            $executableScript = $browserConfig['seleniumChromeExecutable'] ?? 'cdiscount';
            $browser->setProperty('SeleniumChromeExecutableScript', $executableScript);
            $browser->setProperty('SeleniumChromeBinary', $browserConfig['seleniumChromeBinary']);
        } elseif (($browserConfig['phantomFlag'] ?? null) && ($browserConfig['phantomBinary'] ?? null)) {
            $browser->setPhantomFlag($browserConfig['phantomFlag']);
            $browser->setProperty('PhantomBinary', $browserConfig['phantomBinary']);
        }


        if ($browserConfig['UserAgentId'] ?? null) {
            $browser->setProperty('UserAgentId', $browserConfig['UserAgentId']);
        }
        if ($browserConfig['UserAgentGroups'] ?? null) {
            $browser->setProperty('UserAgentGroups', $browserConfig['UserAgentGroups']);
        }
        if ($browserConfig['ContentMarkers'] ?? null) {
            $browser->contentMarker = new Browser\ContentMarker($browserConfig['ContentMarkers']);
        }

        if ($browserConfig['proxyMaxRetries'] ?? null) {
            $browser->proxy->maxRetries = $browserConfig['proxyMaxRetries'];
        }
        if ($browserConfig['proxyMaxProxyRetries'] ?? null) {
            $browser->proxy->maxProxyRetries = $browserConfig['proxyMaxProxyRetries'];
        }

        $this->browser = $browser;
        if ($header) {
            $browser->generateHeader($header);
        }
        // group and tag gives possibility to filter scraping logs by tag and group.
        $browser->setGroup($this->getGroup())->setTag($this->getTag());
        $this->content = $browser->getAdvancedPage()->getContent();
        $this->lastCallCode = $browser->code;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param mixed $group
     * @return TablePage
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @param mixed $tag
     * @return TablePage
     */
    public function setTag($tag)
    {
        $this->tag = $tag;
        return $this;
    }

    public function getProxy()
    {
        return $this->proxy;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function getConfig($section = null, $field = null)
    {
        if ($section) {
            if ($field) {
                return $this->config[$section][$field] ?? null;
            }
            return $this->config[$section] ?? null;
        }
        return $this->config;
    }

    public function extractSingleField($html, $path, $attribute = null)
    {
        $this->extractField($data, $html, $path, 'field', $attribute);
        if (isset($data[0])) {
            return $data[0]['field'];
        }
        return '';
    }

    public function extractField(&$data = [], $html, $path, $fieldName, $attribute = null)
    {
        if (!$data) {
            $data = [];
        }
        $res = $this->getResourceByXpath($html, $path);
        pr($res->length);
        if ($res->length ?? null) {
            foreach ($res as $key => $item) {
                $val = self::extendedTrim($attribute ? $item->getAttribute($attribute) : $item->textContent);
                pr($val);
                $data[$key][$fieldName] = $val;
            }
        }
        foreach ($data as $key => $item) {
            if (!isset($data[$key][$fieldName])) {
                $data[$key][$fieldName] = 'non';
            }
        }
        return $data;
    }

    /**
     * @param $html
     * @param $path
     * @return \DOMNodeList
     */
    public function getResourceByXpath($html, $path)
    {
        if (!$path) {
            throw new \RuntimeException('empty xpath for extraction:');
        }
        if (!$this->xpath) {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            @$dom->loadHTML($html);
            $this->xpath = new \DOMXPath($dom);
        }
        return $this->xpath->query($path);
    }

    /**
     * @param $string
     * @return string
     */
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

    public function extractFieldWithContainer(&$data = [], $html, $containerPath, $path, $fieldName, $attribute = null)
    {
        if (!$data) {
            $data = [];
        }
        $res = $this->getResourceByXpath($html, $containerPath);
//        pr($res->length);
        if ($res->length ?? null) {
            foreach ($res as $key => $element) {
                $item = $this->xpath->query($path, $element);
                if ($item->length ?? null) {
                    foreach ($item as $key2 => $itemElement) {
                        $val = self::extendedTrim($attribute ? $itemElement->getAttribute($attribute) : $itemElement->textContent);
                        $data[$key . '_' . $key2][$fieldName] = $val;
//                        pr($val);
                    }
                }
            }
        }
        foreach ($data as $key => $item) {
            if (!isset($data[$key][$fieldName])) {
                $data[$key][$fieldName] = 'non';
            }
        }
        return $data;
    }

    public function _getContentFromHTMLbyXpath($html, $path)
    {
        $res = $this->getResourceByXpath($html, $path);
        return $this->_getContentFromElement($res, '%s');
    }

    /**
     * @param \DOMNodeList $res
     * @param              $htmlWrap
     * @return string
     */
    public function _getContentFromElement(\DOMNodeList $res, $htmlWrap)
    {
        $productDescription = '';
        $i = 0;
        $separator = '';
        if ($res->length) {
            foreach ($res as $element) {
                $xDoc = new \DOMDocument('1.0', 'UTF-8');
                $cloned = @$element->cloneNode(true);
                $xDoc->appendChild($xDoc->importNode($cloned, true));
                if ($i++) {
                    $separator = '';
                }
                $productDescription .= $separator . $xDoc->saveHTML();
            }
            $productDescription = sprintf($htmlWrap, $productDescription);
        }
        return $productDescription;
    }

    /**
     * @param $ids - unique ids - field or fields which will give a unique identification. for a set of items, which has to be created/updated
     * @param $data
     * @return $this
     */
    public function insertOrUpdate($ids, $data): self
    {
        $data = $this->processData($data);
        $result = $this->select($ids);
        if (!$result->current()) {
            $data['created'] = new Expression('NOW()');
            $data['updated'] = new Expression('NOW()');
            $this->insert($data);
        } else {
            $data['updated'] = new Expression('NOW()');
            $this->update($data, $ids);
        }
        return $this;
    }

    /**
     * Check array and remote elements which does not belong to the model
     * @param $data
     * @return mixed
     */
    public function processData($data)
    {
        foreach ($data as $key => $item) {
            if (!in_array($key, $this->fields)) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    public function resetXpath()
    {
        $this->xpath = null;
    }

    public function getDb()
    {
        return $this->getAdapter();
    }

    /**
     * @param $data
     * @param $where
     * @return int
     */
    public function updateUnprocessed($data, $where): int
    {
        $data = $this->processData($data);
        $data['updated'] = new Expression('NOW()');
        return $this->update($data, $where);
    }

    /**
     * @param $start string
     * @param $end string
     * @param $content string
     * @return string
     */
    public function extractPartialContent($start, $end, $content): string
    {
        $fieldsData = explode($start, $content);
        $fieldsData = $fieldsData[1];
        $fieldsData = explode($end, $fieldsData);
        $fieldsData = $fieldsData[0];
        return $start . $fieldsData . $end;
    }

    /**
     * @param Where|\Closure|string|array $condition
     * @param array $columns
     * @param null $order
     * @param null $limit
     * @param null $offset
     * @return array
     */
    public function getList($condition = [], $columns = [],
                            $order = null, $limit = null, $offset = null): array
    {
        $select = new Select(['l' => $this->getTable()]);
        if ($condition) {
            $select->where($condition);
        }
        if ($columns) {
            $select->columns($columns);
        }
        if ($order) {
            $select->order($order);
        }
        if ($limit) {
            $select->limit($limit);
        }
        if ($offset) {
            $select->offset($offset);
        }

        $rowSet = $this->selectWith($select);
        $data = [];
        while ($line = $rowSet->current()) {
            $data[] = (array)$line;
            $rowSet->next();
        }
        return $data;
    }
}
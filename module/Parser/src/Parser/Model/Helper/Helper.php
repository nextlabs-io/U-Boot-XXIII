<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 15.11.2017
 * Time: 21:49
 */

namespace Parser\Model\Helper;

use Parser\Model\Amazon\Product;
use Parser\Model\Configuration\ProductSyncable;
use Parser\Model\Magento\Request;
use Parser\Model\ProductSync;
use Parser\Model\Web\ProxyConnection;
use Parser\Model\Web\ProxySource\ProxyManager;
use Parser\Model\Web\UserAgent;
use phpDocumentor\Reflection\Types\Mixed_;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Session as SessionStorage;
use Laminas\Config\Reader\Xml;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Json\Json as ZJson;
use Laminas\Mail\Message as Message;
use Laminas\Mail\Transport\Smtp as SmtpTransport;
use Laminas\Mail\Transport\SmtpOptions;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;


class Helper
{
    public static function prepareListFilter($filter, $locales)
    {
        $fields = [
            'asin' => '',
            'sku' => '',
            'parent_asin' => '',
            'title' => '',
            'locale' => '',
            'syncable' => '-1',
            'sortUpdate' => '',
            'sortSync' => '',
            'sortId' => '',
            'page' => '',
            'fromPrice' => '',
            'fromModified' => '',
            'toModified' => '',
            'toPrice' => '',
            'fromStock' => '',
            'toStock' => '',
            'enabled' => '-1',
            'per-page' => 100,
            'sort_column' => 'modified',
            'sort_type' => 'desc',
        ];
        foreach ($fields as $key => $field) {
            if (!isset($filter[$key])) {
                $filter[$key] = $field;
            }
        }
        foreach ($locales as $key => $locale) {
            $locales[$key]['selected'] = $filter['locale'] === $locale['value'] ? 1 : 0;
        }
        $filter['locales'] = $locales;
        $activeList = ProductSyncable::getOptionsForSelect($filter['syncable']);
        $filter['activeList'] = $activeList;

        $enabledList = [
            ['value' => -1, 'title' => ''],
            ['value' => 1, 'title' => 'Yes'],
            ['value' => 0, 'title' => 'No'],
        ];

        foreach ($enabledList as $key => $item) {
            $enabledList[$key]['selected'] = $filter['enabled'] === $item['value'] ? 1 : 0;
        }
        $filter['enabledList'] = $enabledList;
        return $filter;
    }

    public static function getFloat($str, $config): ?float
    {
        $debug = false;
//        if(strpos($str, '499' ) !== false) {
//            $debug = true;
//        }
        $str = strip_tags($str);
        $skips = $config['priceSkip'] ?? '';
        if ($skips) {
            $skipList = explode(';', $skips);
            $skipList[] = ';';
            $str = str_replace($skipList, '', $str);
            $str = trim($str);
        }
        if ($debug) pr($str);
        $decPoint = $config['dec_point'] ?? '.';
        $separator = $config['thousands_sep'] ?? ',';
        if (isset($config['pricePrefix']) && $config['pricePrefix']) {
            $str = str_replace($config['pricePrefix'], '', $str);
        }
        if ($separator && false !== strpos($str, $separator)) {
            $str = str_replace($separator, '', $str); // replace thousands separator with ""
        }
        if ($decPoint !== '.' && strstr($str, $decPoint)) {
            $str = str_replace($decPoint, '.', $str); // replace dec point with .
        }

        if (preg_match("#([0-9\.]+)#", $str, $match)) { // search for number that may contain '.'
            return (float)$match[0];
        } else {
            return (float)$str; // take some last chances with floatval
        }

    }

    public static function checkIfTestModeOn($globalConfig)
    {
        return $globalConfig['settings']['testMode'] ?? false;
    }

    public static function compare($val1, $val2, $type): ?bool
    {
        // too complex logic TODO fix it somehow.
        if ($type == 'strlen_positive') {
            return (bool)strlen($val1) === (bool)strlen($val2);
        } elseif ($type == 'contains') {
            // return true if $val2 is in the $val1
            return strpos($val2, $val1) !== false;
        } else {
            return (int)$val1 === (int)$val2;
        }
    }

    public static function loadConfig($file, $extension = 'xml', $loadLocalFile = true)
    {
        if (!file_exists($file)) {
            // can not perform parsing without file.
            return [];
        }
        $localFile = str_replace('.' . $extension, '.local.' . $extension, $file);
        if ($extension === 'xml') {
            $xml = new Xml();
            $config = $xml->fromFile($file);
            if (file_exists($localFile) && $loadLocalFile) {
                $localConfig = $xml->fromFile($localFile);
                $config = array_replace_recursive($config, $localConfig);
            }
        } elseif ($extension === 'json') {
            $config = json_decode(file_get_contents($file), 1);
            if (file_exists($localFile) && $loadLocalFile) {
                $localConfig = json_decode(file_get_contents($localFile), 1);
                $config = array_replace_recursive($config, $localConfig);
            }
        } else {
            throw new \RuntimeException('no engine to load configs with extension ' . $extension);
        }
        return $config;
    }

    public static function saveConfig($data, $file, $extension = 'json')
    {
        $localFile = str_replace('.' . $extension, '.local.' . $extension, $file);
        if ($extension === 'json') {
            $json = json_encode($data);
            file_put_contents($file, $json);
        } else {
            throw new \RuntimeException('no engine to load configs with extension ' . $extension);
        }
    }


    /**
     * Simple logging function
     * @param        $message
     * @param string $file
     */
    public
    static function log($message, $file = '')
    {
        if (!$message) {
            return;
        }
        $file = $file ? 'data/parser/Log/' . $file : 'data/parser/Log/general.log';
        $logger = new \Laminas\Log\Logger();
        $writer = new \Laminas\Log\Writer\Stream($file);
        $logger->addWriter($writer);
        $logger->log(\Laminas\Log\Logger::DEBUG, $message);
        return;
    }

    /**
     * @param $string
     * @return null|string|string[]
     */
    public
    static function stripLinks($string)
    {
        if (!is_array($string)) {
            $string = preg_replace("/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/", '', $string);
            $string = self::stripDomains($string);
            return $string;
        } else {
            foreach ($string as $key => $sub) {
                $string[$key] = self::stripLinks($sub);
            }
            return $string;
        }

    }

    /**
     * @param $string
     * @return string|string[]|null
     */
    public
    static function stripDomains($string)
    {
        $charsToReplace = ['🏁', '★', '❤', '✅', '☞', '☺', '✔', '♥', '☀', '💕', '◆', '√', '✔', '♥', '🔁', '😍', '♪', '➤', '➡', '️', '💰', '💻', '🏆', '❀', '🔹', '👍', '▶', '📶', '📌', '✌', '♫', '🚀', '⚡', '🔥', '🌈', '📱', '🎃', '📖', '◙', '📢', '🎧', '📺'];
//        $stringToReplace = '✔♥🔁😍♪➤➡️💰💻🏆❀🔹👍▶📶📌 ✌♪♫🚀📶⚡🔥🌈📱🎃➤📖◙📢🎧📢📺';
//        $list = mb_str_split($stringToReplace);
//        $toShow = "'".implode("','", array_unique($list))."'";
//        pr($toShow);
//        $charsToReplace = array_merge()
        $string = preg_replace('/(\.[a-zA-Z]{2,4})(?![a-zA-Z])/', '$2', $string);
        $string = str_replace($charsToReplace, '', $string);
        return $string;
    }

    /**
     * Check if value is set in the array and positive
     * @param $array
     * @param $value
     * @return bool
     */
    public
    static function ifINN($array, $value)
    {
        return isset($array[$value]) && $array[$value];
    }

    /**
     * @param        $db
     * @param Config $config
     * @return bool
     */
    public
    static function cleanDb($db, Config $config): bool
    {
        $sql = new Sql($db);


        // check for proxy_connection which are not closed for more than 5 minutes.
        // delete proxy_connections data which are older than two days
        // enable/disable proxy accounts .
        pr('clean proxy connections ' . time());
        $pc = new ProxyConnection();
        $pc::updateStatistics($db, $config);

        // enable/disable proxy accounts .
        $data = $pc->getStats($db, [1]);
        $pm = new ProxyManager($config);
        $pm->checkProxies($data);


        // update user_agent usage stats
        pr('update ua stats ' . time());
        UserAgent::updateStatistics($db);

        // remove old events older than 14 days
        pr('clean old events ' . time());
        EventLogger::deleteOldEvents($db, 14);

        $request = new Request($db);
        pr('clean old requests ' . time());
        $request->deleteOldRequests();

        // remove old logs
        pr('clean logs ' . time());
        Logger::cleanLogs($db);
        \Parser\Model\Magento\Logger::cleanLogs($db);

        pr('clean products sync_flag ' . time());
        // checking for products which are still in sync state for more than a SYNC_DELAY
        $productSync = new ProductSync($config);
        $productSync->cleanOldRegistered();

        pr('finish ' . time());
        return true;
    }

    public
    static function saveContentToFile($url, $content): bool
    {
        return self::saveContentToFileByPath(md5($url), $content);
    }

    public
    static function saveContentToFileByPath($path, $content, $filePattern = 'data/content/{path}.html'): bool
    {
        $file = str_replace('{path}', $path, $filePattern);
        file_put_contents($file, $content);
        return true;
    }

    public
    static function getContentFromFile($url)
    {
        $path = md5($url);
        return self::getContentFromFileByPath($path);
    }

    public
    static function getContentFromFileByPath($path, $filePattern = 'data/content/{path}.html')
    {
        $file = str_replace('{path}', $path, $filePattern);
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return false;
    }

    public
    static function _getContentFromHTMLbyXpath($html, $path): string
    {
        $res = self::getResourceByXpath($html, $path);
        return self::_getContentFromElement($res, '%s');
    }

    /**
     * @param $html
     * @param $path
     * @return \DOMNodeList
     */
    public
    static function getResourceByXpath($html, $path): \DOMNodeList
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        return $xpath->query($path);
    }

    public
    static function _getContentFromElement($res, $htmlWrap): string
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

    /**
     * @param $html
     * @param $path
     * @return \DOMElement|null
     */
    public
    static function getFirstElementByXpath($html, $path)
    {
        $res = self::getResourceByXpath($html, $path);
        if ($res->item(0)) {
            return $res->item(0);
        } else {
            return null;
        }
    }

    public
    static function extractFromUl($html, $path)
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

    public
    static function extendedTrim($string)
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
        return trim($newString);
    }

    /**
     * @param              $adapter
     * @param Where|array $where
     * @param array|string $fieldString
     * @param string $fileName
     * @return array|string
     */
    public
    static function generateCSVContent($adapter, $where, $fileName, $fieldString = '')
    {
        if ('' !== $fieldString) {
            $fields = explode(',', $fieldString);
        } else {
            $fields = [];
        }
        $sql = new Sql($adapter);
        $columns = [];

        $select = $sql->select('product')
            ->where($where)
            ->limit(1);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();

        if ($item = $result->current()) {
            if (count($fields)) {
                // taking only requested fields
                foreach ($fields as $field) {
                    if (array_key_exists(trim($field), $item)) {
                        $columns[] = trim($field);
                    }
                }
            } else {
                // taking all available fields
                $columns = array_keys($item);
            }
        }
        if ($columns) {
            $columns = array_unique($columns);
            $newCol = [];
            foreach ($columns as $key => $field) {
                $newCol[$field] = $field;
            }
            $columns = $newCol;
        } else {
            // no columns specified or no items in the selection
            return false;
        }
        $fileName = str_replace('{date}', date('Y-m-d-H-i-s'), $fileName);
        $filePath = substr($fileName, strpos($fileName, '/'), strlen($fileName));
        $csvGen = new CsvGenerator($columns, ['delimiter' => ';']);
        $stream = false;

        $offset = 0;
        $limit = 1000;
        $select = $sql->select('product')
            ->where($where)
            ->columns($columns)
            ->limit($limit)
            ->offset($offset);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();

        $count = 0;
        // TODO select items with limit like 1000 products
        while (1) {
            // careful! infinite loop
            while ($item = $result->current()) {
                if (!$count) {
                    self::deleteOldDownloadFiles($fileName);

                    // initialize file and put a first line
                    $stream = fopen($fileName, 'wb');
                    if (!$stream) {
                        throw new \RuntimeException (sprintf('cannot open file %s, check folder permissions',
                            $fileName));
                    }
                    fwrite($stream, $csvGen->generateHeader());
                }
                fwrite($stream, $csvGen->renderLine($item));
                $count++;
                $result->next();
            }
            // getting another bunch of products
            $offset += $limit;
            $select = $sql->select('product')
                ->where($where)
                ->columns($columns)
                ->limit($limit)
                ->offset($offset);
            $stmt = $sql->prepareStatementForSqlObject($select);
            $result = $stmt->execute();
            if (!$result->current()) {
                break;
            }
        }
        if (isset($stream) && $stream) {
            fclose($stream);
            return $filePath;
        }
        return false;
    }

    public
    static function deleteOldDownloadFiles($fileName)
    {
        $xmlFile = pathinfo($fileName);
        if (isset($xmlFile['dirname'])) {
            $directory = $xmlFile['dirname'];
            $oldFiles = array_diff(scandir($directory, SCANDIR_SORT_NONE), ['..', '.']);
            foreach ($oldFiles as $oldFile) {
                $oldFilePath = $directory . '/' . $oldFile;
                $oldFileInfo = pathinfo($oldFilePath);
                $oldFileInfo['created'] = time() - filemtime($oldFilePath);
                if (isset($oldFileInfo['extension'])
                    && $oldFileInfo['extension'] === 'csv'
                    && $oldFileInfo['created'] > 86400) {
                    // delete file if it is 1 day old  and extension csv
                    @unlink($oldFilePath);
                }
            }
        }
    }

    /**
     * @param      $content
     * @param bool $strip
     * @return mixed
     * extracting json object which has some wrong character placemnets
     */

    public
    static function JsonDecode($content, $strip = false)
    {
        $content = str_replace('};', '}', $content);
        if ($strip) {
            $content = str_replace("'", '"', $content);
        }
        $pattern = "/,[\r\n\s]*]/";
        $replace = ']';
        $content = preg_replace($pattern, $replace, $content);
        $pattern = "/,[\r\n\s]*}/";
        $replace = '}';
        $content = preg_replace($pattern, $replace, $content);
        try {
            $json = ZJson::decode($content);
            return $json;
        } catch (\Laminas\Json\Exception\RuntimeException $e) {
//            pr($e->getMessage());
            return null;
        }
    }

    /**
     * @param $content
     * @param $startTag string of the beginning of the object
     * @param $endTag string which goes after the object ends
     * @return string
     */
    public
    static function getJsonObjectFromHtml($content, $startTag, $endTag)
    {
        //print_r($content);
        if (!strpos($content, $startTag) || !strpos($content, $endTag)) {
            return "";
        }
        $data = explode($startTag, $content);
        $data = explode($endTag, $data[1]);
        $content = $data[0];
        return trim($content);
    }

    public
    static function extractAsinsFromFile($fileName)
    {
        // remove bom
        $file = new \SplFileObject($fileName);

        $file->setFlags(\SplFileObject::READ_CSV);
        $asinList = [];
        foreach ($file as $key => $row) {
            if ($row[0] && $key == 0 && strpos('asin', strtolower($row[0])) !== false) {
                continue;
            }
            $asin = trim($row[0]);
            if ($key == 0) {
                // remove bom if it is there
                $bom = pack('H*', 'EFBBBF');
                $asin = preg_replace("/^$bom/", '', $asin);
            }
            if ($asin) {
                $asinList[] = ['asin' => $asin, 'sku' => isset($row[1]) ? trim($row[1]) : $asin];
            }
        }

        return $asinList;
    }

    public
    static function extractItemsFromFileWithTitleRow($fileName, $separator = ';')
    {
        // remove bom
        $file = new \SplFileObject($fileName);
        $file->setCsvControl($separator);
        $file->setFlags(\SplFileObject::READ_CSV);
        $dataKeys = [];
        $itemList = [];
        foreach ($file as $key => $row) {
            $string = trim($row[0]);
            if ($key === 0) {
                // remove bom if it is there
                $bom = pack('H*', 'EFBBBF');
                $string = preg_replace("/^$bom/", '', $string);
                $row[0] = $string;
            }

            if ($row[0] && $key === 0) {
                // first row is taken as keys;
                $dataKeys = array_map('trim', $row);
                $dataKeys = array_map('strtolower', $dataKeys);
                continue;
            }
            foreach ($row as $rowKey => $rowValue) {
                if (isset($dataKeys[$rowKey])) {
                    $itemList[$key][$dataKeys[$rowKey]] = $rowValue;
                }
            }
        }
        return $itemList;
    }

    public
    static function extractAsinsFromString($string)
    {
        $data = self::extractRegularelySeparatedItemsFromString($string);
        $list = [];
        if (count($data)) {
            foreach ($data as $item) {
                if (self::validateAsin($item)) {
                    $list[] = $item;
                }
            }
        }
        return $list;
    }

    /**
     * @param $string
     * @return array
     */
    public
    static function extractRegularelySeparatedItemsFromString($string): array
    {
        $list = [];
        if ($string) {
            $string = str_replace([',', ';', "\r", "\n"], ' ', $string);
            $data = explode(' ', $string);
            if (count($data)) {
                $list = array_map('trim', $data);
            }
        }
        return $list;
    }

    public
    static function validateAsin($asin)
    {
//        'B0001ILYAQ'
//        'B0001HZEG2'
        return strlen($asin) == '10' ? true : false;
    }

    public
    static function getAuth()
    {
        $auth = new AuthenticationService();
        $auth->setStorage(new SessionStorage('identitystorage'));
        return $auth;
    }

    /**
     * get some data statistscs, total products to sync, products in the queue
     * @param Config $config
     * @param bool $getSync24Data
     * @return array|mixed
     */
    public
    static function getProductStat(Config $config, $getSync24Data = true)
    {
        $db = $config->getDb();
        $sql = new Sql($db);
        $data = new \stdClass();
        $data->products = new \stdClass();
        $data->graph = new \stdClass();
        $data->products->beingSynced = 0;
        $data->products->total = 0;
        $data->products->active = 0;
        $data->products->queued = 0;
        $data->products->inStock = 0;
        $data->products->activeConnections = 0;
        $data->products->pendingConnections = 0;
        $data->products->activeThreads = 0;
        $data->products->pendingThreads = 0;
        $data->products->updatedPrice = 0;
        $data->products->updatedStock = 0;
        $data->graph->sync24h = [];
        //TODO 4 queries below might be performed at once, consider refactoring
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $query = 'SELECT COUNT(*) as qty FROM product';
        $stmt->setSql($query);
        $result = $stmt->execute();
        if ($res = $result->current()) {
            $data->products->total = $res['qty'];
        }
        $config->addTimeEvent('got total products');
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $syncableArray = [
            ProductSyncable::SYNCABLE_YES,
            ProductSyncable::SYNCABLE_PRESYNCED,
            ProductSyncable::SYNCABLE_PREFOUND,
        ];
        $syncableString = implode(',', $syncableArray);
        $query = 'SELECT COUNT(*) as qty, SUM(IF(`next_update_date` < NOW(), 1, 0)) as qty_queue, SUM(IF(`stock` <> 0, 1, 0)) as qty_stock FROM `product` WHERE `syncable` IN(' . $syncableString . ')';
        $stmt->setSql($query);
        $result1 = $stmt->execute();
        if ($res = $result1->current()) {
            $data->products->active = $res['qty'];
            $data->products->inStock = $res['qty_stock'];
            $data->products->queued = $res['qty_queue'];
        }
        $config->addTimeEvent('got active, in stock, queue products');

        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $query = 'SELECT COUNT(*) as qty FROM `product_sync`';
        $stmt->setSql($query);
        $result = $stmt->execute();
        if ($res = $result->current()) {
            $data->products->beingSynced = $res['qty'];
        }
        $config->addTimeEvent('got being synced atm');


        $eLogger = new EventLogger($db, []);
        $eLoggerStat = $eLogger->getStat();
        $config->addTimeEvent('got event logger stats');
        $data->products->synced = $eLoggerStat[EventLogger::PRODUCT_SYNC];
        $data->products->updatedPrice = $eLoggerStat[EventLogger::PRODUCT_PRICE_UPDATE];
        $data->products->updatedStock = $eLoggerStat[EventLogger::PRODUCT_STOCK_UPDATE];

        if ($getSync24Data) {
            $data->graph->sync24h = $eLogger->getGridData();
            $config->addTimeEvent('got grid data');
        }
//      old way to specify active connections
        $connections = ProxyConnection::getStatistics($db);
        $data->products->activeConnections = $connections['active'];
        $data->products->pendingConnections = $connections['pending'];
        // active and pending threads
        $threads = self::getLimiterThreads($db);
        $data->products->activeThreads = $threads['active'];
        $data->products->pendingThreads = $threads['pending'];

        $config->addTimeEvent('got proxy connection stats');
        return $data;
    }

    private
    static function getLimiterThreads(\Laminas\Db\Adapter\AdapterInterface $db)
    {
        $stats = [];
        $sql = new Sql($db);
        $select = $sql->select('process_limiter');
        $select->columns(['active' => new Expression('COUNT(*)'), 'pending' => new Expression('SUM(IF(`expire` < NOW(), 1, 0))')]);
        $stmt = $sql->prepareStatementForSqlObject($select);
        $result = $stmt->execute();

        $stats['active'] = 0;
        $stats['pending'] = 0;
        $data = $result->current();
        $stats = array_merge($stats, $data);
        $stats['pending'] = $stats['pending'] ?: 0;
        $stats['active'] = $stats['active'] ?: 0;
        return $stats;
    }

    /**
     * @param $db
     * @return array
     */
    public
    static function getProductSyncSpeed($db)
    {
        $sql = new Sql($db);
        $data = [];
        $stmt = $sql->getAdapter()->getDriver()->createStatement();
        $syncableArray = [
            ProductSyncable::SYNCABLE_YES,
            ProductSyncable::SYNCABLE_PRESYNCED,
            ProductSyncable::SYNCABLE_PREFOUND,
        ];
        $syncableString = implode(',', $syncableArray);
        $query = 'SELECT count(*) as qty, `sync_speed` FROM `product` WHERE `syncable` IN(' . $syncableString . ')  GROUP BY `sync_speed` ORDER BY `sync_speed` ASC';
        $stmt->setSql($query);
        $result = $stmt->execute();
        while ($res = $result->current()) {
            $data[$res['sync_speed']] = $res['qty'];
            $result->next();
        }

        $minSpeed = 1;
        $maxSpeed = count($data) ? max(array_keys($data)) : 1;
        for ($i = $minSpeed; $i < $maxSpeed; $i++) {
            if (!isset($data[$i])) {
                $data[$i] = 0;
            }
        }
        return $data;
    }

    public
    static function getColoredBoolean($item, $textYes = 'Yes', $textNo = 'No')
    {
        $color = $item ? 'green' : 'red';
        $text = $item ? $textYes : $textNo;
        return '<span class="' . $color . '">' . $text . '</span>';
    }

    /**
     * @param $images - string with image urls separated by |
     * @return string - image element or empty
     */
    public
    static function getFirstImagesFromProduct($images, $title = '')
    {
        $image = '';
        if ($images) {
            $images = explode('|', $images);
            if (count($images)) {
                $image = "<img alt=\"" . htmlspecialchars($title) . "\" src=\"" . $images[0] . "\" width=\"50px\" loading=\"lazy\" />";
            }
        }

        return $image;
    }

    public
    static function removeTableTag($tagLine, $content)
    {
        $content = explode("\n", $content);
        if (count($content)) {
            $skip = 0;
            $newContent = [];
            foreach ($content as $line) {
                if (strpos($line, $tagLine) !== false) {
                    // start skipping
                    $skip = 1;
                } else {
                    if ($skip && strpos($line, '<table') !== false) {
                        // we have met another table
                        $skip++;
                    } else {
                        if ($skip && strpos($line, '</table') !== false) {
                            // stop skipping
                            $skip--;
                        }
                    }
                }
                if ($skip) {
                    continue;
                }
                $newContent[] = $line;
            }
            $content = implode("\n", $newContent);
            return $content;
        }
        return '';
    }

    /**
     * @param           $msg
     * @param           $subject
     * @param           $to
     * @param \stdClass $emailSettings
     */
    public
    static function sendMessage($msg, $subject, $to, $emailSettings)
    {
        // TODO define from in config
        $from = "";
        $message = new Message();
        $message->setEncoding('UTF-8');

        $text = new MimePart($msg);
        $text->type = 'text/plain; charset = UTF-8';

        $body = new MimeMessage();
        $body->setParts([$text]);
        $to = explode(",", $to);
        foreach ($to as $key => $item) {
            $to[$key] = trim($item);
        }
        $message->addTo($to)
            ->addFrom($from)
            ->setSubject($subject)
            ->setBody($body);
        $message->getHeaders()->setEncoding('UTF-8');
        $transport = new SmtpTransport();

        $options = new SmtpOptions([
            'name' => $emailSettings->name,
            'host' => $emailSettings->host,
            'connection_class' => 'plain',
            'port' => $emailSettings->port,
            'connection_config' => [
                'username' => $emailSettings->username,
                'password' => $emailSettings->password,
                'ssl' => 'tls',
            ],
        ]);
        $transport->setOptions($options);
        $transport->send($message);

    }

    public
    static function resetTorProxy($ip, $port, $auth): bool
    {
        $command = 'signal NEWNYM';

        $fp = fsockopen($ip, $port, $error_number, $err_string, 10);
        if (!$fp) {
            return false;
        }

        fwrite($fp, "AUTHENTICATE \"" . $auth . "\"\n");
        fread($fp, 512);
        fwrite($fp, $command . "\n");
        fread($fp, 512);
        fclose($fp);
        return true;
    }

    public
    static function getAllStockStrings($db, $locale)
    {
        $sql = new Sql($db);
        $select = $sql->select('product')
            ->columns(['string' => 'StockString', 'qty' => new Expression('COUNT(*)')])
            ->group('StockString')->order(['qty desc'])->where(['locale' => $locale]);

        $stmt = $sql->prepareStatementForSqlObject($select);
        $list = $stmt->execute();
        $data = [];
        while ($item = $list->current()) {
            $data[] = $item;
            $list->next();
        }
        return $data;

    }

    public static function getAllDeliveryStrings($db, $locale)
    {
        $sql = new Sql($db);
        $select = $sql->select('product')
            ->columns(['string' => 'delivery', 'qty' => new Expression('COUNT(*)')])
            ->group('delivery')->order(['qty desc'])->where(['locale' => $locale]);

        $stmt = $sql->prepareStatementForSqlObject($select);
        $list = $stmt->execute();
        $data = [];
        while ($item = $list->current()) {
            $data[] = $item;
            $list->next();
        }
        return $data;

    }

    /**
     * @param \Exception $e
     * @param string $file
     */
    public
    static function logException(\Exception $e, $file = 'error.log'): void
    {
        $message = "\r\n - ======================\r\n" . $e->getMessage() . "\r\n";
        $message .= str_replace(getcwd() . '/', '', $e->getTraceAsString());
        $message .= date('Y-m-d H:i:s') . "\r\n - ======================\r\n";
        $file = getcwd() . '/data/log/' . $file;
        $dir = getcwd() . '/data/log/';
        if (!is_dir($dir) && !mkdir($dir) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', 'data/log/'));
        }
        $f = fopen($file, 'ab+');
        fwrite($f, $message);
        fclose($f);
    }

    /**
     * function tells if prices are very close
     * @param $attribute
     * @param $attribute1
     * @return bool
     */
    public
    static function comparePriceDelta($attribute, $attribute1): bool
    {
        if ($attribute >= 0 && $attribute1 >= 0) {
            // ex 50 and 51 will give 100* (1/101);
            $delta = (int)(100 * abs(($attribute1 - $attribute) / ($attribute1 + $attribute)));
            if ($delta > 3) {
                // prices different
                return true;
            }
            return false;
        }
        // prices different
        return true;
    }

    public
    static function getLinkFromNode($elem)
    {
        if (isset($elem->childNodes)) {
            foreach ($elem->childNodes as $child) {
                if ($child->nodeName === 'a') {
                    return $child;
                }
            }
        }
        return null;
    }

    public
    static function routeGetParseMode($asin)
    {
        // we can get B004HJ6V1W_1_1599
        if (strpos($asin, '_') !== false) {
            $data = explode('_', $asin);
            if (count($data) === 2) {
                $data[] = null;
            } else {
                $data[2] = ((int)$data[2]) / 100;
            }
            return $data;
        }
        return [$asin, null, null];
    }

    public
    static function routeMatchParseMode($mode, $locale)
    {
        // we get int 1,2,3
        $set = [1 => ['parseOffers' => 0],
            2 => ['parseOffers' => 1, $locale . '_primeTag' => 1],
            3 => ['parseOffers' => 1, $locale . '_primeTag' => 0, $locale . '_freeshippingTag' => 1]
        ];
        if (isset($set[$mode])) {
            return $set[$mode];
        }
        return [];
    }

    public
    static function getMysqlPrefixFromString($string)
    {
        if ($string) {
            $string = preg_replace("/[^A-Za-z0-9 ]/", '', $string);
            $string = strtolower($string);
            return $string;
        }
        return null;
    }

    public
    static function obfuscateString($string)
    {
        // replace middle chars with stars, keep 4 start and 4 end, add 3 stars between.
        if (strlen($string) > 16) {
            return substr($string, 0, 4) . '***' . substr($string, -4, 4);
        } elseif (strlen($string) > 8) {
            return substr($string, 0, 2) . '***' . substr($string, -2, 2);
        } else {
            return substr($string, 0, 2) . '***';
        }
    }

    public
    static function filterCorrectFieldsFromCSV(array $itemList, array $fieldList)
    {
        $keysList = array_flip($fieldList);
        $emptyItem = array_map(function ($item) {
            return null;
        }, $fieldList);
        $resultList = [];
        if (count($itemList)) {
            foreach ($itemList as $itemKey => $item) {
                $keepingFeilds = array_intersect_key($item, $keysList);
                $data = [];
                foreach ($keepingFeilds as $fieldKey => $keepingFeild) {
                    $data[$keysList[$fieldKey]] = $keepingFeild;
                }
                $data = array_merge($emptyItem, $data);
                $resultList[] = $data;
            }
        }
        return $resultList;
    }

    public
    static function installDb($db, $prefix, $filePath)
    {
        $sql = new Sql($db);
        /* first delete old data */
        $stmt = $sql->getAdapter()->getDriver()->createStatement();

        $query = file_get_contents($filePath);
        if ($query) {
            $query = str_replace('{TABLE_PREFIX}', $prefix, $query);
            $stmt->setSql($query);
            $stmt->execute();
        } else {
            throw new \Exception(' no basic structure found for the mysql for ' . $prefix);
        }
    }

    public
    static function checkTableExistance($db, $table)
    {

        try {
            $sql = new Sql($db);
            $select = $sql->select($table);
            $select->columns(['qty' => new Expression('COUNT(*)')]);
            $select->limit(1);
            $stmt = $sql->prepareStatementForSqlObject($select);
            $result = $stmt->execute();
            if ($result->current()) {
                return true;
            }
        } catch (\RuntimeException $e) {

        }

        return false;
    }

    public static function getCountryDeliveryCheck(string $shipsFromTag, $delivery, $pCountries)
    {
        preg_match($shipsFromTag, $delivery, $country);
        $country = $country[1] ?? false;
        $check = false;
        if ($country) {
            foreach ($pCountries as $pCountry) {
                if (strpos($country, $pCountry) !== false) {
                    // we have a good country
                    $check = true;
                }
            }
        } else {
            // if no country specified, we treat it as good
            $check = true;
        }
        return $check;

    }

    public
    function deleteContentFileByPath($path)
    {
        $file = 'data/content/' . $path . '.html';
        if (file_exists($file)) {
            return unlink($file);
        }
        return false;
    }
    public static function arrayDiffRecursive($arr1, $arr2)
    {
        $outputDiff = [];

        foreach ($arr1 as $key => $value)
        {
            //if the key exists in the second array, recursively call this function
            //if it is an array, otherwise check if the value is in arr2
            if (array_key_exists($key, $arr2))
            {
                if (is_array($value))
                {
                    $recursiveDiff = self::arrayDiffRecursive($value, $arr2[$key]);

                    if (count($recursiveDiff))
                    {
                        $outputDiff[$key] = $recursiveDiff;
                    }
                }
                else if (!in_array($value, $arr2))
                {
                    $outputDiff[$key] = $value;
                }
            }
            //if the key is not in the second array, check if the value is in
            //the second array (this is a quirk of how array_diff works)
            else if (!in_array($value, $arr2))
            {
                $outputDiff[$key] = $value;
            }
        }

        return $outputDiff;
    }

}
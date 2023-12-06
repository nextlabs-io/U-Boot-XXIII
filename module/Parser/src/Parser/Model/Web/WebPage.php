<?php


namespace Parser\Model\Web;

use Parser\Model\Helper\Helper;
use Parser\Model\SimpleObject;

/**
 * Class that retrieves page content of the url
 * specified by url
 * !TODO need to replace with implemented zend_http functionality
 *
 */
class WebPage extends SimpleObject
{

    public $ch;
    /**
     * url of the page
     *
     * @var string
     */

    public $url = '';
    /**
     * retrieved content of the page
     *
     * @var string
     */
    public $content = '';
    /**
     * content splitted to array of tags
     *
     * @var array
     */
    public $tags = [];

    public $cookie = [];

    public $_curlError = '';

    public $_useTor = false;


    public $resultHTML;

    public function __construct($url = '', $data = [])
    {
        $this->url = $url;
        if ($data) {
            $this->loadFromArray($data);
        }
    }


    /**
     * @return string
     */

    public function getContent()
    {
        if (!isset($this->content) || !$this->content) {
            $this->getContentFromWeb();
        }
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    public function getContentFromWeb($data = [])
    {
        if ($data) {
            $this->loadFromArray($data);
        }
        $header = $this->getProperty('Header');
        $parts = [];
        if (!$header) {

            $parts = parse_url($this->url);
            $host = isset($parts['host']) ? $parts['host'] : "";
            if ($host) {
                $header[] = "Host: " . $host;
            }
            $header[] = "Content-Type: text/html;charset=UTF-8";
            $header[] = "Accept-Language:en-US,en;q=0.5";
            $header[] = "Accept-Encoding:gzip, deflate";
            // text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9
            $header[] = "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8";
            $header[] = "Cache-Control:max-age=0";
            $header[] = "Connection:keep-alive";
        }

        if (!$this->ch) {
            $this->ch = curl_init();
        }
        curl_setopt($this->ch, CURLOPT_URL, $this->url);

        if (isset($parts['port'])) {
            curl_setopt($this->ch, CURLOPT_PORT, $parts['port']);
        }
        $post = $this->getProperty('POST');

        if ($header && !$post) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        if ($this->getProperty('UserAgent')) {
            curl_setopt($this->ch, CURLOPT_USERAGENT, $this->getProperty('UserAgent'));
        } else {
            curl_setopt($this->ch, CURLOPT_USERAGENT,
                'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.65 Safari/537.36');
        }

        $referer = $this->getProperty('Referer');
        if (!$referer) {
            $parts = parse_url($this->url);
            $host = isset($parts['host']) ? $parts['host'] : "";
            $scheme = isset($parts['scheme']) ? $parts['scheme'] : "http";
            $scheme .= "://";
            if ($host) {
                $referer = $scheme . $host;
            }
        }
        curl_setopt($this->ch, CURLOPT_REFERER, $referer);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 60);
//		curl_setopt ($this->ch, CURLOPT_MAXREDIRS, 100);
        if (!$post) {
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        }

        curl_setopt($this->ch, CURLOPT_COOKIE,
            'session-id=' . rand(100, 999) . '-' . rand(1000000, 9999999) . '-' . rand(1000000, 9999999));


        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);

        // on some php settings it is forbidden to set followlocation option
        if ($this->getProperty('Follow')) {
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        } else {
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 0);
        }
        /*  */
        if ($this->getProperty('POST')) {
            curl_setopt($this->ch, CURLOPT_POST, 1);
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($this->getProperty('PostFields')));
        }


        if ($this->getProperty('AllCookies')) {
            curl_setopt($this->ch, CURLOPT_COOKIE, implode(";", $this->getProperty("AllCookies")));
        }
        if ($this->getProperty('CookieFile')) {
            curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->getProperty('CookieFile'));
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->getProperty('CookieFile'));
        }

        if ($this->getProperty('ProxyIp')) {

            $proxy = 'http://' . $this->getProperty('ProxyIp') . ':' . $this->getProperty('ProxyPort');

            $proxyUserName = $this->getProperty('ProxyUserName');
            $proxyUserPass = $this->getProperty('ProxyUserPass');
            // setting username and pass
            if ($proxyUserName && $proxyUserPass) {
//                curl_setopt($this->ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY);
                curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, $proxyUserName . ':' . $proxyUserPass);
                $proxy = 'https://' . $this->getProperty('ProxyIp') . ':' . $this->getProperty('ProxyPort');
                curl_setopt($this->ch, CURLOPT_PROXY, $this->getProperty('ProxyIp'));
            }
            if ($auth = $this->getProperty('ProxyTorAuth')) {
                // TODO add intelligent identity reset, do not reset if proxy works.
                $torAuthPort = $this->getProperty('ProxyTorAuthPort');
                // only if port specified - change the identity
                if ($torAuthPort) {
                    Helper::resetTorProxy($this->getProperty('ProxyIp'), $torAuthPort, $auth);
                }
                curl_setopt($this->ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }

            curl_setopt($this->ch, CURLOPT_PROXY, $this->getProperty('ProxyIp'));
            curl_setopt($this->ch, CURLOPT_PROXYPORT, $this->getProperty('ProxyPort'));

        }
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);

        $resultHTML = curl_exec($this->ch);
        $info = curl_getinfo($this->ch);
        $this->setProperty('CurlInfo', $info);
        /*
            parsing headers to get header info.
        */
        $headerSize = $info['header_size'];
        $header = substr($resultHTML, 0, $headerSize);
        $header = explode("\r\n", $header);
        $header = $this->skipEmptyElements($header);

        $respHeader = [];
        foreach ($header as $item) {
            $item = explode(":", $item);
            if (isset($item[0]) && isset($item[1])) {
                $respHeader[$item[0]] = trim($item[1]);
            }
        }

        //echo "<pre>";
        //print_r($respHeader);
        $this->setProperty('ResultHeader', $respHeader);
        $this->getCookies($header);
        if (!$resultHTML) {
            $this->addError("No output by this url");
        }


        $this->resultHTML = $resultHTML;
        $this->content = substr($resultHTML, $headerSize, strlen($resultHTML));
        $headerString = implode("\r\n", $header);
        if (strpos($headerString, 'content-encoding: gzip') !== false ||
            strpos($headerString, 'Content-Encoding: gzip') !== false) {
            $this->gunzip();
        }

        $error = curl_error($this->ch);

        if ($error) {
            $this->_curlError = $error;
            $this->addError($error);
        }

        return $this;
    }


    function skipEmptyElements($array)
    {
        if (is_array($array) && count($array)) {
            foreach ($array as $k => $v) {
                if (!trim($v)) {
                    unset($array[$k]);
                }
            }
        }
        return $array;
    }

    /**
     * parses retrieved content and produces an array of tags
     * @param array $header
     * @return true
     */
    function getCookies($header = [])
    {
        $c = [];
        if (is_array($header) && count($header)) {
            foreach ($header as $k => $v) {
                if (strpos($v, "Set-Cookie:") !== false) {
                    $v = trim(str_replace("Set-Cookie:", "", $v));
                    $c[] = explode(";", $v);
                }
            }
        }
        $cData = [];
        if (is_array($c) && count($c)) {
            foreach ($c as $k => $v) {
                $cData[] = $v[0];
            }
        }
        $this->setProperty("Cookie", $cData);
        if ($cData) {
            $all = $this->getProperty("AllCookies");
            if (!$all) {
                $all = [];
            }
            $all = array_merge($all, $cData);
            $this->setProperty("AllCookies", $all);
        }
    }

    public function gunzip()
    {
        $zipped = $this->content;
        $offset = 0;
        if (substr($zipped, 0, 2) == "\x1f\x8b") {
            $offset = 2;
        }
        if (substr($zipped, $offset, 1) == "\x08") {
            $this->content = @gzinflate(substr($zipped, $offset + 8));
            return $this->content;
        }
        return "Unknown Format";
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * retrieves content of the web page
     *
     *
     * @return bool
     */

    public function getCurlError()
    {
        return $this->_curlError;
    }
}
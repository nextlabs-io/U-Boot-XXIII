<?php
/**
 * Created by WebExperiment.
 * User: Ernazar
 * Date: 11.03.2016
 * Time: 21:19
 */

namespace Parser\Model\Web;

require_once __DIR__ . '/deathbycaptcha.php';

use GuzzleHttp\Psr7\Request;
use Parser\Model\Helper\Helper;
use Parser\Model\Web\Browser\ContentMarker;

/*
 * @InheritDoc
 */

class Browser extends WebPage
{
    public const SOLVE_CAPTCHA_ATTEMPTS_LIMIT = 2;
    public $config;
    public $captcha_service;
    public $captcha_login;
    public $captcha_password;
    public $captcha_balance;
    public $solve_captcha;
    public $data_dir;
    public $solve_captcha_attempts;
    /** @var $client WebClient */
    public $client;
    // proxy object
    /**
     * @var $proxy Proxy
     */
    public $proxy;
    /**
     * @var $userAgent UserAgent
     */
    public $userAgent;
    public $code;
    public $mode;
    public $debugMode;
    public $group;
    public $tag;
    /**
     * @var Request
     */
    public $request;
    public $phantomFlag;
    public $failedUAList;
    public $seleniumChromeFlag;
    /**
     * @var ContentMarker
     */
    public $contentMarker;
    public $puppeteerFlag;
    /**
     * @var array
     */
    public $ignoredProxies = [];
    /**
     * @var $customHeaders array custom headers
     */
    private $customHeaders = [];

    /**
     * Browser constructor.
     * @param           $url
     * @param array $config
     * @param Proxy $proxy
     * @param UserAgent $userAgent
     * @param array $data
     * @throws \RuntimeException
     */

    public function __construct($url, Proxy $proxy, UserAgent $userAgent, $config = [], $data = [])
    {
        $this->config = $config;
        $this->data_dir = (isset($config['data_dir']) && $config['data_dir'])
            ? getcwd() . $config['data_dir']
            : getcwd() . '/data/parser/cookie';
        $this->solve_captcha_attempts = 0;
        /** @noinspection MkdirRaceConditionInspection */
        if ($this->data_dir && !is_dir($this->data_dir) && !mkdir($this->data_dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->data_dir));
        }
        $this->setCaptchaOptions($config);

        parent::__construct($url, $data);

        $this->proxy = $proxy;
        $this->userAgent = $userAgent;
        if (isset($config['cookie_file']) && $config['cookie_file']) {
            $cookieFile = $this->data_dir . '/' . $config['cookie_file'];
            $this->setProperty('CookieKey', $config['cookie_file']);
            $this->setProperty('CookieFile', $cookieFile);
        }
        if (isset($config['content_tag'])) {
            $this->setProperty('ContentTag', $config['content_tag']);
        }
        // developer mode tries to avoid http requests to remote site if the content is cached by url
        if (isset($config['mode']) && $config['mode'] === 'developer') {
            $this->mode = 'developer';
        } else {
            $this->mode = '';
        }
        // debug mode is specified for indeep scraping debug, every request is kept and linked to the process
        if (isset($config['debugMode']) && $config['debugMode']) {
            $this->debugMode = true;
        }


    }

    public function setCaptchaOptions($config)
    {
        $captchaService = $config['captcha_service'] ?? false;
        if ($captchaService && isset($config[$captchaService])) {
            $this->solve_captcha = (isset($config['solve_captcha']) && $config['solve_captcha']);
            $config = $config[$captchaService];
            $this->captcha_service = $captchaService;
            $this->captcha_login = $config['login'] ?? '';
            $this->captcha_password = $config['password'] ?? '';
        } else {
            $this->solve_captcha = false;
        }
        return $this;
    }

    public function getAdvancedPage($url = '', $options = [])
    {
        if ($url) {
            $this->setUrl(trim($url));
        }


        // debugging in developer mode, for a faster check - files are saved and got back later.
        if ($this->mode === 'developer') {
            $content = Helper::getContentFromFileByPath($this->getContentTag());
            if ($content) {
                $this->content = $content;
                $this->code = 200;
                return $this;
            }
        }

        $this->addMessage($this->getUrl());
        $this->setProperty('ProxyPort', $this->proxy->getProperty('port'));
        $this->setProperty('ProxyIp', $this->proxy->getProperty('ip'));
        $this->setProperty('ProxyUserName', $this->proxy->getProperty('user_name'));
        $this->setProperty('ProxyUserPass', $this->proxy->getProperty('user_pass'));
        $this->setProperty('ProxyTorAuth', $this->proxy->getProperty('tor_auth'));
        $this->setProperty('ProxyType', $this->proxy->getProperty('proxy_type'));
        $this->setProperty('ProxyTorAuthPort', $this->proxy->getProperty('tor_auth_port'));
        $follow = ini_get('allow_url_fopen');
        $this->setProperty('Follow', $follow);

        if ($this->getPhantomFlag()) {
            $this->getPhantomPage($url);
        } else if ($this->getSeleniumChromeFlag()) {
            $this->getSeleniumChromePage($url);
        } else if ($this->getPuppeteerFlag()) {
            $this->getPuppeteerPage($url);
        } else {
            $this->getContentFromWeb();
        }
        //$code = $this->code;

        if (is_object($this->contentMarker)) {
            $code = $this->contentMarker->getCode($this->content);
            if ($code !== null) {
                $this->debugPrint('setting code ' . $code);
                $this->code = $code;
            }
        }

        $proxyId = $this->proxy->getProperty('proxy_id');
        if ($this->proxy->getProperty('tor_auth')) {
            if (!$this->client) {
                $this->client = new WebClient([]);
            }
            // tor proxy, need to set result
            if (in_array($this->code, [200, 400, 404], true)) {
                // good tor result
                $this->client->setLastTorRequestResult(true, $proxyId, $this->debugMode );
            } else {
                $this->client->setLastTorRequestResult(false, (bool) $proxyId, $this->debugMode);
            }
        }


        // checking failed curl request
        $allowedCodes = $options['allowedCodes '] ?? [200, 400, 404];
        if (!in_array($this->code, $allowedCodes, true)) {
            if ($this->proxy->proxyRetryCount >= $this->proxy->maxProxyRetries) {
                // too many proxy retries
                $this->proxy->logConnection($this->code, 'proxy retry count reached');
                $this->proxy->proxyRetryCount = 0;
                $this->addError('proxy retry count reached');
                return $this;
            }
            $this->proxy->logConnection($this->code);
            if ($this->proxy->retryCount < $this->proxy->maxRetries) {
                // another try with the same proxy, keep the cookie cache
                $this->proxy->retryCount++;
                // todo keep the user agent here!
                $this->addFailedUA($this->userAgent->getProperty('value'));
                return $this->getAdvancedPage($url);
            }
            $this->addProxyToIgnoredList();
            if ($this->proxy->loadAvailableProxy($this->ignoredProxies)) {
                $this->proxy->retryCount = 0;
                $this->proxy->proxyRetryCount++;
                // drop cookie cache only on user agent change
//                $cookieCacheObject = new Cookie();
//                $cookieCacheObject->setCookieCache([], $this->compoundCookieKey());
                $this->addFailedUA($this->userAgent->getProperty('value'));
                return $this->getAdvancedPage($url);
            }
            return $this;
        }
        /**
         * check cookies
         */

        // TODO refactor captcha solving and move it from browser.
        if ($this->solve_captcha
            && $this->solve_captcha_attempts <= self::SOLVE_CAPTCHA_ATTEMPTS_LIMIT
            && strpos($this->getContent(), '/captcha/')) {
            file_put_contents($this->data_dir . '/captcha.log', "got captcha\r\n", FILE_APPEND);
            $this->proxy->logConnection($this->code,
                'captcha url,try:' . $this->solve_captcha_attempts . '; userAgent: ' . $this->userAgent->getProperty('value'));
            $captchaUrl = $this->generateUrlForCaptchaPage();
            if ($captchaUrl) {
                @unlink($this->getProperty('CookieFile'));
                return $this->getAdvancedPage($captchaUrl);
            } else {
                $this->proxy->logConnection($this->code, 'captcha solve error: ' . $this->getStringErrorMessages());
                //print_r("\n" . $this->getStringErrorMessages());
                if ($this->proxy->proxyRetryCount < $this->proxy->maxProxyRetries) {
                    $this->proxy->proxyRetryCount++;
                    if ($this->proxy->loadAvailableProxy()) {
                        return $this->getAdvancedPage($url);
                    } else {
                        return $this;
                    }
                } else {
                    $this->proxy->logConnection($this->code, 'proxy retry count reached');
                    $this->proxy->proxyRetryCount = 0;
                    $this->addError('proxy retry count reached');
                    return $this;
                }
            }
        } elseif (strpos($this->getContent(), '/captcha/') || strpos($this->getContent(), 'Amazon CAPTCHA')) {
            // todo modify captcha marker and put to config
            // captcha solving is disabled, we just change proxy and try again
            $this->code = 555;
            if ($this->proxy->proxyRetryCount < $this->proxy->maxProxyRetries) {
                $this->proxy->logConnection(555, 'captcha');
                $this->proxy->proxyRetryCount++;
                if (!$this->proxy->loadAvailableProxy()) {
                    // TODO proxy didn't load? need to do something here for sure
                } else {
                    $this->proxy->retryCount = 0;
                }
                $this->addFailedUA($this->userAgent->getProperty('value'));
                return $this->getAdvancedPage($url);
            } else {
                $this->proxy->logConnection(555, 'proxy retry count reached');
                $this->addError('proxy retry count reached');
                $this->proxy->proxyRetryCount = 0;
                return $this;
            }
        } elseif (strpos($this->getContent(), 'Sorry! Something went wrong!')) {
            // captcha solving is disabled, we just change proxy and try again
            $this->code = 556;
            if ($this->proxy->proxyRetryCount < $this->proxy->maxProxyRetries) {
                $this->proxy->logConnection(556, 'sorry page');
                $this->proxy->proxyRetryCount++;
                if (!$this->proxy->loadAvailableProxy()) {
                    return $this;
                } else {
                    $this->proxy->retryCount = 0;
                }
                $this->addFailedUA($this->userAgent->getProperty('value'));
                return $this->getAdvancedPage($url);
            } else {
                $this->proxy->logConnection(556, 'proxy retry count reached');
                $this->addError('proxy retry count reached');
                $this->proxy->proxyRetryCount = 0;
                return $this;
            }
        }
        $html = $this->getContent();
        $this->setContent($html);
        // todo move content save to a place where content is proved to be good.
        if ($this->mode === 'developer') {
            Helper::saveContentToFileByPath($this->getContentTag(), $html);
        }
        // todo MOVE this check to some other location. it is not here to check this.
        if (!$this->checkHtml()) {
            $this->proxy->logConnection($this->code, 'we are sorry page');
        }

        return $this;
    }

    /**
     * defines which filepath will be for stored content in a developer mode
     * @return string
     */
    public function getContentTag(): string
    {
        return $this->getProperty('ContentTag') ?: md5($this->getUrl());
    }

    /**
     * @return mixed
     */
    public function getPhantomFlag()
    {
        return $this->phantomFlag;
    }

    /**
     * @param mixed $phantom
     * @return Browser
     */
    public function setPhantomFlag($phantom): Browser
    {
        $this->phantomFlag = $phantom;
        return $this;
    }

    public function getPhantomPage($url = '', $options = [])
    {
        if ($url) {
            $this->setUrl($url);
        }
        $phantom = new PhantomBrowser($this->getProperty('PhantomBinary'), $this->debugMode);
        $port = $this->proxy->getProperty('port');
        $proxyIp = $this->proxy->getProperty('ip');
        $proxyTypeByIp = ($proxyIp == '127.0.0.1') ? 'socks5' : 'http';
        $proxyType = $this->proxy->getProperty('proxy_type') ?: $proxyTypeByIp;


        $this->userAgent->getUserAgent($this->getProperty('UserAgentId'), $this->getFailedUA(), $this->getProperty('UserAgentGroups'));
        $this->proxy->addConnection($this->getUrl(), $this->userAgent->getProperty('user_agent_id'), $this->getTag(), $this->getGroup());


        $userAgent = $this->userAgent->getProperty('value');

        if (!$this->proxy->hasErrors()) {

            $phantom->getPage($this->getUrl(), $userAgent, $proxyIp, $port, $proxyType);
//            pr($phantom->responseData);
            $this->content = $phantom->content;
            $this->code = $phantom->code;

            $this->userAgent->triggerUsage($this->code);
            $this->proxy->logConnection($this->code);
            $this->proxy->closeConnection();
        } else {
            if ($this->proxy->getProperty('proxy_connection_id')) {
                $this->proxy->closeConnection();
            }
            // something is wrong with connection, but creating connection should be very easy, either mysql problem or some other serious outage.
            $this->loadErrors($this->proxy);
        }
        return $this->content;
    }

    /**
     * @return array
     */
    public function getFailedUA(): array
    {
        return $this->failedUAList ?: [];
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
     * @return Browser
     */
    public function setTag($tag)
    {
        $this->tag = $tag;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getGroup()
    {
        return $this->group;
    }

    // set cookie key depend on the user agent

    /**
     * @param mixed $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    // set cookie key depend on the user agent

    /**
     * @return mixed
     */
    public function getSeleniumChromeFlag()
    {
        return $this->seleniumChromeFlag;
    }

    public function setSeleniumChromeFlag($flag)
    {
        $this->seleniumChromeFlag = $flag;
        return $this;

    }

    public function getSeleniumChromePage($url = '', $options = [])
    {
        if ($url) {
            $this->setUrl($url);
        }
        $driverPath = $options['driverPath'] ?? getcwd() . '/phantom/chromedriver';
        $executableScript = $this->getProperty('SeleniumChromeExecutableScript') ?: 'cdiscount';
        $webDriver = new SeleniumBrowser($this->getProperty('SeleniumChromeBinary'), $driverPath, $executableScript);

//        $pData = $this->proxy->loadProxyByIpPort('127.0.0.1',9090);
//        $this->proxy->loadFromArray($pData);

        $port = $this->proxy->getProperty('port');
        $proxyIp = trim($this->proxy->getProperty('ip'));
        // TODO proxytype should be defined within proxy itself
        $proxyTypeByIp = ($proxyIp == '127.0.0.1') ? 'socks5' : 'http';
        $proxyType = $this->proxy->getProperty('proxy_type') ?: $proxyTypeByIp;


        $proxyTorPort = $this->proxy->getProperty('tor_auth_port');
        $proxyTorAuth = $this->proxy->getProperty('tor_auth');
//        if($proxyTorAuth && $proxyTorPort) {
//            $client = new WebClient([]);
//            $client->resetTorProxy($proxyIp, $proxyTorPort, $proxyTorAuth);
//            sleep(10);
//        }

        $this->userAgent->getUserAgent($this->getProperty('UserAgentId'), $this->getFailedUA(), $this->getProperty('UserAgentGroups'));
//        pr($this->getProperty('UserAgentGroups'));
//        pr($this->userAgent->getProperties());
//        die();
        $this->proxy->addConnection($this->getUrl(), $this->userAgent->getProperty('user_agent_id'), $this->getTag(), $this->getGroup());

        $userAgent = $this->userAgent->getProperty('value');

        if (!$this->proxy->hasErrors()) {

            $webDriver->getPage($this->getUrl(), $userAgent, $proxyIp, $port, $proxyType);
//            pr($webDriver->responseData);
            $this->content = $webDriver->content;
            $this->code = $webDriver->code;

            $this->userAgent->triggerUsage($this->code);
            $this->proxy->logConnection($this->code);
            $this->proxy->closeConnection();
        } else {
            if ($this->proxy->getProperty('proxy_connection_id')) {
                $this->proxy->closeConnection();
            }
            // something is wrong with connection, but creating connection should be very easy, either mysql problem or some other serious outage.
            $this->loadErrors($this->proxy);
        }
        return $this->content;
    }

    public function getPuppeteerFlag()
    {
        return $this->puppeteerFlag;
    }

    public function setPuppeteerFlag($flag)
    {
        $this->puppeteerFlag = $flag;
        return $this;

    }

    private function getPuppeteerPage(string $url)
    {
        if ($url) {
            $this->setUrl($url);
        }
        $executableScript = $this->getProperty('PuppeteerExecutableScript') ?: 'avito.ts';
        $webDriver = new PuppeteerBrowser($this->getProperty('PuppeteerBinary'), 1, $executableScript);

        $port = $this->proxy->getProperty('port');
        $proxyIp = trim($this->proxy->getProperty('ip'));
        $proxyTypeByIp = ($proxyIp == '127.0.0.1') ? 'socks5' : 'http';
        $proxyType = $this->proxy->getProperty('proxy_type') ?: $proxyTypeByIp;


        $proxyTorPort = $this->proxy->getProperty('tor_auth_port');
        $proxyTorAuth = $this->proxy->getProperty('tor_auth');
//        if($proxyTorAuth && $proxyTorPort) {
//            $client = new WebClient([]);
//            $client->resetTorProxy($proxyIp, $proxyTorPort, $proxyTorAuth);
//            sleep(10);
//        }

        $this->userAgent->getUserAgent($this->getProperty('UserAgentId'), $this->getFailedUA(), $this->getProperty('UserAgentGroups'));
//        pr($this->getProperty('UserAgentGroups'));
//        pr($this->userAgent->getProperties());
//        die();
        $this->proxy->addConnection($this->getUrl(), $this->userAgent->getProperty('user_agent_id'), $this->getTag(), $this->getGroup());

        $userAgent = $this->userAgent->getProperty('value');

        if (!$this->proxy->hasErrors()) {
            $webDriver->setDevice($this->getProperty('PuppeteerDevice'));
            $webDriver->getPage($this->getUrl(), $userAgent, $proxyIp, $port, $proxyType);

//            pr($webDriver->responseData);
            $this->content = $webDriver->content;
            $this->code = $webDriver->code;

            $this->userAgent->triggerUsage($this->code);
            $this->proxy->logConnection($this->code);
            $this->proxy->closeConnection();
        } else {
            if ($this->proxy->getProperty('proxy_connection_id')) {
                $this->proxy->closeConnection();
            }
            // something is wrong with connection, but creating connection should be very easy, either mysql problem or some other serious outage.
            $this->loadErrors($this->proxy);
        }
        return $this->content;
    }

    public function getContentFromWeb($data = [])
    {
        if ($this->debugMode) {
            pr("<strong>starting page scraping:</strong> \n" . $this->getUrl());
        }
        // loading user agent, if specified the id - will load certain id.
        $this->userAgent->getUserAgent($this->getProperty('UserAgentId'), $this->getFailedUA());
//        $this->userAgent->getUserAgent($this->getProperty('UserAgentId'));
        // todo unify header definition. Right now it is not clear on which stage the user agent is placed
        $this->setProperty('UserAgent', $this->userAgent->getProperty('value'));
        $this->proxy->addConnection($this->getUrl(), $this->userAgent->getProperty('user_agent_id'), $this->getTag(), $this->getGroup());

        if (!$this->proxy->hasErrors()) {
            // old get content
//            parent::getContentFromWeb($data);
//            $cInfo = $this->getProperty('CurlInfo');
//            $code = $cInfo['http_code'];
//            $this->code = $code;
            // new get content
            $this->getGuzzlePage();

            $this->userAgent->triggerUsage($this->code);
            $this->proxy->logConnection($this->code);
            $this->proxy->closeConnection();
        } else {
            if ($this->debugMode) {
                pr('proxy errors');
                pr($this->proxy->getErrors());
            }
            if ($this->proxy->getProperty('proxy_connection_id')) {
                $this->proxy->closeConnection();
            }
            // something is wrong with connection, but creating connection should be very easy, either mysql problem or some other serious outage.
            $this->loadErrors($this->proxy);
        }
        return $this;
    }

    public function getGuzzlePage($url = ''): void
    {
        if (!$url) {
            $url = $this->getUrl();
        }
        $config = [];
        if (!$this->client) {
            $this->client = new WebClient($config);
        }
        $this->client->setProxy($this->proxy);
        if ($this->debugMode) {
            pr([
                'UserAgent' => $this->getProperty('UserAgent'),
                'Proxy' => $this->proxy->getProperty('ip') . ':' . $this->proxy->getProperty('port'),
                'CookieKey' => $this->compoundCookieKey(),
            ]);
        }

        $headers['User-Agent'] = $this->getProperty('UserAgent');

        $headers = $this->getCustomHeaders($headers);
        $pageConfig['headers'] = $headers;
        $pageConfig['cookieCacheKey'] = $this->compoundCookieKey();
        $pageConfig['method'] = 'GET';
        $pageConfig['debugMode'] = $this->debugMode;
        $pageConfig['timeout'] = $this->config['timeout'] ?? 30;
        $request = $this->client->getPage($url, $pageConfig);
        if ($request) {
            $this->request = $request;
            // that is how we can work with cookies.
            $cookieCacheObject = new Cookie();

            if ($this->debugMode && $this->compoundCookieKey()) {
                $cookie = $cookieCacheObject->getCookieFromCache($this->compoundCookieKey());
                pr($cookie);
            }

// moved to a higher level
//            if (in_array($request->getStatusCode(), [200, 404], true)
//                && $this->client->getProxy()->getProperty('tor_auth')) {
//                // good tor result
//                $this->client->setLastTorRequestResult(true);
//            } elseif($this->client->getProxy()->getProperty('tor_auth')) {
//                $this->client->setLastTorRequestResult(false);
//            }
            if ($this->debugMode == 3 && !in_array($request->getStatusCode(), [200, 404], true)) {
                pr($request->getBody()->getContents());
            }
            $this->setContent($request->getBody()->getContents());
            $this->code = $request->getStatusCode();
            if ($this->debugMode) {
                pr(['statusCode' => $request->getStatusCode()]);
            }
            if ($this->client->lastCallError) {
                if ($this->debugMode) {
                    pr(['lastCallError' => $this->client->lastCallError]);
                }
                $this->addError($this->client->lastCallError);
            }

        } else {
            // case when nothing was got, that is just proxy failure or other connection issue.
            $this->code = 0;
            if ($this->debugMode) {
                pr(['statusCode' => 0, 'lastCallError' => $this->client->lastCallError]);
            }
            $this->addError($this->client->lastCallError);
            $this->setContent(null);
        }
    }

    public function compoundCookieKey()
    {
        if (!$this->getProperty('CookieKey')) {
            return null;
        }
        return $this->getProperty('CookieKey') . md5($this->getProperty('UserAgent'));
    }

    public function getCustomHeaders($headers = [])
    {
        if (!count($this->customHeaders)) {
            return $headers;
        }
        return array_merge($headers, $this->customHeaders);
    }

    public function debugPrint($string)
    {
        if ($this->debugMode) {
            pr($string);
        }
    }

    /**
     * @param $userAgent
     */
    public function addFailedUA($userAgent): void
    {
        if (!$this->failedUAList) {
            $this->failedUAList = [];
        }
        $this->failedUAList[$userAgent] = $userAgent;
    }

    private function addProxyToIgnoredList()
    {
        if ($this->proxy->getProperty('proxy_character') !== 'rotating') {
            $this->ignoredProxies[] = $this->proxy->getProperty('proxy_id');
        }
    }

    /**
     * @return string
     * taking captcha page of amazon parsing captcha image and generating string url for passing captcha
     *             Array(
     * [fields] => Array
     * (
     * [amzn] => IPYay1qbLPACWEMXfP0rlw==
     * [amzn-r] => /gp/offer-listing/B010S9N6OO?SubscriptionId=AKIAJGORQED7LGUQTFAQ&tag=wp-amazon&linkCode=sp1&camp=2025&creative=386001&creativeASIN=B010S9N6OO
     * &f_primeEligible=true&f_new=true&startIndex=0
     * [amzn-pt] => OfferListing
     * [field-keywords] =>
     * )
     *
     * [captchaUrl] => http://ecx.images-amazon.com/captcha/usvmgloq/Captcha_kjunurpboz.jpg
     * [formUrl] => /errors/validateCaptcha
     */
    public function generateUrlForCaptchaPage()
    {
        // found captcha, need to add captcha solver here.
        if ($this->solve_captcha) {
            $data = $this->generateCaptchaRequest($this->getContent());
            if (!$data) {
                $this->addMessage('Generate captcha request failed');
                return '';
            }
            try {
                $captchaCode = '';
                switch ($this->captcha_service) {
                    case 'deathbycaptcha':
                        $client = new \DeathByCaptcha_SocketClient($this->captcha_login, $this->captcha_password);
                        $this->captcha_balance = $client->balance;
                        $client->is_verbose = false;

                        $captchaCode = $this->getCaptchaCode($data['captchaUrl'], $client);
                        break;
                    case 'captchasolutions':
                        /*$captchaFile = $this->saveImage($data['captchaUrl']);
                        if(! $captchaFile) {
                            return "";
                        }*/
                        $client = new CaptchaSolutions($this->captcha_login, $this->captcha_password);
                        $captchaCode = $this->getCaptchaSolutionsCode($data['captchaUrl'], $client);
                        break;
                }
                file_put_contents($this->data_dir . '/captcha.log',
                    'CaptchaCode:' . $captchaCode . '; url: ' . $data['captchaUrl'] . "\r\n", FILE_APPEND);
                if (!$captchaCode) {
                    $this->addMessage('failed to get captcha code, captcha balance: ' . $this->captcha_balance);
                    // failed to get captcha code
                    return '';
                }
                $data['fields']['field-keywords'] = $captchaCode;
                // everything is ready to make a curl call
                $oldUrl = $this->getUrl();
                $parts = parse_url($oldUrl);
                $formUrl = $parts['scheme'] . '://' . $parts['host'] . $data['formUrl'] . '?';
                $fields = [];
                unset($data['fields']['amzn-pt']);
                foreach ($data['fields'] as $name => $field) {
                    $fields[] = urlencode($name) . '=' . urlencode($field);
                }
                $formUrl .= implode('&', $fields);
                $this->solve_captcha_attempts++;

                return $formUrl;

            } catch (\Exception $e) {
                $this->addMessage($e->getMessage());
                return '';
            }
        } else {
            $this->addMessage('Captcha respond to the page request');
            return '';
        }
    }

    public function generateCaptchaRequest($content)
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($content);

        $xpath = new \DOMXPath($dom);
        $res = $dom->getElementsByTagName('input');
        $form = $xpath->query("//div[contains(concat(' ', @class, ' '), ' a-padding-extra-large')]/form");
        $images = $xpath->query("//div[contains(concat(' ', @class, ' '), 'a-box-inner')]//img");
        // get form url
        if ($form->item(0)) {
            $form = $form->item(0);
            $url = $form->getAttribute('action');
        } else {
            $this->addError('No captcha form found.');
            return false;
        }

        // get fields
        $fields = [];
        foreach ($res as $field) {
            $fields[$field->getAttribute('name')] = $field->getAttribute('value');
        }

        if (!isset($fields['field-keywords'])) {
            $this->addError('No captcha result field found');
            return false;
        }

        // get image url

        if ($images->item(0)) {
            $captchaUrl = $images->item(0)->getAttribute('src');
        } else {
            $this->addError('No captcha image found.');
            return false;
        }

        return ['fields' => $fields, 'captchaUrl' => $captchaUrl, 'formUrl' => $url];
    }

    public function getCaptchaCode($filename, \DeathByCaptcha_Client $client)
    {
        static $i = 0;
        try {
            if ($captcha = $client->decode($filename, \DeathByCaptcha_Client::DEFAULT_TIMEOUT)) {

                //echo "CAPTCHA {$captcha['captcha']} solved: {$captcha['text']} \n<br /> ". $i . " iterations taken";
                // Report an incorrectly solved CAPTCHA.
                // Make sure the CAPTCHA was in fact incorrectly solved!
                //$client->report($captcha['captcha']);
                return $captcha['text'];
            }
        } catch (\Exception $e) {
            if ('CAPTCHA was rejected due to service overload, try again later' == $e->getMessage()) {
                // trying again
                if ($i > 10) {
                    $this->addExtendedError($e);
                    return false;
                }
                $i++;
                sleep(5);
                return $this->getCaptchaCode($filename, $client);
            }
            $this->addExtendedError($e);
            return false;
        }

    }

    public function getCaptchaSolutionsCode($url, $client)
    {
        /**
         * @var $client CaptchaSolutions
         */
        try {
            if ($captcha = $client->decode($url)) {
                //print_r($captcha);
                $obj = new \SimpleXMLElement($captcha);
                $json = json_encode($obj);
                $data = json_decode($json, true);
                if (isset($data['decaptcha']) && is_string($data['decaptcha']) && trim($data['decaptcha'])) {
                    $captcha = trim($data['decaptcha']);
                    if (strpos($captcha, 'Sorry,') !== false) {
                        $this->addError($captcha);
                        file_put_contents($this->data_dir . '/captcha-error.log', $captcha . "\r\n", FILE_APPEND);
                        return false;
                    }
                    return $captcha;
                } else {
                    $this->addError('No captcha result field found');
                    file_put_contents($this->data_dir . '/captcha-error.log', 'ErrorResponse:' . $captcha . "\r\n",
                        FILE_APPEND);
                    return false;
                }
            }
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    private function checkHtml()
    {
        $html = $this->getContent();
        if (strpos($html, "We're sorry!") !== false) {
            // banned proxy either user agent
            $this->userAgent->logFailedParse('wearesorry page with proxyCon:' . $this->proxy->getProperty('proxy_connection_id'));
            return false;
        }
        return true;
    }

    public function generateHeader($data = [])
    {
        if (!is_array($data)) {
            return;
        }
        $this->setCustomHeader($data);
        $this->setProperty('Header', $data);
        return $data;
    }

    /**
     * @param string|array $key
     * @param string $value
     * @return $this
     */
    public function setCustomHeader($key, $value = ''): self
    {
        if (is_array($key)) {
            foreach ($key as $header => $item) {
                if (trim($header)) {
                    $this->setCustomHeader($header, $item);
                }
            }
        } else {
            $this->customHeaders[$key] = $value;
        }
        return $this;
    }

    public function generateAvitoHeader($data = [])
    {
        $parts = parse_url($this->url);
        $host = isset($parts['host']) ? $parts['host'] : '';
        if ($host) {
            $header['Host'] = $host;
            $header['Origin'] = 'https://' . $host;
        }

        $header['Content-Type'] = 'text/html; charset=utf-8';
        $header['Accept-Language'] = 'ru-RU,ru;q=0.8';
        $header['Accept-Encoding'] = 'gzip, deflate';
        $header['Cache-Control'] = 'max-age=0';
        $header['Connection'] = 'keep-alive';

        if (count($data)) {
            $header = array_merge($header, $data);
        }

        $this->setProperty('Header', $header);
        $this->setCustomHeader($header);
        return $header;
    }

    public function saveImage($url)
    {
        $imageContent = $this->getSimplePage($url)->getContent();
//        $cInfo = $this->getProperty('CurlInfo');
//        $code = $cInfo['http_code'];
        $code = $this->code;
        if ($code == 200 && strpos($imageContent, 'window.location') === false) {
            // sample image url https://images-na.ssl-images-amazon.com/captcha/ahkfsmoa/Captcha_hbzbsjbygd.jpg
            $urlChunk = explode('/captcha/', $url);
            if (!isset($urlChunk[1])) {
                $this->addError('failed to get captcha filename for save');
                return false;
            }
            $fileName = $this->data_dir . '/images/' . str_replace('/', '_', $urlChunk[1]);
            file_put_contents($fileName, $imageContent);
            return $fileName;
        } else {
            $this->addError('failed to get captcha image');
            return false;
        }

    }

    public function getSimplePage($url = '')
    {
        if ($url) {
            $this->setUrl($url);
        }

        $this->setProperty('ProxyPort', $this->proxy->getProperty('port'));
        $this->setProperty('ProxyIp', $this->proxy->getProperty('ip'));
        $this->userAgent->getUserAgent($this->getProperty('UserAgentId'), $this->getFailedUA());
        //$this->userAgent->getUserAgent();
        $this->setProperty('UserAgent', $this->userAgent->getProperty('value'));

        $cookieFile = $this->data_dir . '/cookies_' . $this->proxy->getProperty('proxy_id') . '.txt';
        $this->setProperty('CookieFile', $cookieFile);

//        $follow = ini_get('allow_url_fopen');
        $this->setProperty('Follow', 1);

        $this->getGuzzlePage($this->getUrl());

//        $this->getContentFromWeb();
//        $cInfo = $this->getProperty('CurlInfo');
//        $code = $cInfo['http_code'];

        $content = $this->getContent();
        //file_put_contents($this->data_dir . "/captcha-image-content.log", $content );

        return $this;
    }

    /**
     * @param $testUrl
     * @param $amazonUrl
     * @param $attemptsCount
     * @return array
     */
    public function testProxy($testUrl, $amazonUrl, $attemptsCount = 10): array
    {
        /*
         *
         *
        $url = 'https://amazon-parser.web-experiment.info/blow.php';
        $url = 'https://www.amazon.co.uk/dp/B00HNTDL70/ref=br_asw_pdt-4?ie=UTF8&psc=1&m=A3P5ROKL5A1OLE';

        $this->proxy->loadAvailableProxy();
        $browser = new Browser($url, [], $this->proxy, $this->userAgent);
        // browser tries to get the content, if required several attempts will be performed with proxy/user agent changes.
        $content = $browser->getAdvancedPage()->getContent();
        //$browser->setProperty('Referer', "https://amazon-parser.web-experiment.info/");
        //$content = ($content);
        echo "<pre><textarea>";

        echo $content;
        echo "</textarea>";
        die();

         */

        $data = [];
        $proxyList = $this->proxy->loadProxyData();
        // running through all proxy list
        $data['testUrl'] = $testUrl;
        $data['amazonUrl'] = $amazonUrl;

        while ($proxyData = $proxyList->current()) {
            $proxyId = 'proxyId:' . $proxyData['proxy_id'];
            $data[$proxyId] = ['proxy' => $proxyData['ip'] . ':' . $proxyData['port'], 'testUrlStat' => [], 'amazonUrlStat' => []];
            $this->proxy->loadFromArray($proxyData);
            $this->proxy->retryCount = 0;
            $this->proxy->clearErrors();
            // proxy is loaded with some data
            for ($attempt = 1; $attempt <= $attemptsCount; $attempt++) {
                $this->setUrl($testUrl);
                $this->setGroup('test-proxy-test')->setTag($proxyId);
                $this->getContentFromWeb();
                if (isset($data[$proxyId]['testUrlStat'][$this->code])) {
                    $data[$proxyId]['testUrlStat'][$this->code]++;
                } else {
                    $data[$proxyId]['testUrlStat'][$this->code] = 1;
                }
                $this->setGroup('test-proxy-amzn')->setTag($proxyId);
                $this->setUrl($amazonUrl);
                $this->getContentFromWeb();
                if (isset($data[$proxyId]['amazonUrlStat'][$this->code])) {
                    $data[$proxyId]['amazonUrlStat'][$this->code]++;
                } else {
                    $data[$proxyId]['amazonUrlStat'][$this->code] = 1;
                }
            }
            $proxyList->next();
        }
        return $data;
    }

    /**
     * @return bool|mixed
     */
    public function getFinalUrl()
    {
        if ($this->getPhantomFlag()) {

        } elseif (isset($this->request) && $requestHeaders = $this->request->getHeaders()) {
            $redirects = $requestHeaders['X-Guzzle-Redirect-History'] ?? [];
            return end($redirects);
        }
        return false;
    }
}
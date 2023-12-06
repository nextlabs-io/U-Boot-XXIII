<?php

/*
 * Simple config class which works with xml config file
 * It attempts to create a file if it does not exist and allows to save new config.
 *
 */

namespace Parser\Model\Helper;


use Parser\Model\Profile;
use Parser\Model\SimpleObject;
use Parser\Model\Web\ProxySource\ProxyScraper;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Session as SessionStorage;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Session\Container;

class Config extends SimpleObject
{
    /**
     * @var Logger
     */
    public $logger;
    /**
     * @var array
     */
    public $storeConfig;
    /**
     * @var AuthenticationService
     */
    public $auth;
    /**
     * @var Container
     */
    public $session;
    /**
     * @var array
     */
    protected $configData;
    /**
     * @var AdapterInterface
     */
    protected $db;
    /**
     * @var array
     */
    protected $localeList = [];

    protected $timeLine = [];
    protected $timeStamp = [];
    /**
     * @var Profile
     */
    public $userProfile;

    /**
     * Config constructor.
     * @param AdapterInterface $db
     * @param array $storeConfig
     */
    public function __construct(AdapterInterface $db, $storeConfig = [])
    {
        $this->db = $db;

        $configFile = $storeConfig['moduleConfigFile'] ?? 'data/parser/config.json';
        $this->configData['config'] = Helper::loadConfig($configFile, 'json');
//        $jsonConf = $this->configData['config'];

//        Helper::saveConfig($jsonConf, 'data/parser/config.local.json');

//        $configFile = $storeConfig['moduleConfigFile'] ?? 'data/parser/config/config.xml';
//        $this->configData['config'] = Helper::loadConfig($configFile, 'xml');
//        $xmlConf = $this->configData['config'];


//        $diff = Helper::arrayDiffRecursive($xmlConf, $jsonConf);
//        pr($diff);
//        die();
        $settings = $this->getConfig('settings');
        $this->logger = new Logger($db, $settings);
        $this->storeConfig = $storeConfig ?: [];

        $auth = new AuthenticationService();

        if (!$this->session) {
            $this->session = new Container();
        }
        $manager = $this->session->getManager();
        $storage = new SessionStorage('parser', null, $manager);
        $auth->setStorage($storage);
        $this->auth = $auth;
        if($this->auth->hasIdentity()){
            $profile = new Profile($this->getDb(), $this->auth->getIdentity());
            $profile->load();
            $this->userProfile = $profile;
        }

    }



    /**
     * @param null $section
     * @param null $key
     * @return array - global config or it's section
     */
    public function getConfig($section = null, $key = null): ?array
    {
        if (!$section) {
            return $this->configData['config'];
        }
        if($key){
            return $this->configData['config'][$section][$key] ?? null;
        }
        return $this->configData['config'][$section] ?? [];
    }

    public static function isFileExist($configFile)
    {
        return file_exists($configFile);
    }

    /**
     * @return AdapterInterface $db
     */
    public function getDb()
    {
        return $this->db;
    }

    public function getSetting($key)
    {
        return $this->configData['config']['settings'][$key] ?? null;
    }

    /**
     * @param $locale
     * @return mixed locale config std class
     * @throws \Exception
     */
    public function getLocaleConfig($locale)
    {
        $path = 'data/parser/config/profile/';
        return $this->localeConfigGetter($locale, $path);
    }

    /**
     * @param $locale
     * @param $path
     * @return mixed
     * @throws \Exception
     */
    private function localeConfigGetter($locale, $path)
    {
        if (!$locale) {
            throw new \Exception('empty locale not allowed');
        }
        $hash = md5($path);
        if (!isset($this->configData[$hash][$locale])) {
            $localeFile = $path . $locale . '.xml';
            if (!file_exists($localeFile)) {
                throw new \Exception('no locale config file found: ' . $path . $locale . ".xml");
            }
            $this->configData[$hash][$locale] = Helper::loadConfig($localeFile);
        }
        return $this->configData[$hash][$locale];
    }

    /**
     * @param $locale
     * @return mixed
     * @throws \Exception
     */
    public function getCrawlConfig($locale)
    {
        $path = 'data/parser/config/crawler/';
        return $this->localeConfigGetter($locale, $path);
    }

    /**
     * @param $url
     * @return mixed
     * @throws \Exception if url didn't parse or no locale found for the url
     */
    public function getLocaleByUrl($url)
    {
        $data = parse_url($url);
        if (!isset($data['host'])) {
            throw new \Exception('invalid url');
        }
        $config = $this->getConfig();

        $locales = $config['locales'] ?? [];
        foreach ($locales as $locale) {
            if (strpos($data['host'], $locale['url']) !== false) {
                return $locale['id'];
            }
        }
        throw new \Exception('no locale found by url');
    }

    public function getLocales($assoc = false)
    {
        if ($this->localeList) {
            return $this->localeList;
        }

        $config = $this->getConfig();
        $list = [];
        $locales = $config['locales'] ?? [];

        foreach ($locales as $locale) {
            if ($assoc) {
                $list[$locale['id']] = $locale['url'];
            } else {
                $list[] = $locale['id'];
            }

        }
        $this->localeList = $list;
        return $list;
    }

    /**
     * @param $mode
     */
    public function setDebugMode($mode): void
    {
        $this->setProperty('DebugMode', $mode);
    }

    /**
     * @return mixed|null
     */
    public function getDebugMode()
    {
        return $this->getProperty('DebugMode');
    }

    /**
     * @param $tag
     * @return Config
     */
    public function addTimeEvent($tag): Config
    {
        $this->timeStamp = microtime(1);
        $this->timeLine[] = ['timestamp' => $this->timeStamp, 'tag' => $tag];
        return $this;
    }

    /**
     * @return array
     */
    public function getTimeLine(): array
    {
        $data = [];
        if (count($this->timeLine)) {
            $moment = false;
            foreach ($this->timeLine as $key => $event) {
                $delta = !$moment ? 0 : $event['timestamp'] - $moment;
                $data[$key] = $event;
                $data[$key]['delta'] = (int)(1000 * $delta);
                $moment = $event['timestamp'];
            }
        }
        return $data;
    }

    /**
     * @param array|string $code
     * @param null $value
     */
    public function registerSettingOverride($code, $value = null){

        if(is_array($code)){
            foreach ($code as $key => $item) {
                $this->registerSettingOverride($key, $item);
            }
            return;
        }
        $overrides = $this->getProperty('overrides') ?: [];
        $overrides[$code] = $value;
        $this->setProperty('overrides', $overrides);

    }

    public function getSettingOverride($code){
        $overrides = $this->getProperty('overrides') ?: [];
        return $overrides[$code] ?? null;
    }
    public function getProfileSetting($code, $locale = null)
    {
        $key = $this->getSettingOverride($code);
        if($key === null) {
            $identity = $this->getSetting('keepaApiKeyIdentity') ?: 'admin';
            $profile = new Profile($this->getDb(), $identity);
            $key = $profile->getProfileSetting($code, $identity);
            if ($key === null) {
                $key = $this->getSetting($code);
            }
        }
        return $key;
    }

    public function getProfileFormCustomFields()
    {
        $customFields = $this->getConfig('profileSettings') ?: [];
        $list = [];
        $locales = $this->getLocales(1);
        $proxyScraper = new ProxyScraper($this);
        $proxyScraperTypes = $proxyScraper->getConfigType();
        foreach ($customFields as $id => $field) {
            if (strpos($id, 'locale_') !== false) {
                // add as many locales as required
                foreach ($locales as $locale => $url) {
                    $localeField = $field;
                    $localeField['name'] = str_replace('Locale', $url, $localeField['name']);
                    $fieldId = str_replace('locale', $locale, $id);
                    $list[$fieldId] = $localeField;
                }
            } elseif (strpos($id, 'proxyscraper_') !== false) {
                // add as proxyscraper account keys as needed
                foreach ($proxyScraperTypes as $profile => $data) {
                    $localeField = $field;
                    $localeField['name'] = str_replace('profile', $profile, $localeField['name']);
                    $fieldId = str_replace('serial', $profile, $id);
                    $list[$fieldId] = $localeField;
                }
            } else {
                $list[$id] = $field;
            }
        }
        return $list;
    }
}
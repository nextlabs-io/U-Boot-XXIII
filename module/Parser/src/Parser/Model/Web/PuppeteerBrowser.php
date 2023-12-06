<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 10.11.2020
 * Time: 14:42
 */

namespace Parser\Model\Web;


use Parser\Model\SimpleObject;
use Parser\Model\Web\Puppeteer\PuppeteerChromeDriverTemplate;

use RuntimeException;
use Laminas\Json\Json as ZJson;

class PuppeteerBrowser extends SimpleObject
{
    public $executablePath;
    public $driverPath;

    public $responseData;
    /**
     * keeps direct url response
     * @var array
     */
    public $urlResponse;
    public $code;
    public $content;
    /**
     * @var string
     */
    private $executableScript;
    private $device;

    public function __construct($executablePath, $driverPath = null, $executableScript = 'avito.ts')
    {
        $this->executablePath = $executablePath;
        if (!$executablePath) {
            throw new RuntimeException('no required params for puppeteer Browser');
        }
        $this->driverPath = $driverPath;
        if (!$driverPath) {
            throw new RuntimeException('no required params for puppeteer Browser');
        }
        $this->executableScript = $executableScript;
    }

    /**
     * @param $url
     * @param string $userAgent
     * @param $proxy
     * @param $proxyPort
     * @param string $proxyType
     * @return false|string
     */
    public function getPage($url, $userAgent, $proxy, $proxyPort, $proxyType = 'none')
    {
        $templateDataObject = new PuppeteerChromeDriverTemplate();
        $templateDataObject->url = $url;
        $contentFile = tmpfile();
        $tmpFileData = stream_get_meta_data($contentFile);
        if (!isset($tmpFileData['uri'])) {
            throw new RuntimeException('unable to create a tmp file for script execution');
        }
        $contentFilePath = $tmpFileData['uri'];
        $templateDataObject->contentFilePath = $contentFilePath;
        $templateDataObject->setData([
            'url' => $url,
            'userAgent' => $userAgent,
            'proxyHost' => $proxy,
            'proxyPort' => $proxyPort,
            'proxyType' => $proxyType,
            'contentFilePath' => $contentFilePath,
            'driverPath' => $this->driverPath,
        ]);
        // saving config file
        $confFile = tmpfile();
        fwrite($confFile, $templateDataObject->getBrowserScript($this->getDevice()));
//        pr($this->executableScript);
//        pr($templateDataObject->getBrowserScript($this->executableScript));


        $confFileData = stream_get_meta_data($confFile);
        $execConfigPath = $confFileData['uri'];

        $shellCommand = trim($this->getShellCommand($execConfigPath));

        pr($shellCommand);
        pr('proxy ' . $proxy . ':' . $proxyPort . ' ' . $proxyType);
        pr('user agent ' . $templateDataObject->userAgent);
        if($this->getDevice()){
            pr('using device :'. $this->getDevice());
            pr('warning: ua is ignored');
        }
        //        pr($templateDataObject->getBrowserScript());
        try {
            exec($shellCommand. ' 2>&1', $result);
//            pr($output);die();
//            $result = shell_exec($shellCommand);
        } catch (\Exception $e) {
            pr($e->getMessage());
            throw new RuntimeException($e->getMessage());
//            $this->addError($e->getMessage());
//            $this->code = 0;
//            return '';
        }
        pr('result');
        pr($result);
        // some python script printing data
        $this->responseData = $result;
        $content = file_get_contents($contentFilePath);
        $json = json_decode($content, 1, 512, JSON_UNESCAPED_UNICODE);
//        pr($json);
        $this->content = $json['urls'][0]['data'] ?? null;
//        pr($this->content);
        if(!$this->content){
            $this->code = 0;
        } else {
            $this->code = 200;
        }

//        if ($error || !trim($content)) {
//            $this->code = 0;
//        }

        //        pr(getcwd().'/data/log/content.txt');
//        file_put_contents(getcwd().'/data/log/content.txt', $this->content);
        pr('content length ' . strlen($this->content));
        pr('code ' . $this->code);
        return $this->content;
    }

    public function getShellCommand($execConfigPath)
    {
//        $execConfigPath = '/var/www/parser/html/phantom/test';
        // note may be a hard time to read json file without json extension
        $string = $this->executablePath . ' ' .getcwd().'/phantom/'.$this->executableScript.' ' . $execConfigPath;
        return $string;
    }

    /**
     * @return mixed
     */
    public function getDevice()
    {
        return $this->device;
    }

    /**
     * @param mixed $device
     */
    public function setDevice($device): void
    {
        $this->device = $device;
    }
}
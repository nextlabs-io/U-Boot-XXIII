<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 10.11.2020
 * Time: 14:42
 */

namespace Parser\Model\Web;


use Parser\Model\SimpleObject;
use Parser\Model\Web\Selenium\SeleniumChromeDriverTemplate;
use RuntimeException;
use Laminas\Json\Json as ZJson;

class SeleniumBrowser extends SimpleObject
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

    public function __construct($executablePath, $driverPath, $executableScript = 'cdiscount')
    {
        $this->executablePath = $executablePath;
        if (!$executablePath) {
            throw new RuntimeException('no required params for selenium Browser');
        }
        $this->driverPath = $driverPath;
        if (!$driverPath) {
            throw new RuntimeException('no required params for selenium Browser');
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
        $templateDataObject = new SeleniumChromeDriverTemplate();

        $templateDataObject->userAgent = $userAgent;


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
            'driverPath' => $this->driverPath
        ]);

        $confFile = tmpfile();
        fwrite($confFile, $templateDataObject->getBrowserScript($this->executableScript));
//        pr($this->executableScript);
//        pr($templateDataObject->getBrowserScript($this->executableScript));


        $confFileData = stream_get_meta_data($confFile);
        $execScriptPath = $confFileData['uri'];

        $shellCommand = $this->getShellCommand($execScriptPath);

        pr($shellCommand);
        pr('proxy ' . $proxy . ':' . $proxyPort . ' ' . $proxyType);
        pr('user agent ' . $templateDataObject->userAgent);

        //        pr($templateDataObject->getBrowserScript());
        try {
            $result = shell_exec($shellCommand);
        } catch (\Exception $e) {
            pr($e->getMessage());
            throw new RuntimeException($e->getMessage());
//            $this->addError($e->getMessage());
//            $this->code = 0;
//            return '';
        }
        try {
            $json = ZJson::decode($result);
        } catch (\Laminas\Json\Exception\RuntimeException $e) {
            $json = [];
        }
        pr('result');
        pr($json);
        $resultMessage = $json->result ?? null;
        $error = $json->error ?? null;
        if ($resultMessage === 'load success') {
            $this->code = 200;
        } elseif ($resultMessage) {
            $this->code = 503;
        }
        // some python script printing data
        $this->responseData = (array) $json;
        $content = file_get_contents($contentFilePath);
        $this->content = $content;
        if ($error || !trim($content)) {
            $this->code = 0;
        }

        //        pr(getcwd().'/data/log/content.txt');
//        file_put_contents(getcwd().'/data/log/content.txt', $this->content);
        pr('content length ' . strlen($content));
        pr('code ' . $this->code);
        return $content;
    }

    public function getShellCommand($execScriptPath)
    {
        $string = $this->executablePath . ' ' . $execScriptPath;
        return $string;
    }
}
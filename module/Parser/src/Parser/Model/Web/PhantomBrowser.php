<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 24.09.2020
 * Time: 20:33
 */

namespace Parser\Model\Web;

use Parser\Model\SimpleObject;
use Parser\Model\Web\Phantom\PhantomTemplate;
use RuntimeException;
use Laminas\View\Model\ViewModel;

/**
 * Class PhantomBrowser to utilise phantomJs engine.
 * Note! it has to be installed
 * @package Parser\Model\Web
 */
class PhantomBrowser extends SimpleObject
{

    public $executablePath;

    public $responseData;
    /**
     * keeps direct url response
     * @var array
     */
    public $urlResponse;
    public $code;
    public $content;
    private $debugMode;

    public function __construct($executablePath, $debugMode = false)
    {
        $this->debugMode = $debugMode;
        $this->executablePath = $executablePath;
        if (!$executablePath) {
            throw new RuntimeException('no required params for PhantomBrowser');
        }

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
        $phantomData = new PhantomTemplate();
        $phantomData->userAgent = $userAgent;

//        $data = parse_url($url);
//
//        $url = $data['scheme']."://".$data['host'].$data['path'];
//        if($data['query'] ?? null){
//            $data['urldecoded'] = urldecode($data['query']);
//            $url.= '?'.$data['urldecoded'];
//        }
        $phantomData->url = $url;
        pr($phantomData->url);
        $contentFile = tmpfile();
        $tmpFileData = stream_get_meta_data($contentFile);
        $contentFilePath = $tmpFileData['uri'];
        $phantomData->contentFilePath = $contentFilePath;

        $requestFile = tmpfile();
        $tmpRequestFileData = stream_get_meta_data($requestFile);
        $requestFilePath = $tmpRequestFileData['uri'];
        $phantomData->requestFilePath = $requestFilePath;


        $confFile = tmpfile();

        fwrite($confFile, $phantomData->getBrowserScript());
        $confFileData = stream_get_meta_data($confFile);
        $execScriptPath = $confFileData['uri'];

        $shellCommand = $this->getShellCommand($execScriptPath, $proxy, $proxyPort, $proxyType);
        pr($shellCommand);
        pr('proxy '. $proxy.':'.$proxyPort. ' '. $proxyType);
        pr('user agent '. $phantomData->userAgent);
        //pr($phantomData->getBrowserScript());
        try {
            $result = shell_exec($shellCommand);
        } catch (\Exception $e) {
            pr($e->getMessage());
            throw new RuntimeException($e->getMessage());
//            $this->addError($e->getMessage());
//            $this->code = 0;
//            return '';
        }
        if($this->debugMode === 2) {
            pr($result);
        }
        // get all response headers. there should be many.
        $responseData = $this->analyzeResult($result);
        $this->responseData = $responseData;
        $content = file_get_contents($contentFilePath);
        $this->content = $content;
//        pr(getcwd().'/data/log/content.txt');
//        file_put_contents(getcwd().'/data/log/content.txt', $this->content);
        pr('content length ' .strlen($content));
        if (strlen($content) < 100) {
            $this->code = 0;
        } else {
            $this->code = $this->extractCodeFromResult($url);
        }
        pr('code '. $this->code);
        return $content;
    }

    public function getShellCommand($execScriptPath, $proxy, $proxyPort, $proxyType)
    {
        if($proxyType !== 'socks5'){
            $proxyType = 'none';
        }
        $string = $this->executablePath . ' --load-images=false --proxy=' . $proxy . ':' . $proxyPort . ' --proxy-type=' . $proxyType . ' ' . $execScriptPath;
        return $string;
    }

    private function analyzeResult(string $result)
    {
        $data = [];
        $markerStart = '<!--startJson-->';
        $markerEnd = '<!--endJson-->';
        if ($result && strpos($result, $markerStart) !== false) {

            $list = explode($markerStart, $result);
            if (count($list)) {
                foreach ($list as $item) {
                    if (strpos($item, $markerEnd) !== false) {
//                        $data[] = $item;
                        $jsonChunk = explode($markerEnd, $item);
                        $jsonChunk = $jsonChunk[0];
                        $data[] = json_decode($jsonChunk, 1);
                    }
                }
            }

        }

        return $data;
    }

    private function extractCodeFromResult($url)
    {
        $urlDom = $this->getDomain($url);
        if ($this->responseData && count($this->responseData)) {
            foreach ($this->responseData as $singleResponse) {
                $headers = $this->getHeaders($singleResponse);
                $contentType = $singleResponse['contentType'] ?? null;

                if ($headers && strpos($contentType, 'text/html') !== false) {
                    // means we have a good response
                    // first html response is the right one
                    //pr($headers);
//                    pr($singleResponse);
                    $status = $singleResponse['status'];
                    $respUrl = $singleResponse['url'];
                    $stage = $singleResponse['stage'];
                    $singleResponse['headers'] = $headers;
                    $this->urlResponse = $singleResponse;
                    $this->code = $status;
                    //return $this->code;
                }
            }
        }
        return $this->code;
    }

    private function getDomain($url)
    {
        $urlData = parse_url($url);
        $urlDom = $urlData['scheme'] . '://' . $urlData['host'];
        return $urlDom;
    }

    /**
     * @param $singleResponse
     * @return array
     */
    private function getHeaders($singleResponse): array
    {
        $headers = $singleResponse['headers'] ?? [];
        if ($headers) {
            $assoc = [];
            foreach ($headers as $item) {
                $assoc[$item['name']] = $item['value'];
            }
            return $assoc;
        }
        return [];
    }


}
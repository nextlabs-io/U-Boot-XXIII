<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 25.09.2020
 * Time: 10:59
 */

namespace Parser\Model\Web\Puppeteer;

use Parser\Model\Web\DriverTemplate\AbstractDriverTemplate;

class PuppeteerChromeDriverTemplate extends AbstractDriverTemplate
{
    public $device;

    public function __construct($scriptPath = null)
    {
        if (!$scriptPath) {
            $scriptPath = getcwd() . '/phantom/avito.ts';
        }
        parent::__construct($scriptPath);
    }

    public function getBrowserScript($device = ''): string
    {
        /* generating json from data array
         * {
  "urls": [
    {
      "field": 0,
      "url": "https://m.avito.ru/?qid=",
      "data": ""
    },
    {
      "field": 1,
      "url": "https://m.avito.ru/novosibirsk?q=narada&qid=",
      "data": ""
    }
  ]
}
         */
        $data = [
            'proxyHost' => $this->proxyHost,
            'proxyPort' => $this->proxyPort,
            'proxyType' => $this->proxyType,
            'device' => $device,
            'userAgent' => $this->userAgent,
            'userDataDir' => getcwd() . '/phantom/puppeteer/',
            'contentFilePath' => $this->contentFilePath,
            'urls' => [
                ["field" => 0, "data" => "", "url" => $this->url]
            ]
        ];
        $json = json_encode($data, JSON_NUMERIC_CHECK);
        $json = $this->escapeJsonString($json);
        return $json;
    }

    public function escapeJsonString($value) {
        # list from www.json.org: (\b backspace, \f formfeed)
        $escapers =     array("\/",    "{",   ",\"", "\":\"");
        $replacements = array("/", "{\n", ",\n\"", "\": \"");
        $result = str_replace($escapers, $replacements, $value);
        return $result;
    }
}
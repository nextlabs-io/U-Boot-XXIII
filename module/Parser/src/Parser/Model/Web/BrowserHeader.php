<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 11.02.2020
 * Time: 19:51
 */

namespace Parser\Model\Web;

/**
 * Class BrowserHeader designed to deploy proper header depending on the situation
 * @package Parser\Model\Web
 */
class BrowserHeader
{
    public static function getChromeHeader()
    {
        $header =
            [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cache-Control' => 'max-age=0',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Upgrade-Insecure-Requests' => '1',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:69.0) Gecko/20100101 Firefox/69.0',
            ];
        return array_change_key_case($header);
    }

    public static function getMozillaHeader()
    {
        $header =
            [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.7',
                'Connection' => 'close',
                'Host' => '',
                'Upgrade-Insecure-Requests' => '1',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:69.0) Gecko/20100101 Firefox/69.0',
            ];
        return $header;
    }
}
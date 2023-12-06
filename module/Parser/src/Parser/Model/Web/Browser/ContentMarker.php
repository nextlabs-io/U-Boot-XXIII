<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 25.11.2020
 * Time: 11:34
 */

namespace Parser\Model\Web\Browser;


use Parser\Model\SimpleObject;

class ContentMarker extends SimpleObject
{

    public $markers;

    public function __construct($markers)
    {
        /*
         * sample $markers = [
         * 0 => ['code' => 200, 'pattern' => 'somePattern', 'function' => 'strpos'],
         * 1 => ['code' => 505, 'pattern' => 'someCaptchaPattern', 'function' => 'strpos'],
         * 1 => ['code' => 505, 'size' => '1500', 'function' => 'strlen'],
         * ]
         */
        $this->markers = $markers;
    }

    public function getCode($content)
    {
        // checking all markers for a content match, first found will return a result
        if ($this->markers) {
            foreach ($this->markers as $marker) {
                if ($marker['function'] === 'strpos') {
                    if (strpos($content, $marker['pattern']) !== false) {
                        return $marker['code'];
                    }
                } elseif ($marker['function'] === 'strlen') {
                    if (strlen($content) <= $marker['size']) {
                        return $marker['code'];
                    }
                }
            }
        }
        return null;
    }
}
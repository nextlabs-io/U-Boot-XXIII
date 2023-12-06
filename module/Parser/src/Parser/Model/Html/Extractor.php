<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 16.02.2020
 * Time: 12:47
 */

namespace Parser\Model\Html;


use Parser\Model\Helper\Helper;
use Parser\Model\SimpleObject;
use Laminas\Dom\DOMXPath;

class Extractor extends SimpleObject
{
    private $content;
    private $xpath;
    private $dom;

    /**
     * Extractor constructor.
     * @param $content DOMXPath|string
     */
    public function __construct($content)
    {
        if (is_object($content)) {
            // pass DOMXpath
            $this->xpath = $content;
        } else {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            @$dom->loadHTML($content);
            $this->dom = $dom;
            $this->xpath = new \DOMXPath($dom);
        }
    }

    public function getFirstElementByXpath($path)
    {

        $res = $this->getResourceByXpath($path);
        if ($res && $res->item(0)) {
            return $res->item(0);
        }
        return null;
    }

    public function getResourceByXpath($path, $element = null)
    {
        return $element ? $this->xpath->query($path, $element) : $this->xpath->query($path);
    }

}
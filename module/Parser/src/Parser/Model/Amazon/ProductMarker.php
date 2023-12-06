<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 13.02.2020
 * Time: 23:14
 */

namespace Parser\Model\Amazon;


use Parser\Model\Helper\Helper;
use Parser\Model\Html\Extractor;
use Laminas\Dom\DOMXPath;

class ProductMarker extends Extractor
{
    private $localeConfig;


    /**
     * ProductMarker constructor.
     * @param $content String|DOMXPath html content or xpath object
     * @param $localeConfig Array of locale configurations
     */
    public function __construct($content, $localeConfig)
    {
        $this->localeConfig = $localeConfig;
        parent::__construct($content);
    }

    /**
     * find asin markers, run though all list one by one
     * @param $identifier string
     * @param string $pathCode
     * @return array
     * @throws \RuntimeException
     */
    public function check($identifier, $pathCode = 'productMarkers'): array
    {
        $marker = false;
        $identifierPresent = false;

        $paths = $this->localeConfig[$pathCode] ?? [];
        if (!$paths) {
            throw new \RuntimeException('no marker paths in the config for '. $pathCode . $this->localeConfig['settings']['locale'] ?? '');
        }
        foreach ($paths as $id => $path) {
            $pathMarker = $this->getFirstElementByXpath($path);
            if ($pathMarker && isset($pathMarker->textContent)) {
                $marker = $id;
                if ($identifier === trim($pathMarker->textContent)) {
                    $identifierPresent = true;
                }
                return [$marker, $identifierPresent];
            }
        }
        return [$marker, $identifierPresent];
    }

    /**
     * @param $merchantId
     * @return array
     */
    public function checkMerchant($merchantId): array
    {
        return $this->check($merchantId, 'merchantMarkers');
    }



}
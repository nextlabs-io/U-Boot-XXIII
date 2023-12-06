<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 27.09.2017
 * Time: 15:23
 */

namespace Parser\Controller;

use Parser\Factory\ProxyFactory;
use Parser\Model\Amazon\ProductMarker;
use Parser\Model\Helper\Config;
use Parser\Model\Helper\Logger;
use Parser\Model\Html\ContentCollector;
use Parser\Model\Web\Proxy;
use Parser\Model\Web\UserAgent;
use Laminas\View\Model\ViewModel;


class StatusController extends AbstractController
{
    protected $db;
    private $container;
    private $proxy;
    private $userAgent;

    public function __construct(Config $config, $container)
    {
        $this->container = $container;
        $this->proxy = $this->container->get(Proxy::class);
        /**
         * @var $userAgent UserAgent
         */
        $userAgent = $this->container->get(UserAgent::class);
        $this->userAgent = $userAgent;
        $this->config = $config;
        $this->db = $config->getDb();

    }

    public function indexAction()
    {
        $data = [
        ];

        return new ViewModel([
            'data' => $data,
        ]);
    }


    public function testAction()
    {
        $settings = $this->config->getConfig('settings');
        $data = [];
        $dataPath = getcwd() . '/' . ($settings['testContentPath'] ?? 'data/parser/test');
        $startTime = time();
        $cc = new ContentCollector($dataPath, 1);

        $locales = ['ca', 'com', 'it', 'uk'];
        $types = ['product'];

        foreach ($locales as $locale) {
            $localeConfig = $this->config->getLocaleConfig($locale);
            $folder = $dataPath . '/product/' . $locale . '/';
            $limit = 0;
            if (is_dir($folder)) {
                $data[] = ('<b>starting product data check for Locale:'. $locale.'</b>');

                foreach (new \DirectoryIterator($folder) as $file) {
                    $limit ++;
                    if($limit > 100) continue;
                    if ($file->isFile()) {
                        $fileName = $file->getFilename();
                        $asin = str_replace('.html', '', $fileName);
                        $content = file_get_contents($folder . $fileName);
                        if(strlen($content) < 10000) {
                            $data[] = ($asin . '; content size: ' . strlen($content) );
                        }
                        else {
                            $stockHtml = '';
                            $logger = new Logger($this->proxy->getDb(), $settings);
                            $pm = new ProductMarker($content, $localeConfig);
                            [$marker, $asinCheck] = $pm->check($asin);
                            if (!$marker) {
                                $stock = 0;
                                $stockHtml = 'missing product marker';
                            } elseif (!$asinCheck) {
                                $stock = 0;
                                $stockHtml = 'wrong variation content received';
                            } else {
                                $stockHtml = 'found!';
                            }
                            $data[] = ($asin . '; content size: ' . strlen($content) . '; marker: ' . $marker . '; result: ' . $stockHtml);
                        }
                    }
                }
            }
        }
        $data[]= 'execution time: '. (time() - $startTime);
        return new ViewModel([
            'data' => $data,
        ]);
    }

}
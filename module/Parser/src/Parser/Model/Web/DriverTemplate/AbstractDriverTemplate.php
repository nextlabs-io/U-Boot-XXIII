<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 13.12.2020
 * Time: 12:08
 */

namespace Parser\Model\Web\DriverTemplate;

use RuntimeException;


/*
 * TODO change template usage to modify config file with the same executable script file.
 */

class AbstractDriverTemplate
{
    public $contentFilePath;
    public $url;
    // userAgent is not used for now.
    public $userAgent;
    public $proxy;
    public $driverPath;
    public $eventTimeout = 60;
    public $proxyHost;
    public $proxyPort;
    /**
     * @var string|null
     */
    public $proxyType;
    /**
     * @var string
     */
    public $scriptPath;

    public function __construct($scriptPath = null)
    {
        $this->scriptPath = $scriptPath ?: __DIR__ . '/../../../../../view/driver/';

    }

    /**
     * @param string $scriptName
     * @return string
     */
    public function getBrowserScript($scriptName = 'cdiscount'): string
    {
        // $scriptName = 'cdiscount' | 'nodescript.ts' - with or without extension, if without - .py script will be taken
        $view = new \Laminas\View\Renderer\PhpRenderer();
        $resolver = new \Laminas\View\Resolver\TemplateMapResolver();
        $scriptName = (strpos($scriptName, '.') !== false) ? $scriptName : $scriptName . '.py';
        $resolver->setMap([
            $scriptName => $this->scriptPath . $scriptName
        ]);
        $view->setResolver($resolver);
        $viewModel = new \Laminas\View\Model\ViewModel();
        $viewModel->setTemplate($scriptName)
            ->setVariables(['dataObject' => $this]);
        $result = $view->render($viewModel);
        return $result;
    }

    /**
     * Put all required params to the template file
     * @param array $data
     * @return $this
     */
    public function setData($data)
    {

        if (!isset($data['url'])) {
            throw new RuntimeException('no url in the selenium request');
        }
        if (!isset($data['contentFilePath'])) {
            throw new RuntimeException('no contentFilePath in the selenium request');
        }
        if (!isset($data['driverPath'])) {
            throw new RuntimeException('no driverPath in the selenium request');
        }
        if (!isset($data['proxyHost'])) {
            throw new RuntimeException('no proxyHost in the selenium request');
        }
        if (!isset($data['proxyPort'])) {
            throw new RuntimeException('no proxyPort in the selenium request');
        }
        $this->url = $data['url'];
        $this->contentFilePath = $data['contentFilePath'];
        $this->driverPath = $data['driverPath'];
        $proxy = $data['proxyHost'] . ':' . $data['proxyPort'];
        $proxyType = $this->getProxyType($data);

        $this->proxy = $proxy;
        $this->proxyHost = $data['proxyHost'];
        $this->proxyPort = $data['proxyPort'];
        $this->proxyType = $proxyType;
        $this->userAgent = $data['userAgent'] ?? null;
        return $this;
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function getProxyType($data): string
    {
        return $data['proxyType'] ?? 'http';
    }

}
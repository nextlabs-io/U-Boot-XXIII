<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 25.09.2020
 * Time: 10:59
 */

namespace Parser\Model\Web\Phantom;


class PhantomTemplate
{
    public $contentFilePath;
    public $requestFilePath;
    public $url;
    public $userAgent;



    /**
     * @return string
     */
    public function getBrowserScript(): string
    {


        $view = new \Laminas\View\Renderer\PhpRenderer();
        $resolver = new \Laminas\View\Resolver\TemplateMapResolver();
        $resolver->setMap([
            'phantom' => __DIR__ . '/../../../../../view/layout/phantom.phtml'
        ]);
        $view->setResolver($resolver);

        $viewModel = new \Laminas\View\Model\ViewModel();
        $viewModel->setTemplate('phantom')
            ->setVariables(['phantomData' => $this]);

        $result = $view->render($viewModel);

        return $result;

    }

}
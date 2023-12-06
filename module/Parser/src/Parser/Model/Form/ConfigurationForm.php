<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 24.07.18
 * Time: 11:21
 */

namespace Parser\Model\Form;


use Parser\Model\Helper\Config;
use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigurationForm extends Form
{
    private $config;

    public function __construct(Config $config, $name = null, $options = [])
    {
        $this->config = $config;
        parent::__construct($name, $options);
        $this->addElements();
    }

    public function addElements()
    {
        $locales = $this->config->getLocales();
        foreach ($locales as $locale) {
            $brand = new Element\Textarea('brand[' . $locale . ']');
            $brand->setLabel($locale . ' brands black list')
                ->setAttribute('id', 'brand[' . $locale . ']')
                ->setAttribute('class', 'full_width');
            $this->add($brand);
        }
    }
}
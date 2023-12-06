<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 24.07.18
 * Time: 11:21
 */

namespace eBay\Model\Form;


use Laminas\Form\Element;
use Laminas\Form\Form;

class SearchForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->addElements();
    }

    public function addElements()
    {
        $search = new Element\Text('search');
        $search->setLabel('Search')
            ->setAttribute('id', 'search')
            ->setAttribute('class', 'form-control col-md-7 col-xs-12');
        $this->add($search);
    }


}
<?php
/**
 * Copyright WebExperiment.info
 * Created by Creator.
 * Date: 22.06.2020
 * Time: 19:42
 */

namespace Cdiscount\Model\Form;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\InputFilter;

class CategoryForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->setAttribute('class', 'form-horizontal form-label-left');
        $customFields = $options['uploadFormFields'] ?? [];
        $this->addElements($customFields);
    }

    public function addElements($customFields = []): void
    {
        // File Input

        $url = new Element\Textarea('category_id');
        $url->setLabel('Category Id (like BB_15879042), multiple categories comma or space separated are allowed')
            ->setAttribute('id', 'category_id')
            ->setAttribute('rows', '3')
            ->setAttribute('class', 'form-control');
        $this->add($url);

        $this->setInputFilter($this->createInputFilter());
    }

    /**
     * @return InputFilter\InputFilter
     */
    public function createInputFilter(): \Laminas\InputFilter\InputFilter
    {
        $inputFilter = new InputFilter\InputFilter();

        $url = new InputFilter\Input('category_id');
        $url->setRequired(true);
        $inputFilter->add($url);


        return $inputFilter;
    }
}
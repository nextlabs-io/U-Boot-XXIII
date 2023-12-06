<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 24.07.18
 * Time: 11:21
 */

namespace Cdiscount\Model\Form;


use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\InputFilter;

class SearchForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->setAttribute('class', 'form-horizontal form-label-left');
        $this->setAttribute('id', 'category-form');
        $customFields = $options['uploadFormFields'] ?? [];
        $this->addElements($customFields);
    }

    public function addElements($customFields = []): void
    {
        // File Input

        $url = new Element\Textarea('category_url');
        $url->setLabel('Category Url')
            ->setAttribute('id', 'category_url')
            ->setAttribute('rows', '3')
            ->setAttribute('class', 'form-control');
        $this->add($url);

        $file = new Element\File('category_list');
        $file->setLabel('Category list')
            ->setAttribute('data-parsley-id', '1')
            ->setAttribute('class', 'form-file')
            ->setAttribute('id', 'category_list');
        $this->add($file);

//        $this->setInputFilter($this->createInputFilter());



        if (!empty($customFields)) {
            foreach ($customFields as $id => $customField) {
                $field = new Element\Text($id);
                $field->setLabel($customField['name'])
                    ->setAttribute('id', $id)
                    ->setAttribute('class', 'form-control col-md-7 col-xs-12 parsley-success')
                    ->setAttribute('data', 'custom');

                $this->add($field);
            }
        }
    }

    public function createInputFilter()
    {
        $inputFilter = new InputFilter\InputFilter();

//        $url = new InputFilter\Input('category_url');
//        $url->setRequired(true);
//        $inputFilter->add($url);


        return $inputFilter;
    }

}
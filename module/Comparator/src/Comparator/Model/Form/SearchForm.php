<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 24.07.18
 * Time: 11:21
 */

namespace Comparator\Model\Form;


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
        $localeList = $options['locales'] ?? [];
        $this->addElements($customFields, $localeList);
    }

    public function addElements($customFields = [], $localeList = []): void
    {
        // File Input


        $file = new Element\File('product_list');
        $file->setLabel('Item list')
            ->setAttribute('data-parsley-id', '1')
            ->setAttribute('class', 'form-file')
            ->setAttribute('id', 'product_list');
        $this->add($file);

        $select = new Element\Select('locale', $localeList);
        $select->setLabel('Choose locale')
            ->setAttribute('id', 'locale-select')
            ->setAttribute('data-parsley-id', '3')
            ->setAttribute('class', 'form-control col-md-1 col-xs-1 parsley-success')
            ->setOptions(['options' => $localeList]);
        $this->add($select);

        $url = new Element\Textarea('product_ean');
        $url->setLabel('EAN list')
            ->setAttribute('id', 'product_ean')
            ->setAttribute('rows', '3')
            ->setAttribute('class', 'form-control');
        $this->add($url);

        $url = new Element\Textarea('product_upc');
        $url->setLabel('UPC list')
            ->setAttribute('id', 'product_upc')
            ->setAttribute('rows', '3')
            ->setAttribute('class', 'form-control');
        $this->add($url);

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
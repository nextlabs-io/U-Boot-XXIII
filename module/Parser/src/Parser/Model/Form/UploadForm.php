<?php
/**
 * Copyright WebExperiment.info
 * Created by ernazar.
 * Date: 24.09.2017
 * Time: 0:22
 */

namespace Parser\Model\Form;

use Parser\Model\Configuration\ProductSyncable;
use Laminas\Form\Element;
use Laminas\Form\Form;

class UploadForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->setAttribute('class', 'form-horizontal form-label-left');
        $localeList = $options['locales'];
        $customFields = $options['uploadFormFields'] ?? [];
        $this->addElements($localeList, $customFields);
    }

    public function addElements(array $localeList, array $customFields)
    {
        // File Input
        $file = new Element\File('asins');
        $file->setLabel('ASINs list')
            ->setAttribute('data-parsley-id', '1')
            ->setAttribute('class', 'form-file')
            ->setAttribute('id', 'asins');
        $this->add($file);

        $textarea = new Element\Textarea('asins_list');
        $textarea->setLabel('and/or put ASINs here (comma, space, semicolon, next line separated)')
            ->setAttribute('id', 'asins_list')
            ->setAttribute('data-parsley-id', '2')
            ->setAttribute('class', 'form-control col-md-7 col-xs-12 parsley-success');

        $this->add($textarea);

        $select = new Element\Select('locale', $localeList);
        $select->setLabel('Choose locale')
            ->setAttribute('id', 'locale-select')
            ->setAttribute('data-parsley-id', '3')
            ->setAttribute('class', 'form-control col-md-1 col-xs-1 parsley-success')
            ->setOptions(['options' => $localeList]);
        $this->add($select);

        $firstElement = ['' => '--'];
        $syncStatusList = ProductSyncable::getOptions();
        $syncStatusList = $firstElement + $syncStatusList;

        $select = new Element\Select('syncable', $syncStatusList);
        $select->setLabel('Choose Sync Status')
            ->setAttribute('id', 'syncable-select')
            ->setAttribute('data-parsley-id', '3')
            ->setAttribute('class', 'form-control col-md-1 col-xs-1 parsley-success')
            ->setOptions(['options' => $syncStatusList]);

        $this->add($select);

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
//        $form = new Form()
    }
}

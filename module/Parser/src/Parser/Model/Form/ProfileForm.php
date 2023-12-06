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

class ProfileForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $customFields = $options['profileSettings'] ?? [];
        $this->addElements($customFields);

    }

    public function addElements($customFields = [], $locales = [])
    {
        // File Input

        $login = new Element\Text('login');
        $login->setLabel('Login')
            ->setAttribute('id', 'login')
            ->setAttribute('class', 'form-control col-md-7 col-xs-12');
        $this->add($login);

        /*
               $change = new Element\Checkbox('change-password');
                $change->setLabel('Change Password')
                    ->setAttribute('id', 'change-password');
                $this->add($change);*/

        $pass = new Element\Password('password');
        $pass->setLabel('Password')
            ->setAttribute('id', 'password')
            ->setAttribute('class', 'form-control col-md-7 col-xs-12');
        $this->add($pass);

        $pass2 = new Element\Password('password2');
        $pass2->setLabel('Repeat Password')
            ->setAttribute('id', 'password2')
            ->setAttribute('class', 'form-control col-md-7 col-xs-12');
        $this->add($pass2);

        $email = new Element\Text('email');
        $email->setLabel('Email (if you wish to get email notifications)')
            ->setAttribute('id', 'email')
            ->setAttribute('class', 'form-control col-md-7 col-xs-12');
        $this->add($email);

        if (!empty($customFields)) {

            foreach ($customFields as $id => $customField) {
                $type = $customField['type'] ?? null;

                    $field = new Element\Text($id);

                    $field->setLabel($customField['name'])
                        ->setAttribute('id', $id)
                        ->setAttribute('class', 'form-control col-md-7 col-xs-12 parsley-success')
                        ->setAttribute('data', $type !== 'checkbox' ? 'custom' : 'manually-placed');
                    if($customField['comment'] ?? null){
                        $field->setAttribute('data-comment', $customField['comment']);
                    }
                    $this->add($field);
            }
        }

    }


}
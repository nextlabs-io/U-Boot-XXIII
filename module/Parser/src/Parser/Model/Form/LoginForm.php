<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 24.07.18
 * Time: 11:21
 */

namespace Parser\Model\Form;


use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\InputFilter;

class LoginForm extends Form
{
    public function __construct($name = null, $options = [])
    {
        $options['class'] = "form-horizontal form-label-left";
        $options['id'] = 'login-form';
        $options['data-parsley-validate'] = "";
        parent::__construct($name, $options);
        $this->addElements();
    }

    public function addElements()
    {
        $login = new Element\Text('login');
        $login->setLabel('Login')
            ->setAttribute('id', 'login')
            ->setAttribute('data-parsley-id', '1')
            ->setAttribute('required', 'required')
            ->setAttribute('placeholder', 'Username')
            ->setAttribute('class', 'form-control');

        $this->add($login);

        $pass = new Element\Password('password');
        $pass->setLabel('Password')
            ->setAttribute('id', 'password')
            ->setAttribute('data-parsley-id', '2')
            ->setAttribute('placeholder', 'Password')
            ->setAttribute('required', 'required')
            ->setAttribute('class', 'form-control col-md-7 col-xs-12 parsley-success');
        $this->add($pass);
        $this->setInputFilter($this->createInputFilter());
    }

    public function createInputFilter()
    {
        $inputFilter = new InputFilter\InputFilter();

        //username
        $username = new InputFilter\Input('login');
        $username->setRequired(true);
        $inputFilter->add($username);

        //password
        $password = new InputFilter\Input('password');
        $password->setRequired(true);
        $inputFilter->add($password);

        return $inputFilter;
    }

}
<?php
return [
    'service_manager' => [
        'aliases' => [
            'Zend\\Mail\\Protocol\\SmtpPluginManager' => 'Laminas\\Mail\\Protocol\\SmtpPluginManager',
            'Laminas\\Session\\SessionManager' => 'Laminas\\Session\\ManagerInterface',
            'Zend\\Session\\SessionManager' => 'Laminas\\Session\\SessionManager',
            'Zend\\Session\\Config\\ConfigInterface' => 'Laminas\\Session\\Config\\ConfigInterface',
            'Zend\\Session\\ManagerInterface' => 'Laminas\\Session\\ManagerInterface',
            'Zend\\Session\\Storage\\StorageInterface' => 'Laminas\\Session\\Storage\\StorageInterface',
            'MvcTranslator' => 'Laminas\\Mvc\\I18n\\Translator',
            'Zend\\Mvc\\I18n\\Translator' => 'Laminas\\Mvc\\I18n\\Translator',
            'Zend\\Log\\Logger' => 'Laminas\\Log\\Logger',
            'Laminas\\Form\\Annotation\\AnnotationBuilder' => 'FormAnnotationBuilder',
            'Laminas\\Form\\Annotation\\AttributeBuilder' => 'FormAttributeBuilder',
            'Laminas\\Form\\FormElementManager' => 'FormElementManager',
            'Laminas\\Db\\Adapter\\Adapter' => 'Laminas\\Db\\Adapter\\AdapterInterface',
            'Zend\\Db\\Adapter\\AdapterInterface' => 'Laminas\\Db\\Adapter\\AdapterInterface',
            'Zend\\Db\\Adapter\\Adapter' => 'Laminas\\Db\\Adapter\\Adapter',
            'HttpRouter' => 'Laminas\\Router\\Http\\TreeRouteStack',
            'router' => 'Laminas\\Router\\RouteStackInterface',
            'Router' => 'Laminas\\Router\\RouteStackInterface',
            'RoutePluginManager' => 'Laminas\\Router\\RoutePluginManager',
            'Zend\\Router\\Http\\TreeRouteStack' => 'Laminas\\Router\\Http\\TreeRouteStack',
            'Zend\\Router\\RoutePluginManager' => 'Laminas\\Router\\RoutePluginManager',
            'Zend\\Router\\RouteStackInterface' => 'Laminas\\Router\\RouteStackInterface',
            'ValidatorManager' => 'Laminas\\Validator\\ValidatorPluginManager',
            'Zend\\Validator\\ValidatorPluginManager' => 'Laminas\\Validator\\ValidatorPluginManager'
        ],
        'factories' => [
            'Laminas\\Mail\\Protocol\\SmtpPluginManager' => 'Laminas\\Mail\\Protocol\\SmtpPluginManagerFactory',
            'Laminas\\Session\\Config\\ConfigInterface' => 'Laminas\\Session\\Service\\SessionConfigFactory',
            'Laminas\\Session\\ManagerInterface' => 'Laminas\\Session\\Service\\SessionManagerFactory',
            'Laminas\\Session\\Storage\\StorageInterface' => 'Laminas\\Session\\Service\\StorageFactory',
            'Laminas\\Mvc\\I18n\\Translator' => 'Laminas\\Mvc\\I18n\\TranslatorFactory',
            'Laminas\\Log\\Logger' => 'Laminas\\Log\\LoggerServiceFactory',
            'LogFilterManager' => 'Laminas\\Log\\FilterPluginManagerFactory',
            'LogFormatterManager' => 'Laminas\\Log\\FormatterPluginManagerFactory',
            'LogProcessorManager' => 'Laminas\\Log\\ProcessorPluginManagerFactory',
            'LogWriterManager' => 'Laminas\\Log\\WriterPluginManagerFactory',
            'FormAnnotationBuilder' => 'Laminas\\Form\\Annotation\\BuilderAbstractFactory',
            'FormAttributeBuilder' => 'Laminas\\Form\\Annotation\\BuilderAbstractFactory',
            'FormElementManager' => 'Laminas\\Form\\FormElementManagerFactory',
            'Laminas\\Db\\Adapter\\AdapterInterface' => 'Laminas\\Db\\Adapter\\AdapterServiceFactory',
            'Laminas\\Cache\\Storage\\AdapterPluginManager' => 'Laminas\\Cache\\Service\\StorageAdapterPluginManagerFactory',
            'Laminas\\Cache\\Storage\\PluginManager' => 'Laminas\\Cache\\Service\\StoragePluginManagerFactory',
            'Laminas\\Cache\\Service\\StoragePluginFactory' => 'Laminas\\Cache\\Service\\StoragePluginFactoryFactory',
            'Laminas\\Cache\\Service\\StoragePluginFactoryInterface' => 'Laminas\\Cache\\Service\\StoragePluginFactoryFactory',
            'Laminas\\Cache\\Service\\StorageAdapterFactory' => 'Laminas\\Cache\\Service\\StorageAdapterFactoryFactory',
            'Laminas\\Cache\\Service\\StorageAdapterFactoryInterface' => 'Laminas\\Cache\\Service\\StorageAdapterFactoryFactory',
            'Laminas\\Cache\\Command\\DeprecatedStorageFactoryConfigurationCheckCommand' => 'Laminas\\Cache\\Command\\DeprecatedStorageFactoryConfigurationCheckCommandFactory',
            'Laminas\\Router\\Http\\TreeRouteStack' => 'Laminas\\Router\\Http\\HttpRouterFactory',
            'Laminas\\Router\\RoutePluginManager' => 'Laminas\\Router\\RoutePluginManagerFactory',
            'Laminas\\Router\\RouteStackInterface' => 'Laminas\\Router\\RouterFactory',
            'Laminas\\Validator\\ValidatorPluginManager' => 'Laminas\\Validator\\ValidatorPluginManagerFactory',
            'Parser\\Model\\Web\\Proxy' => 'Parser\\Factory\\ProxyFactory',
            'Parser\\Model\\Web\\UserAgent' => 'Parser\\Factory\\UserAgentFactory',
            'Parser\\Model\\Helper\\Config' => 'Parser\\Factory\\ConfigFactory',
            'Parser\\Model\\Amazon\\Search\\Product' => 'Parser\\Factory\\SearchProductFactory',
            'Laminas\\Session\\SessionManager' => 'Laminas\\Session\\Service\\SessionManagerFactory',
            'Laminas\\Db\\Adapter\\Adapter' => 'Laminas\\Db\\Adapter\\AdapterServiceFactory'
        ],
        'abstract_factories' => [
            'Laminas\\Session\\Service\\ContainerAbstractServiceFactory',
            'Laminas\\Log\\LoggerAbstractServiceFactory',
            'Laminas\\Log\\PsrLoggerAbstractAdapterFactory',
            'Laminas\\Form\\FormAbstractServiceFactory',
            'Laminas\\Db\\Adapter\\AdapterAbstractServiceFactory',
            'Laminas\\Cache\\Service\\StorageCacheAbstractServiceFactory'
        ],
        'delegators' => [
            'HttpRouter' => [
                'Laminas\\Mvc\\I18n\\Router\\HttpRouterDelegatorFactory'
            ],
            'Laminas\\Router\\Http\\TreeRouteStack' => [
                'Laminas\\Mvc\\I18n\\Router\\HttpRouterDelegatorFactory'
            ]
        ],
        'invokables' => []
    ],
    'controller_plugins' => [
        'aliases' => [
            'prg' => 'Laminas\\Mvc\\Plugin\\Prg\\PostRedirectGet',
            'PostRedirectGet' => 'Laminas\\Mvc\\Plugin\\Prg\\PostRedirectGet',
            'postRedirectGet' => 'Laminas\\Mvc\\Plugin\\Prg\\PostRedirectGet',
            'postredirectget' => 'Laminas\\Mvc\\Plugin\\Prg\\PostRedirectGet',
            'Laminas\\Mvc\\Controller\\Plugin\\PostRedirectGet' => 'Laminas\\Mvc\\Plugin\\Prg\\PostRedirectGet',
            'Zend\\Mvc\\Controller\\Plugin\\PostRedirectGet' => 'Laminas\\Mvc\\Plugin\\Prg\\PostRedirectGet',
            'Zend\\Mvc\\Plugin\\Prg\\PostRedirectGet' => 'Laminas\\Mvc\\Plugin\\Prg\\PostRedirectGet',
            'identity' => 'Laminas\\Mvc\\Plugin\\Identity\\Identity',
            'Identity' => 'Laminas\\Mvc\\Plugin\\Identity\\Identity',
            'Laminas\\Mvc\\Controller\\Plugin\\Identity' => 'Laminas\\Mvc\\Plugin\\Identity\\Identity',
            'Zend\\Mvc\\Controller\\Plugin\\Identity' => 'Laminas\\Mvc\\Plugin\\Identity\\Identity',
            'Zend\\Mvc\\Plugin\\Identity\\Identity' => 'Laminas\\Mvc\\Plugin\\Identity\\Identity',
            'flashmessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\FlashMessenger',
            'flashMessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\FlashMessenger',
            'FlashMessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\FlashMessenger',
            'Laminas\\Mvc\\Controller\\Plugin\\FlashMessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\FlashMessenger',
            'Zend\\Mvc\\Controller\\Plugin\\FlashMessenger' => 'Laminas\\Mvc\\Controller\\Plugin\\FlashMessenger',
            'Zend\\Mvc\\Plugin\\FlashMessenger\\FlashMessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\FlashMessenger',
            'fileprg' => 'Laminas\\Mvc\\Plugin\\FilePrg\\FilePostRedirectGet',
            'FilePostRedirectGet' => 'Laminas\\Mvc\\Plugin\\FilePrg\\FilePostRedirectGet',
            'filePostRedirectGet' => 'Laminas\\Mvc\\Plugin\\FilePrg\\FilePostRedirectGet',
            'filepostredirectget' => 'Laminas\\Mvc\\Plugin\\FilePrg\\FilePostRedirectGet',
            'Laminas\\Mvc\\Controller\\Plugin\\FilePostRedirectGet' => 'Laminas\\Mvc\\Plugin\\FilePrg\\FilePostRedirectGet',
            'Zend\\Mvc\\Controller\\Plugin\\FilePostRedirectGet' => 'Laminas\\Mvc\\Controller\\Plugin\\FilePostRedirectGet',
            'Zend\\Mvc\\Plugin\\FilePrg\\FilePostRedirectGet' => 'Laminas\\Mvc\\Plugin\\FilePrg\\FilePostRedirectGet'
        ],
        'factories' => [
            'Laminas\\Mvc\\Plugin\\Prg\\PostRedirectGet' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Mvc\\Plugin\\Identity\\Identity' => 'Laminas\\Mvc\\Plugin\\Identity\\IdentityFactory',
            'Laminas\\Mvc\\Plugin\\FlashMessenger\\FlashMessenger' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Mvc\\Plugin\\FilePrg\\FilePostRedirectGet' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory'
        ]
    ],
    'view_helpers' => [
        'aliases' => [
            'flashmessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\View\\Helper\\FlashMessenger',
            'flashMessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\View\\Helper\\FlashMessenger',
            'FlashMessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\View\\Helper\\FlashMessenger',
            'Zend\\Mvc\\Plugin\\FlashMessenger\\View\\Helper\\FlashMessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\View\\Helper\\FlashMessenger',
            'zendviewhelperflashmessenger' => 'laminasviewhelperflashmessenger',
            'form' => 'Laminas\\Form\\View\\Helper\\Form',
            'Form' => 'Laminas\\Form\\View\\Helper\\Form',
            'formbutton' => 'Laminas\\Form\\View\\Helper\\FormButton',
            'form_button' => 'Laminas\\Form\\View\\Helper\\FormButton',
            'formButton' => 'Laminas\\Form\\View\\Helper\\FormButton',
            'FormButton' => 'Laminas\\Form\\View\\Helper\\FormButton',
            'formcaptcha' => 'Laminas\\Form\\View\\Helper\\FormCaptcha',
            'form_captcha' => 'Laminas\\Form\\View\\Helper\\FormCaptcha',
            'formCaptcha' => 'Laminas\\Form\\View\\Helper\\FormCaptcha',
            'FormCaptcha' => 'Laminas\\Form\\View\\Helper\\FormCaptcha',
            'captchadumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'captcha_dumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'captcha/dumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'CaptchaDumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'captchaDumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'formcaptchadumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'form_captcha_dumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'formCaptchaDumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'FormCaptchaDumb' => 'Laminas\\Form\\View\\Helper\\Captcha\\Dumb',
            'captchafiglet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'captcha/figlet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'captcha_figlet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'captchaFiglet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'CaptchaFiglet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'formcaptchafiglet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'form_captcha_figlet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'formCaptchaFiglet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'FormCaptchaFiglet' => 'Laminas\\Form\\View\\Helper\\Captcha\\Figlet',
            'captchaimage' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'captcha/image' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'captcha_image' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'captchaImage' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'CaptchaImage' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'formcaptchaimage' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'form_captcha_image' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'formCaptchaImage' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'FormCaptchaImage' => 'Laminas\\Form\\View\\Helper\\Captcha\\Image',
            'captcharecaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'captcha/recaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'captcha_recaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'captchaRecaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'CaptchaRecaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'formcaptcharecaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'form_captcha_recaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'formCaptchaRecaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'FormCaptchaRecaptcha' => 'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha',
            'formcheckbox' => 'Laminas\\Form\\View\\Helper\\FormCheckbox',
            'form_checkbox' => 'Laminas\\Form\\View\\Helper\\FormCheckbox',
            'formCheckbox' => 'Laminas\\Form\\View\\Helper\\FormCheckbox',
            'FormCheckbox' => 'Laminas\\Form\\View\\Helper\\FormCheckbox',
            'formcollection' => 'Laminas\\Form\\View\\Helper\\FormCollection',
            'form_collection' => 'Laminas\\Form\\View\\Helper\\FormCollection',
            'formCollection' => 'Laminas\\Form\\View\\Helper\\FormCollection',
            'FormCollection' => 'Laminas\\Form\\View\\Helper\\FormCollection',
            'formcolor' => 'Laminas\\Form\\View\\Helper\\FormColor',
            'form_color' => 'Laminas\\Form\\View\\Helper\\FormColor',
            'formColor' => 'Laminas\\Form\\View\\Helper\\FormColor',
            'FormColor' => 'Laminas\\Form\\View\\Helper\\FormColor',
            'formdate' => 'Laminas\\Form\\View\\Helper\\FormDate',
            'form_date' => 'Laminas\\Form\\View\\Helper\\FormDate',
            'formDate' => 'Laminas\\Form\\View\\Helper\\FormDate',
            'FormDate' => 'Laminas\\Form\\View\\Helper\\FormDate',
            'formdatetime' => 'Laminas\\Form\\View\\Helper\\FormDateTime',
            'form_date_time' => 'Laminas\\Form\\View\\Helper\\FormDateTime',
            'formDateTime' => 'Laminas\\Form\\View\\Helper\\FormDateTime',
            'FormDateTime' => 'Laminas\\Form\\View\\Helper\\FormDateTime',
            'formdatetimelocal' => 'Laminas\\Form\\View\\Helper\\FormDateTimeLocal',
            'form_date_time_local' => 'Laminas\\Form\\View\\Helper\\FormDateTimeLocal',
            'formDateTimeLocal' => 'Laminas\\Form\\View\\Helper\\FormDateTimeLocal',
            'FormDateTimeLocal' => 'Laminas\\Form\\View\\Helper\\FormDateTimeLocal',
            'formdatetimeselect' => 'Laminas\\Form\\View\\Helper\\FormDateTimeSelect',
            'form_date_time_select' => 'Laminas\\Form\\View\\Helper\\FormDateTimeSelect',
            'formDateTimeSelect' => 'Laminas\\Form\\View\\Helper\\FormDateTimeSelect',
            'FormDateTimeSelect' => 'Laminas\\Form\\View\\Helper\\FormDateTimeSelect',
            'formdateselect' => 'Laminas\\Form\\View\\Helper\\FormDateSelect',
            'form_date_select' => 'Laminas\\Form\\View\\Helper\\FormDateSelect',
            'formDateSelect' => 'Laminas\\Form\\View\\Helper\\FormDateSelect',
            'FormDateSelect' => 'Laminas\\Form\\View\\Helper\\FormDateSelect',
            'form_element' => 'Laminas\\Form\\View\\Helper\\FormElement',
            'formelement' => 'Laminas\\Form\\View\\Helper\\FormElement',
            'formElement' => 'Laminas\\Form\\View\\Helper\\FormElement',
            'FormElement' => 'Laminas\\Form\\View\\Helper\\FormElement',
            'form_element_errors' => 'Laminas\\Form\\View\\Helper\\FormElementErrors',
            'formelementerrors' => 'Laminas\\Form\\View\\Helper\\FormElementErrors',
            'formElementErrors' => 'Laminas\\Form\\View\\Helper\\FormElementErrors',
            'FormElementErrors' => 'Laminas\\Form\\View\\Helper\\FormElementErrors',
            'form_email' => 'Laminas\\Form\\View\\Helper\\FormEmail',
            'formemail' => 'Laminas\\Form\\View\\Helper\\FormEmail',
            'formEmail' => 'Laminas\\Form\\View\\Helper\\FormEmail',
            'FormEmail' => 'Laminas\\Form\\View\\Helper\\FormEmail',
            'form_file' => 'Laminas\\Form\\View\\Helper\\FormFile',
            'formfile' => 'Laminas\\Form\\View\\Helper\\FormFile',
            'formFile' => 'Laminas\\Form\\View\\Helper\\FormFile',
            'FormFile' => 'Laminas\\Form\\View\\Helper\\FormFile',
            'formfileapcprogress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileApcProgress',
            'form_file_apc_progress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileApcProgress',
            'formFileApcProgress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileApcProgress',
            'FormFileApcProgress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileApcProgress',
            'formfilesessionprogress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileSessionProgress',
            'form_file_session_progress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileSessionProgress',
            'formFileSessionProgress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileSessionProgress',
            'FormFileSessionProgress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileSessionProgress',
            'formfileuploadprogress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileUploadProgress',
            'form_file_upload_progress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileUploadProgress',
            'formFileUploadProgress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileUploadProgress',
            'FormFileUploadProgress' => 'Laminas\\Form\\View\\Helper\\File\\FormFileUploadProgress',
            'formhidden' => 'Laminas\\Form\\View\\Helper\\FormHidden',
            'form_hidden' => 'Laminas\\Form\\View\\Helper\\FormHidden',
            'formHidden' => 'Laminas\\Form\\View\\Helper\\FormHidden',
            'FormHidden' => 'Laminas\\Form\\View\\Helper\\FormHidden',
            'formimage' => 'Laminas\\Form\\View\\Helper\\FormImage',
            'form_image' => 'Laminas\\Form\\View\\Helper\\FormImage',
            'formImage' => 'Laminas\\Form\\View\\Helper\\FormImage',
            'FormImage' => 'Laminas\\Form\\View\\Helper\\FormImage',
            'forminput' => 'Laminas\\Form\\View\\Helper\\FormInput',
            'form_input' => 'Laminas\\Form\\View\\Helper\\FormInput',
            'formInput' => 'Laminas\\Form\\View\\Helper\\FormInput',
            'FormInput' => 'Laminas\\Form\\View\\Helper\\FormInput',
            'formlabel' => 'Laminas\\Form\\View\\Helper\\FormLabel',
            'form_label' => 'Laminas\\Form\\View\\Helper\\FormLabel',
            'formLabel' => 'Laminas\\Form\\View\\Helper\\FormLabel',
            'FormLabel' => 'Laminas\\Form\\View\\Helper\\FormLabel',
            'formmonth' => 'Laminas\\Form\\View\\Helper\\FormMonth',
            'form_month' => 'Laminas\\Form\\View\\Helper\\FormMonth',
            'formMonth' => 'Laminas\\Form\\View\\Helper\\FormMonth',
            'FormMonth' => 'Laminas\\Form\\View\\Helper\\FormMonth',
            'formmonthselect' => 'Laminas\\Form\\View\\Helper\\FormMonthSelect',
            'form_month_select' => 'Laminas\\Form\\View\\Helper\\FormMonthSelect',
            'formMonthSelect' => 'Laminas\\Form\\View\\Helper\\FormMonthSelect',
            'FormMonthSelect' => 'Laminas\\Form\\View\\Helper\\FormMonthSelect',
            'formmulticheckbox' => 'Laminas\\Form\\View\\Helper\\FormMultiCheckbox',
            'form_multi_checkbox' => 'Laminas\\Form\\View\\Helper\\FormMultiCheckbox',
            'formMultiCheckbox' => 'Laminas\\Form\\View\\Helper\\FormMultiCheckbox',
            'FormMultiCheckbox' => 'Laminas\\Form\\View\\Helper\\FormMultiCheckbox',
            'formnumber' => 'Laminas\\Form\\View\\Helper\\FormNumber',
            'form_number' => 'Laminas\\Form\\View\\Helper\\FormNumber',
            'formNumber' => 'Laminas\\Form\\View\\Helper\\FormNumber',
            'FormNumber' => 'Laminas\\Form\\View\\Helper\\FormNumber',
            'formpassword' => 'Laminas\\Form\\View\\Helper\\FormPassword',
            'form_password' => 'Laminas\\Form\\View\\Helper\\FormPassword',
            'formPassword' => 'Laminas\\Form\\View\\Helper\\FormPassword',
            'FormPassword' => 'Laminas\\Form\\View\\Helper\\FormPassword',
            'formradio' => 'Laminas\\Form\\View\\Helper\\FormRadio',
            'form_radio' => 'Laminas\\Form\\View\\Helper\\FormRadio',
            'formRadio' => 'Laminas\\Form\\View\\Helper\\FormRadio',
            'FormRadio' => 'Laminas\\Form\\View\\Helper\\FormRadio',
            'formrange' => 'Laminas\\Form\\View\\Helper\\FormRange',
            'form_range' => 'Laminas\\Form\\View\\Helper\\FormRange',
            'formRange' => 'Laminas\\Form\\View\\Helper\\FormRange',
            'FormRange' => 'Laminas\\Form\\View\\Helper\\FormRange',
            'formreset' => 'Laminas\\Form\\View\\Helper\\FormReset',
            'form_reset' => 'Laminas\\Form\\View\\Helper\\FormReset',
            'formReset' => 'Laminas\\Form\\View\\Helper\\FormReset',
            'FormReset' => 'Laminas\\Form\\View\\Helper\\FormReset',
            'formrow' => 'Laminas\\Form\\View\\Helper\\FormRow',
            'form_row' => 'Laminas\\Form\\View\\Helper\\FormRow',
            'formRow' => 'Laminas\\Form\\View\\Helper\\FormRow',
            'FormRow' => 'Laminas\\Form\\View\\Helper\\FormRow',
            'formsearch' => 'Laminas\\Form\\View\\Helper\\FormSearch',
            'form_search' => 'Laminas\\Form\\View\\Helper\\FormSearch',
            'formSearch' => 'Laminas\\Form\\View\\Helper\\FormSearch',
            'FormSearch' => 'Laminas\\Form\\View\\Helper\\FormSearch',
            'formselect' => 'Laminas\\Form\\View\\Helper\\FormSelect',
            'form_select' => 'Laminas\\Form\\View\\Helper\\FormSelect',
            'formSelect' => 'Laminas\\Form\\View\\Helper\\FormSelect',
            'FormSelect' => 'Laminas\\Form\\View\\Helper\\FormSelect',
            'formsubmit' => 'Laminas\\Form\\View\\Helper\\FormSubmit',
            'form_submit' => 'Laminas\\Form\\View\\Helper\\FormSubmit',
            'formSubmit' => 'Laminas\\Form\\View\\Helper\\FormSubmit',
            'FormSubmit' => 'Laminas\\Form\\View\\Helper\\FormSubmit',
            'formtel' => 'Laminas\\Form\\View\\Helper\\FormTel',
            'form_tel' => 'Laminas\\Form\\View\\Helper\\FormTel',
            'formTel' => 'Laminas\\Form\\View\\Helper\\FormTel',
            'FormTel' => 'Laminas\\Form\\View\\Helper\\FormTel',
            'formtext' => 'Laminas\\Form\\View\\Helper\\FormText',
            'form_text' => 'Laminas\\Form\\View\\Helper\\FormText',
            'formText' => 'Laminas\\Form\\View\\Helper\\FormText',
            'FormText' => 'Laminas\\Form\\View\\Helper\\FormText',
            'formtextarea' => 'Laminas\\Form\\View\\Helper\\FormTextarea',
            'form_text_area' => 'Laminas\\Form\\View\\Helper\\FormTextarea',
            'formTextarea' => 'Laminas\\Form\\View\\Helper\\FormTextarea',
            'formTextArea' => 'Laminas\\Form\\View\\Helper\\FormTextarea',
            'FormTextArea' => 'Laminas\\Form\\View\\Helper\\FormTextarea',
            'formtime' => 'Laminas\\Form\\View\\Helper\\FormTime',
            'form_time' => 'Laminas\\Form\\View\\Helper\\FormTime',
            'formTime' => 'Laminas\\Form\\View\\Helper\\FormTime',
            'FormTime' => 'Laminas\\Form\\View\\Helper\\FormTime',
            'formurl' => 'Laminas\\Form\\View\\Helper\\FormUrl',
            'form_url' => 'Laminas\\Form\\View\\Helper\\FormUrl',
            'formUrl' => 'Laminas\\Form\\View\\Helper\\FormUrl',
            'FormUrl' => 'Laminas\\Form\\View\\Helper\\FormUrl',
            'formweek' => 'Laminas\\Form\\View\\Helper\\FormWeek',
            'form_week' => 'Laminas\\Form\\View\\Helper\\FormWeek',
            'formWeek' => 'Laminas\\Form\\View\\Helper\\FormWeek',
            'FormWeek' => 'Laminas\\Form\\View\\Helper\\FormWeek'
        ],
        'factories' => [
            'Laminas\\Mvc\\Plugin\\FlashMessenger\\View\\Helper\\FlashMessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\View\\Helper\\FlashMessengerFactory',
            'laminasviewhelperflashmessenger' => 'Laminas\\Mvc\\Plugin\\FlashMessenger\\View\\Helper\\FlashMessengerFactory',
            'Laminas\\Form\\View\\Helper\\Form' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormButton' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormCaptcha' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\Captcha\\Dumb' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\Captcha\\Figlet' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\Captcha\\Image' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\Captcha\\ReCaptcha' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormCheckbox' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormCollection' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormColor' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormDate' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormDateTime' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormDateTimeLocal' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormDateTimeSelect' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormDateSelect' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormElement' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormElementErrors' => 'Laminas\\Form\\View\\Helper\\Factory\\FormElementErrorsFactory',
            'Laminas\\Form\\View\\Helper\\FormEmail' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormFile' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\File\\FormFileApcProgress' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\File\\FormFileSessionProgress' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\File\\FormFileUploadProgress' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormHidden' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormImage' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormInput' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormLabel' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormMonth' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormMonthSelect' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormMultiCheckbox' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormNumber' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormPassword' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormRadio' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormRange' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormReset' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormRow' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormSearch' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormSelect' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormSubmit' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormTel' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormText' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormTextarea' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormTime' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormUrl' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory',
            'Laminas\\Form\\View\\Helper\\FormWeek' => 'Laminas\\ServiceManager\\Factory\\InvokableFactory'
        ]
    ],
    'laminas-cli' => [
        'commands' => [
            'laminas-cache:deprecation:check-storage-factory-config' => 'Laminas\\Cache\\Command\\DeprecatedStorageFactoryConfigurationCheckCommand'
        ]
    ],
    'route_manager' => [],
    'router' => [
        'routes' => [
            'home' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\ManagerController',
                        'action' => 'getstat'
                    ]
                ]
            ],
            'profile' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/profile[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\ProfileController',
                        'action' => 'index'
                    ]
                ]
            ],
            'parser' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/parser[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\ListController',
                        'action' => 'index'
                    ]
                ]
            ],
            'parser-index' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/parser/',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\ListController',
                        'action' => 'index'
                    ]
                ]
            ],
            'status' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/status[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\StatusController',
                        'action' => 'index'
                    ]
                ]
            ],
            'manager' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/manager[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\ManagerController',
                        'action' => 'index'
                    ]
                ]
            ],
            'manager-config' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/configLocale[/:locale]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\ManagerController',
                        'action' => 'configLocale',
                        'locale' => 'ca'
                    ],
                    'constraints' => [
                        'locale' => '[a-z]*'
                    ]
                ]
            ],
            'magento' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/magento[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\MagentoController',
                        'action' => 'list'
                    ]
                ]
            ],
            'crawler' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/crawler[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\CrawlerController',
                        'action' => 'index'
                    ]
                ]
            ],
            'config' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/config[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\ConfigurationController',
                        'action' => 'index'
                    ]
                ]
            ],
            'cron' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/cron[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\CronController',
                        'action' => 'index'
                    ]
                ]
            ],
            'technik' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/tech/stockpattern',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\ManagerController',
                        'action' => 'stockPattern'
                    ]
                ]
            ],
            'aska' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/aska[/:action]',
                    'defaults' => [
                        'controller' => 'Parser\\Controller\\TestController',
                        'action' => 'index'
                    ]
                ]
            ],
            'cdiscount' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/cdiscount[/:action]',
                    'defaults' => [
                        'controller' => 'Cdiscount\\Controller\\ListController',
                        'action' => 'list'
                    ]
                ]
            ],
            'cdiscount-console' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/cdiscount-console[/:action]',
                    'defaults' => [
                        'controller' => 'Cdiscount\\Controller\\ConsoleController',
                        'action' => 'scrape'
                    ]
                ]
            ],
            'cdiscount-index' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/cdiscount/',
                    'defaults' => [
                        'controller' => 'Cdiscount\\Controller\\ListController',
                        'action' => 'list'
                    ]
                ]
            ],
            'comparator' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/comparator[/:action]',
                    'defaults' => [
                        'controller' => 'Comparator\\Controller\\ListController',
                        'action' => 'list'
                    ]
                ]
            ],
            'comparator-console' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/comparator-console[/:action]',
                    'defaults' => [
                        'controller' => 'Comparator\\Controller\\ConsoleController',
                        'action' => 'scrape'
                    ]
                ]
            ],
            'comparator-index' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/comparator/',
                    'defaults' => [
                        'controller' => 'Comparator\\Controller\\ListController',
                        'action' => 'list'
                    ]
                ]
            ],
            'bestbuy' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/bestbuy[/:action]',
                    'defaults' => [
                        'controller' => 'BestBuy\\Controller\\ListController',
                        'action' => 'category'
                    ]
                ]
            ],
            'bestbuy-index' => [
                'type' => 'Laminas\\Router\\Http\\Literal',
                'options' => [
                    'route' => '/bestbuy/',
                    'defaults' => [
                        'controller' => 'BestBuy\\Controller\\ListController',
                        'action' => 'category'
                    ]
                ]
            ],
            'keepa' => [
                'type' => 'Laminas\\Router\\Http\\Segment',
                'options' => [
                    'route' => '/keepa[/:action]',
                    'defaults' => [
                        'controller' => 'BestBuy\\Controller\\KeepaController',
                        'action' => 'index'
                    ]
                ]
            ]
        ]
    ],
    'config_file' => 'data/parser/config/config.xml',
    'controllerMap' => [
        'migrate-parser' => [
            'class' => 'yii\\console\\controllers\\MigrateController',
            'migrationTable' => 'migration_parser',
            'migrationPath' => 'module/Parser/migrations'
        ],
        'migrate-cdiscount' => [
            'class' => 'yii\\console\\controllers\\MigrateController',
            'migrationTable' => 'migration_cdiscount',
            'migrationPath' => 'module/Cdiscount/migrations'
        ],
        'migrate-comparator' => [
            'class' => 'yii\\console\\controllers\\MigrateController',
            'migrationTable' => 'migration_comparator',
            'migrationPath' => 'module/Comparator/migrations'
        ],
        'migrate-bestbuy' => [
            'class' => 'yii\\console\\controllers\\MigrateController',
            'migrationTable' => 'migration_bestbuy',
            'migrationPath' => 'module/BestBuy/migrations'
        ]
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'doctype' => 'HTML5',
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
        'template_path_stack' => [
            '/var/www/parser/module/Parser/config/../view',
            '/var/www/parser/module/Cdiscount/config/../view',
            '/var/www/parser/module/Comparator/config/../view',
            '/var/www/parser/module/BestBuy/config/../view'
        ],
        'template_map' => [
            'layout/layout' => '/var/www/parser/module/Parser/config/../view/layout/layout.phtml',
            'layout/sidebar' => '/var/www/parser/module/Parser/config/../view/layout/sidebar.phtml',
            'layout/topnav' => '/var/www/parser/module/Parser/config/../view/layout/topnav.phtml',
            'error/404' => '/var/www/parser/module/Parser/config/../view/error/404.phtml',
            'error/index' => '/var/www/parser/module/Parser/config/../view/error/index.phtml',
            'zero' => '/var/www/parser/module/Parser/config/../view/layout/zero.phtml'
        ],
        'strategies' => [
            'ViewJsonStrategy',
            'ViewJsonStrategy',
            'ViewJsonStrategy',
            'ViewJsonStrategy'
        ]
    ],
    'controllers' => [
        'factories' => [
            'Parser\\Controller\\CrawlerController' => 'Parser\\Factory\\CrawlerControllerFactory',
            'Parser\\Controller\\ConfigurationController' => 'Parser\\Factory\\ConfigurationControllerFactory',
            'Parser\\Controller\\ListController' => 'Parser\\Factory\\ListControllerFactory',
            'Parser\\Controller\\TestController' => 'Parser\\Factory\\TestControllerFactory',
            'Parser\\Controller\\StatusController' => 'Parser\\Factory\\StatusControllerFactory',
            'Parser\\Controller\\ManagerController' => 'Parser\\Factory\\ManagerControllerFactory',
            'Parser\\Controller\\MagentoController' => 'Parser\\Factory\\MagentoControllerFactory',
            'Parser\\Controller\\ProfileController' => 'Parser\\Factory\\ProfileControllerFactory',
            'Parser\\Controller\\CronController' => 'Parser\\Factory\\CronControllerFactory',
            'Cdiscount\\Controller\\ListController' => 'Cdiscount\\Factory\\ListControllerFactory',
            'Cdiscount\\Controller\\ConsoleController' => 'Cdiscount\\Factory\\ConsoleControllerFactory',
            'Comparator\\Controller\\ListController' => 'Comparator\\Factory\\ListControllerFactory',
            'Comparator\\Controller\\ConsoleController' => 'Comparator\\Factory\\ConsoleControllerFactory',
            'BestBuy\\Controller\\ListController' => 'BestBuy\\Factory\\ListControllerFactory',
            'BestBuy\\Controller\\KeepaController' => 'BestBuy\\Factory\\KeepaControllerFactory'
        ]
    ],
    'console' => [
        'router' => [
            'routes' => [
                'general-test-controller' => [
                    'options' => [
                        'route' => 'test scrape catalog',
                        'defaults' => [
                            'controller' => 'Parser\\Controller\\TestController',
                            'action' => 'consoleScrape'
                        ]
                    ]
                ],
                'general-test-controller-browsers' => [
                    'options' => [
                        'route' => 'test browsers [--proxy=proxy]',
                        'defaults' => [
                            'controller' => 'Parser\\Controller\\TestController',
                            'action' => 'testBrowsers'
                        ]
                    ]
                ],
                'cron-scrape-catalog-action' => [
                    'options' => [
                        'route' => 'scrape catalog [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => 'Parser\\Controller\\CrawlerController',
                            'action' => 'scrape'
                        ]
                    ]
                ],
                'cron-sync-amazon-product-action' => [
                    'options' => [
                        'route' => 'scrape amazonProduct [--locale=locale] [--asin=asin] [--debug=debug]',
                        'defaults' => [
                            'controller' => 'Parser\\Controller\\ListController',
                            'action' => 'parse'
                        ]
                    ]
                ],
                'cron-sync-amazon-product-sync-action' => [
                    'options' => [
                        'route' => 'scrape amazon [--debug=debug] [--delay=delay] [--key=key]',
                        'defaults' => [
                            'controller' => 'Parser\\Controller\\ListController',
                            'action' => 'consolesync'
                        ]
                    ]
                ],
                'cdiscount-scrape-controller' => [
                    'options' => [
                        'route' => 'scrape cdiscount [--verbose|-v] [--delay=delay] [--category=category] ',
                        'defaults' => [
                            'controller' => 'Cdiscount\\Controller\\ConsoleController',
                            'action' => 'scrape'
                        ]
                    ]
                ],
                'cdiscount-scrape-amazon-controller' => [
                    'options' => [
                        'route' => 'scrape cdiscountAmazon [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => 'Cdiscount\\Controller\\ConsoleController',
                            'action' => 'scrapeAmazon'
                        ]
                    ]
                ],
                'cdiscount-scrape-product-controller' => [
                    'options' => [
                        'route' => 'scrape cdiscountProduct [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => 'Cdiscount\\Controller\\ConsoleController',
                            'action' => 'scrapeProduct'
                        ]
                    ]
                ],
                'comparator-scrape-controller' => [
                    'options' => [
                        'route' => 'scrape comparator [--verbose|-v] [--delay=delay] [--category=category] ',
                        'defaults' => [
                            'controller' => 'Comparator\\Controller\\ConsoleController',
                            'action' => 'scrape'
                        ]
                    ]
                ],
                'comparator-scrape-amazon-controller' => [
                    'options' => [
                        'route' => 'scrape comparatorAmazon [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => 'Comparator\\Controller\\ConsoleController',
                            'action' => 'scrapeAmazon'
                        ]
                    ]
                ],
                'comparator-scrape-product-controller' => [
                    'options' => [
                        'route' => 'scrape comparatorProduct [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => 'Comparator\\Controller\\ConsoleController',
                            'action' => 'scrapeProduct'
                        ]
                    ]
                ],
                'comparator-scrape-keepa-controller' => [
                    'options' => [
                        'route' => 'scrape comparatorKeepa [--verbose|-v] [--delay=delay] ',
                        'defaults' => [
                            'controller' => 'Comparator\\Controller\\ConsoleController',
                            'action' => 'scrapeKeepa'
                        ]
                    ]
                ]
            ]
        ]
    ],
    'sidebar' => [
        'parser-sidebar' => [
            'condition' => 'Parser\\Model\\Helper\\Condition\\Common',
            'items' => [
                'home' => [
                    'order' => 0,
                    'type' => 'route',
                    'module' => 'home',
                    'action' => '',
                    'class' => 'fa-dashboard',
                    'title' => 'Dashboard'
                ],
                'Products' => [
                    'order' => 10,
                    'type' => 'route',
                    'module' => 'manager',
                    'action' => 'list',
                    'class' => 'fa-th-list',
                    'title' => 'Products'
                ],
                'FindProducts' => [
                    'order' => 20,
                    'type' => 'route',
                    'module' => 'crawler',
                    'action' => 'search',
                    'class' => 'fa-search',
                    'title' => 'Find Products',
                    'children' => [
                        'find-products-list' => [
                            'order' => 20,
                            'type' => 'route',
                            'module' => 'crawler',
                            'action' => 'list',
                            'class' => 'fa-list',
                            'title' => 'Categories'
                        ],
                        'find-products-form' => [
                            'order' => 10,
                            'type' => 'route',
                            'module' => 'crawler',
                            'action' => 'search',
                            'class' => 'fa-search',
                            'title' => 'Upload Form'
                        ]
                    ]
                ],
                'UploadASINs' => [
                    'order' => 30,
                    'type' => 'route',
                    'module' => 'manager',
                    'action' => 'import',
                    'class' => 'fa-upload',
                    'title' => 'Upload ASINs'
                ],
                'Configuration' => [
                    'order' => 40,
                    'type' => 'route',
                    'module' => 'config',
                    'action' => 'list',
                    'class' => 'fa-sliders',
                    'title' => 'Configuration'
                ]
            ]
        ],
        'parser-live' => [
            'condition' => 'Parser\\Model\\Helper\\Condition\\Live',
            'items' => [
                'MagentoList' => [
                    'order' => 50,
                    'type' => 'route',
                    'module' => 'magento',
                    'action' => 'list',
                    'class' => 'fa-shopping-cart',
                    'title' => 'Magento List'
                ]
            ]
        ],
        'config-admin' => [
            'condition' => 'Parser\\Model\\Helper\\Condition\\Admin',
            'items' => [
                'GeneralConfig' => [
                    'order' => 1150,
                    'type' => 'route',
                    'module' => 'manager',
                    'action' => 'config',
                    'class' => 'fa-cog',
                    'title' => 'Settings',
                    'children' => 'Parser\\Model\\Helper\\Content\\ConfigLocales'
                ]
            ]
        ],
        'parser-demo' => [
            'condition' => 'Parser\\Model\\Helper\\Condition\\Demo',
            'items' => [
                'QuickTour' => [
                    'order' => 60,
                    'type' => 'route',
                    'module' => 'profile',
                    'action' => 'quicktour',
                    'class' => 'fa-book',
                    'title' => 'Quick Tour'
                ]
            ]
        ],
        'cdiscount' => [
            'condition' => 'Parser\\Model\\Helper\\Condition\\Live',
            'items' => [
                'cdiscount' => [
                    'order' => 20,
                    'type' => 'route',
                    'module' => 'cdiscount',
                    'action' => 'list',
                    'class' => 'fa-search',
                    'title' => 'CDiscount Products',
                    'children' => [
                        'find-cdiscount-category-list' => [
                            'order' => 30,
                            'type' => 'route',
                            'module' => 'cdiscount',
                            'action' => 'list',
                            'class' => 'fa-list',
                            'title' => 'Categories'
                        ],
                        'find-cdiscount-products-list' => [
                            'order' => 20,
                            'type' => 'route',
                            'module' => 'cdiscount',
                            'action' => 'products',
                            'class' => 'fa-list',
                            'title' => 'Products'
                        ],
                        'find-cdiscount-products-form' => [
                            'order' => 10,
                            'type' => 'route',
                            'module' => 'cdiscount',
                            'action' => 'search',
                            'class' => 'fa-search',
                            'title' => 'Add categories'
                        ]
                    ]
                ]
            ]
        ],
        'comparator' => [
            'condition' => 'Parser\\Model\\Helper\\Condition\\Live',
            'items' => [
                'comparator' => [
                    'order' => 20,
                    'type' => 'route',
                    'module' => 'comparator',
                    'action' => 'category',
                    'class' => 'fa-search',
                    'title' => 'Comparator',
                    'children' => [
                        'find-comparator-products-list' => [
                            'order' => 20,
                            'type' => 'route',
                            'module' => 'comparator',
                            'action' => 'list',
                            'class' => 'fa-list',
                            'title' => 'Products'
                        ],
                        'find-comparator-products-form' => [
                            'order' => 10,
                            'type' => 'route',
                            'module' => 'comparator',
                            'action' => 'search',
                            'class' => 'fa-search',
                            'title' => 'Add items'
                        ]
                    ]
                ]
            ]
        ],
        'bestbuy' => [
            'condition' => 'Parser\\Model\\Helper\\Condition\\Live',
            'items' => [
                'bestbuy' => [
                    'order' => 120,
                    'type' => 'route',
                    'module' => 'bestbuy',
                    'action' => 'category',
                    'class' => 'fa-bold',
                    'title' => 'BestBuy Categories',
                    'children' => [
                        'bestbuy-products-list' => [
                            'order' => 20,
                            'type' => 'route',
                            'module' => 'bestbuy',
                            'action' => 'category',
                            'class' => 'fa-list',
                            'title' => 'Categories'
                        ],
                        'bestbuy-products-form' => [
                            'order' => 10,
                            'type' => 'route',
                            'module' => 'bestbuy',
                            'action' => 'upload',
                            'class' => 'fa-search',
                            'title' => 'Upload Form'
                        ]
                    ]
                ],
                'keepa' => [
                    'order' => 200,
                    'type' => 'route',
                    'module' => 'keepa',
                    'action' => 'index',
                    'class' => 'fa-signal',
                    'title' => 'Keepa Products'
                ]
            ]
        ]
    ],
    'session_storage' => [
        'name' => 'parser',
        'type' => 'Laminas\\Session\\Storage\\SessionArrayStorage',
        'options' => []
    ],
    'session_container' => [
        'name' => 'parser'
    ],
    'session_config' => [
        'name' => 'parser',
        'remember_me_seconds' => 86400,
        'cache_expire' => 86400,
        'cookie_lifetime' => 86400,
        'gc_maxlifetime' => 86400
    ],
    'session_manager' => [
        'name' => 'parser',
        'validators' => [
            'Laminas\\Session\\Validator\\RemoteAddr',
            'Laminas\\Session\\Validator\\HttpUserAgent'
        ]
    ],
    'hookList' => [
        'product-sync' => [],
        'product-delete' => []
    ],
    'cdiscountConfig' => [
        'host' => 'https://cdiscount.com',
        'baseUrl' => 'https://cdiscount.com/',
        'categoryTag' => 'category/',
        'pagingTag' => 'page={page}',
        'pagesQtyPerRun' => 10,
        'productsQtyPerRun' => 100
    ],
    'bestBuyConfig' => [
        'host' => 'https://www.bestbuy.ca',
        'baseUrl' => 'https://www.bestbuy.ca/en-ca/',
        'categoryTag' => 'category/',
        'pagingTag' => 'page={page}',
        'pagesQtyPerRun' => 10,
        'productsQtyPerRun' => 100
    ],
    'db' => [
        'driver' => 'pdo',
        'dsn' => 'mysql:dbname=parser;host=127.0.0.1;',
        'driver_options' => [
            1002 => 'SET NAMES \'utf8mb4\''
        ],
        'charset' => 'utf8mb4',
        'username' => 'parser',
        'password' => 'SV8N4UQYiL7ljCdZqp8x'
    ],
    'view_helper_config' => [
        'asset' => [
            'resource_map' => [
                'website-title' => 'Web Data Extraction Engine',
                'demo' => 0
            ]
        ]
    ],
    'website_mode' => '',
    'cache' => [
        'adapter' => [
            'name' => 'redis',
            'options' => [
                'server' => [
                    'host' => '127.0.0.1',
                    'port' => 6379
                ],
                'ttl' => 3600
            ]
        ]
    ]
];

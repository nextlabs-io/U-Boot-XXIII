<?php
/**
 * Created by WebExperiment.
 * User: dom
 * Date: 10.08.18
 * Time: 21:30
 */

namespace Parser\Controller;

use Magento\Model\Magento;
use Parser\Model\Form\LoginForm;
use Parser\Model\Form\ProfileForm;
use Parser\Model\Helper\Config;
use Parser\Model\Profile;
use Parser\Model\SimpleObject;
use Laminas\Authentication\Adapter\DbTable\CredentialTreatmentAdapter as AuthAdapter;
use Laminas\Db\Sql\Where;
use Laminas\Validator\EmailAddress;
use Laminas\View\Model\ViewModel;

class ProfileController extends AbstractController
{
    private $db;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->db = $config->getDb();
        $this->authActions = ['edit', 'quicktour'];
    }

    public function editAction()
    {
        $simple = new SimpleObject();
        $profile = new Profile($this->db, $this->identity);
        $profile->load();

        $customFields = $this->config->getProfileFormCustomFields();
//

        $uploadForm = new ProfileForm('profileForm', ['profileSettings' => $customFields]);
        $request = $this->getRequest();
        $change = '';
        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );
            $uploadForm->setData($post);
            $uploadForm->isValid();
            $data = $uploadForm->getData();
            $login = $uploadForm->get('login')->getValue();
            $email = $uploadForm->get('email')->getValue();
            $change = $this->params()->fromPost('change-password', '');
            $pass1 = $uploadForm->get('password')->getValue();
            $pass2 = $uploadForm->get('password2')->getValue();



            if (isset($this->config->storeConfig['website_mode']) && $this->config->storeConfig['website_mode'] === 'demo' && $this->identity === $this->config->storeConfig['website_demo_user']) {
                $login = $this->config->storeConfig['website_demo_user'];
                $change = false;
            }

            $emailValidator = new EmailAddress();
            if ($email && !$emailValidator->isValid($email)) {
                $simple->addError($emailValidator->getMessages());
            }
            if (!$login) {
                $simple->addError('Login is required');
            }
            if ($change) {
                if (!$pass1 || !$pass2) {
                    $simple->addError('Please put passwords');
                } elseif ($pass1 != $pass2) {
                    $simple->addError('Passwords must be the same');
                }
            }

            if (!$simple->hasErrors()) {

                $toUpdate = ['login' => $login, 'email' => $email];

                if ($change) {
                    $toUpdate['password'] = md5($pass1);
                }
                $where = new Where();
                $where->equalTo('login', $this->identity);
                if($customFields) {
                    $profileSettings = [];
                    foreach ($customFields as $id => $customField) {
                        // important - get '' empty string if null, unchecked checkbox will give null value
                        $profileSettings[$id] = $uploadForm->get($id)->getValue() ?: '';
                    }
                    if ($profileSettings) {

                        $profile->updateProfileSettings($profileSettings);
                    }
                }
                $profile->update($toUpdate, $where);
                if ($change || $login != $this->identity) {
                    $auth = $this->getAuth();
                    $auth->clearIdentity();
                    $manager = $this->session->getManager();
                    $manager->forgetMe();
                    $this->redirect()->toUrl('/profile/login');
                }
                $simple->addMessage('Update success');
            }

        } else {
            $formData = $profile->data;
            foreach ($customFields as $id => $customField) {
                $formData[$id] = $profile->loadConfigData('profileSettings')[$id] ?? '';
            }
            $uploadForm->setData($formData);
        }

        $result = new ViewModel([
            'form' => $uploadForm,
            'message' => $simple->getStringMessages(","),
            'errors' => $simple->getStringErrorMessages(","),
            'change' => $change,
        ]);


        return $result;

    }

    public function logoutAction()
    {
        $auth = $this->getAuth();
        $auth->clearIdentity();

        /* @var \Laminas\Session\SessionManager $manager */
        $manager = $this->session->getManager();
        $manager->forgetMe();
        $this->redirect()->toUrl('/profile/login');
        return;
    }

    public function loginAction()
    {
        $simple = new SimpleObject();
        $uploadForm = new LoginForm();
        $request = $this->getRequest();
        $auth = $this->getAuth();
        $demo = false;
        $loadOverride = $this->params()->fromQuery('load');
        $passOverride = $this->params()->fromQuery('pass');
        $post = $request->getPost()->toArray();

        if ($auth->hasIdentity() && $loadOverride !== 'ernazar' ) {
            $redirectUrl = $this->session->redirectUrl ? $this->session->redirectUrl : '/';
            $this->session->redirectUrl = "";
            $this->redirect()->toUrl($redirectUrl);
        } elseif (isset($this->config->storeConfig['website_mode']) && $this->config->storeConfig['website_mode'] === 'demo') {
            // manual autoload
            if ($loadOverride === 'ernazar') {
                $post['login'] = $loadOverride;
                $post['password'] = $passOverride;
                $demo = true;
            } else {
                $post['login'] = $this->config->storeConfig['website_demo_user'];
                $post['password'] = $this->config->storeConfig['website_demo_password'];
                $demo = true;
            }
        }
        if ($request->isPost() || $demo) {
            $uploadForm->setData($post);
            if ($uploadForm->isValid()) {
                $data = $uploadForm->getData();
                $login = $uploadForm->get('login')->getValue();
                $pass = $uploadForm->get('password')->getValue();
                if (!$login || !$pass) {
                    $simple->addError('please enter login and pass');
                } else {
                    $authAdapter = new AuthAdapter($this->db,
                        'human',
                        'login',
                        'password',
                        'MD5(?)'
                    );
                    $authAdapter
                        ->setIdentity($login)
                        ->setCredential($pass);
                    $auth->authenticate($authAdapter);
                    if ($auth->hasIdentity()) {
                        /* @var \Laminas\Session\SessionManager $manager */
                        $manager = $this->session->getManager();
                        $manager->rememberMe(86400);

                        $redirectUrl = $this->session->redirectUrl ? $this->session->redirectUrl : '/';
                        if ($demo) {
                            $redirectUrl = $this->params()->fromQuery('redirect', '/');
                        }
                        $this->session->redirectUrl = "";
                        $this->redirect()->toUrl($redirectUrl);
                    } else {
                        $simple->addError('login or password incorrect');
                    }
                }
            } else {
                $simple->addError('please enter login and pass');
            }

        } else {
            $redirectUrl = $this->params()->fromQuery('redirect', '');
            $this->session->redirectUrl = $redirectUrl;
        }

        $result = new ViewModel([
            'form' => $uploadForm,
            'message' => $simple->getStringMessages("<br />"),
            'errors' => $simple->getStringErrorMessages("<br />"),
        ]);
        $result->setTerminal(true);

        return $result;
    }

    public function quicktourAction()
    {
        $result = new ViewModel([
        ]);
        return $result;
    }


}
<?php

namespace Login;

use Login\Controller\LoginController;
use Login\Controller\SuccessController;
use Login\Factory\LoginControllerFactory;
use Login\Factory\SuccessControllerFactory;
use Login\Model\MyAuthStorage;
use Zend\Authentication\Adapter\DbTable\CallbackCheckAdapter as AuthAdapter;
use Zend\Authentication\AuthenticationService;
use Zend\Crypt\Password\Bcrypt;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;

class Module implements AutoloaderProviderInterface, ConfigProviderInterface, ServiceProviderInterface{

    const VERSION = '3.0.2dev';
    
    public function getAutoloaderConfig() {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php',
            ),
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/' . __NAMESPACE__,
                ),
            ),
        );
    }

    public function getControllerConfig() {

        return [
            'factories' => [
                LoginController::class => LoginControllerFactory::class,
                SuccessController::class => SuccessControllerFactory::class,
            ],
        ];
    }

    public function getConfig() {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getServiceConfig() {
        return array(
            'factories' => array(
                'Login\Model\MyAuthStorage' => function($sm) {
                    $myauthstorage = new MyAuthStorage('zf_tutorial');
                    $myauthstorage->setLogger($sm->get('LoggingService'));
                    return $myauthstorage;
                },
                'AuthService' => function($sm) {
                    //My assumption, you've alredy set dbAdapter
                    //and has users table with columns : user_name and pass_word
                    //that password hashed with bcrypt
                    $credentialValidationCallback = function($dbCredential, $requestCredential) {
                                return (new Bcrypt())->verify($requestCredential, $dbCredential);
                            };
                            
                    $dbAdapter = $sm->get('Zend\Db\Adapter\Adapter');
                    $authAdapter = new AuthAdapter($dbAdapter, 'users', 'username', 'password', $credentialValidationCallback);
                    $authService = new AuthenticationService();
                    $authService->setAdapter($authAdapter);
                    $authService->setStorage($sm->get('Login\Model\MyAuthStorage'));

                    return $authService;
                },
            ),
        );
    }

}

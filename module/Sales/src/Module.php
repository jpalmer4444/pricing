<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sales;

use Sales\Controller\ItemsController;
use Sales\Controller\SalesController;
use Sales\Controller\UsersController;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;

class Module implements AutoloaderProviderInterface, ConfigProviderInterface, ServiceProviderInterface {

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
                UsersController::class => function($container) {
                    return new UsersController(
                            $container->get('RestService')
                    );
                },
                ItemsController::class => function($container) {
                    return new ItemsController(
                            $container->get('RestService')
                    );
                },
                SalesController::class => function($container) {
                    return new SalesController(
                            $container->get('RestService')
                    );
                },
            ],
        ];
    }

    public function getConfig() {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function getServiceConfig() {
        return array(
        );
    }

}

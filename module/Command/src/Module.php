<?php

namespace Command;

use Command\Controller\Reporting\PriceOverrideController;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ConsoleUsageProviderInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;

class Module implements AutoloaderProviderInterface, ConfigProviderInterface, ServiceProviderInterface, ConsoleUsageProviderInterface{

    const VERSION = '3.0.2dev';
    
    public function getConfig() {
        return include __DIR__ . '/../config/module.config.php';
    }
    
    public function getServiceConfig() {
        return [];
    }
    
    public function getControllerConfig(){
        return [
            'factories' => [
                PriceOverrideController::class => function($container) {
                    return new PriceOverrideController(
                            $container->get('LoggingService'),
                            $container->get('FFMEntityManager')->getEntityManager(),
                            $container->get('config')['pricing_config']
                    );
                },
            ],
        ];
    }
       
    public function getConsoleUsage(Console $console)
    {
        return [
            // Describe available commands
            'price override [--verbose|-v]' => 'Show Pricing Reports For Date Range.',

            // Describe expected parameters
            [ '--verbose|-v', '(optional) turn on verbose mode'        ],
        ];
    } 
    
    public function getAutoloaderConfig()
    {
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

}

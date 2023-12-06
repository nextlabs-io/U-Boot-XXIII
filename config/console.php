<?php
include __DIR__ . '/../vendor/autoload.php';

use Laminas\Mvc\Service;
use Laminas\ServiceManager\ServiceManager;

// Retrieve the configuration
$configuration = require __DIR__ . '/../config/application.config.php';
if (file_exists(__DIR__ . '/../config/development.config.php')) {
    $configuration = \Laminas\Stdlib\ArrayUtils::merge($configuration,
        require __DIR__ . '/../config/development.config.php');
}
$smConfig = $configuration['service_manager'] ?? [];
$smConfig = new Service\ServiceManagerConfig($smConfig);

$serviceManager = new ServiceManager();
$smConfig->configureServiceManager($serviceManager);
$serviceManager->setService('ApplicationConfig', $configuration);

// Load modules
$serviceManager->get('ModuleManager')->loadModules();
// Prepare list of listeners to bootstrap
return $serviceManager->get('config');
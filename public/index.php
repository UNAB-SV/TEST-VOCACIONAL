<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Helpers\Router;
use App\Helpers\ServiceContainer;
use App\Services\HealthService;

define('BASE_PATH', dirname(__DIR__));

$config = require BASE_PATH . '/config/app.php';

require_once BASE_PATH . '/src/helpers/Autoloader.php';

App\Helpers\Autoloader::addNamespace('App', BASE_PATH . '/src');
App\Helpers\Autoloader::register();

date_default_timezone_set($config['timezone']);

if (($config['session']['enabled'] ?? false) === true) {
    session_name((string) ($config['session']['name'] ?? 'PHPSESSID'));

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

$container = new ServiceContainer();

// Registro de servicios (factory + lazy load)
$container->set('health_service', static fn (): HealthService => new HealthService());
$container->set('home_controller', static fn (ServiceContainer $c): HomeController => new HomeController(
    $c->get('health_service')
));

$router = new Router();
$router->get('/', static function () use ($container): void {
    /** @var HomeController $controller */
    $controller = $container->get('home_controller');
    $controller->index();
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

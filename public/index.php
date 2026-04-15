<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Helpers\Router;
use App\Helpers\ServiceContainer;
use App\Services\ParticipantSessionStore;
use App\Validators\ParticipantDataValidator;

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
$container->set('participant_validator', static fn (): ParticipantDataValidator => new ParticipantDataValidator());
$container->set('participant_session_store', static fn (): ParticipantSessionStore => new ParticipantSessionStore());
$container->set('home_controller', static fn (ServiceContainer $c): HomeController => new HomeController(
    $c->get('participant_validator'),
    $c->get('participant_session_store')
));

$router = new Router();
$router->get('/', static function () use ($container): void {
    /** @var HomeController $controller */
    $controller = $container->get('home_controller');
    $controller->index();
});
$router->post('/', static function () use ($container): void {
    /** @var HomeController $controller */
    $controller = $container->get('home_controller');
    $controller->submit();
});
$router->get('/instrucciones', static function () use ($container): void {
    /** @var HomeController $controller */
    $controller = $container->get('home_controller');
    $controller->instructions();
});
$router->post('/instrucciones/iniciar', static function () use ($container): void {
    /** @var HomeController $controller */
    $controller = $container->get('home_controller');
    $controller->startTest();
});
$router->get('/prueba', static function () use ($container): void {
    /** @var HomeController $controller */
    $controller = $container->get('home_controller');
    $controller->test();
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

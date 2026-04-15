<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Helpers\Router;
use App\Helpers\ServiceContainer;
use App\Helpers\TestResultPresenter;
use App\Repositories\QuestionsBlockRepository;
use App\Services\CalculationEngine;
use App\Services\ParticipantSessionStore;
use App\Services\ScoreService;
use App\Services\TestSessionStore;
use App\Validators\ParticipantDataValidator;
use App\Validators\TestResponseValidator;

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
$container->set('questions_block_repository', static fn (): QuestionsBlockRepository => new QuestionsBlockRepository());
$container->set('test_session_store', static fn (): TestSessionStore => new TestSessionStore());
$container->set('test_response_validator', static fn (): TestResponseValidator => new TestResponseValidator());
$container->set('score_service', static fn (): ScoreService => new ScoreService());
$container->set('calculation_engine', static fn (ServiceContainer $c): CalculationEngine => new CalculationEngine(
    $c->get('score_service'),
    BASE_PATH . '/config/test-vocacional/catalog.php'
));
$container->set('test_result_presenter', static fn (): TestResultPresenter => new TestResultPresenter(
    BASE_PATH . '/config/test-vocacional/catalog.php'
));
$container->set('home_controller', static fn (ServiceContainer $c): HomeController => new HomeController(
    $c->get('participant_validator'),
    $c->get('participant_session_store'),
    $c->get('questions_block_repository'),
    $c->get('test_session_store'),
    $c->get('test_response_validator'),
    $c->get('calculation_engine'),
    $c->get('test_result_presenter')
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
$router->post('/prueba/bloque/guardar', static function () use ($container): void {
    /** @var HomeController $controller */
    $controller = $container->get('home_controller');
    $controller->saveTestBlock();
});
$router->post('/prueba/finalizar', static function () use ($container): void {
    /** @var HomeController $controller */
    $controller = $container->get('home_controller');
    $controller->finishTest();
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

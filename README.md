# Test Vocacional - Estructura Base (PHP 8.3)

Proyecto esqueleto en PHP puro listo para crecer con arquitectura por capas.

## Árbol de carpetas

```text
.
├── config/
│   └── app.php
├── docs/
│   └── diseno-tecnico-test-kuder.md
├── public/
│   ├── assets/
│   │   └── app.css
│   └── index.php
├── src/
│   ├── controllers/
│   │   └── HomeController.php
│   ├── helpers/
│   │   ├── Autoloader.php
│   │   ├── Router.php
│   │   ├── ServiceContainer.php
│   │   └── View.php
│   ├── repositories/
│   │   └── ExampleRepository.php
│   ├── services/
│   │   └── HealthService.php
│   └── validators/
│       └── RequestValidator.php
├── storage/
│   └── logs/
│       └── .gitkeep
└── templates/
    ├── home/
    │   └── index.php
    └── layouts/
        └── base.php
```

## Levantar servidor local

```bash
php -S localhost:8000 -t public
```

## Registro y carga de servicios (ejemplo)

Definido en `public/index.php`:

```php
$container = new ServiceContainer();

$container->set('health_service', static fn (): HealthService => new HealthService());
$container->set('home_controller', static fn (ServiceContainer $c): HomeController => new HomeController(
    $c->get('health_service')
));
```

Con esto los servicios se crean de forma perezosa (lazy) cuando se piden con `$container->get(...)`.

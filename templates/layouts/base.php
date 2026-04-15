<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(($title ?? 'Test Vocacional'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header>
    <h1>Test Vocacional</h1>
</header>
<main>
    <?= $content ?? ''; ?>
</main>
<footer>
    <small>Plantilla base - PHP 8.3 puro</small>
</footer>
</body>
</html>

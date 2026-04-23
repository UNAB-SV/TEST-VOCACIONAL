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
    <div class="brand-header">
        <img
            class="brand-logo"
            src="/assets/img/logo-unab-blanco.png"
            alt="Logo institucional de la Universidad Dr. Andrés Bello"
            width="190"
            height="56"
        >
        <h1 class="brand-title">Test Vocacional</h1>
    </div>
</header>
<main>
    <?= $content ?? ''; ?>
</main>
<footer>
    <small>Universidad Dr. Andrés Bello, 2026</small>
</footer>
</body>
</html>

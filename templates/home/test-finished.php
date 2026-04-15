<?php
/** @var array<string, string> $result */
/** @var int $totalBlocks */
?>
<section class="card">
    <h2>Prueba finalizada</h2>
    <p class="subtitle">Has completado <?= (int) $totalBlocks; ?> bloques y tus respuestas fueron enviadas.</p>

    <div class="alert alert-success" role="status">
        <?= htmlspecialchars((string) ($result['message'] ?? 'Proceso finalizado.'), ENT_QUOTES, 'UTF-8'); ?>
    </div>

    <a class="btn-primary" href="/">Volver al inicio</a>
</section>

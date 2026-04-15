<?php
/** @var array<string, string> $participant */
$displayName = trim(($participant['nombres'] ?? '') . ' ' . ($participant['apellido_paterno'] ?? ''));
?>
<section class="card">
    <h2>Prueba vocacional</h2>
    <p class="subtitle">Inicio de la prueba para <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
    <p>Esta vista confirma que solo es posible iniciar la prueba después de completar datos iniciales y confirmar instrucciones.</p>
</section>

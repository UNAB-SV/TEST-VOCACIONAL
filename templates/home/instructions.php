<?php
/** @var array<string, string> $participant */
?>
<section class="card">
    <h2>Instrucciones del test</h2>
    <p class="subtitle">Bienvenido(a), <strong><?= htmlspecialchars(($participant['nombres'] ?? '') . ' ' . ($participant['apellido_paterno'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>.</p>

    <ol class="instructions-list">
        <li>Lee cada pregunta con atención antes de responder.</li>
        <li>Selecciona la opción que mejor represente tu preferencia.</li>
        <li>Responde de manera honesta para obtener mejores resultados.</li>
    </ol>

    <p class="session-note">Tus datos iniciales se guardaron temporalmente en sesión para continuar el proceso.</p>
</section>

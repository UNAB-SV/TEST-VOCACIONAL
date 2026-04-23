<?php
/** @var array<string, string> $participant */

$fullName = trim(($participant['nombres'] ?? '') . ' ' . ($participant['apellido_paterno'] ?? '') . ' ' . ($participant['apellido_materno'] ?? ''));
?>
<section class="card">
    <h2>Instrucciones del test</h2>
    <p class="subtitle">Bienvenido(a), <strong><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>

    <div class="participant-summary">
        <p><strong>Institución:</strong> <?= htmlspecialchars((string) (($participant['colegio_nombre'] ?? '') !== '' ? $participant['colegio_nombre'] : ($participant['grupo'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Edad:</strong> <?= htmlspecialchars($participant['edad'] ?? '', ENT_QUOTES, 'UTF-8'); ?> años</p>
        <p><strong>Sexo:</strong> <?= htmlspecialchars($participant['sexo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <ol class="instructions-list">
        <li>Lee cada actividad y compara qué tanto te agrada frente a las demás opciones.</li>
        <li>En cada bloque marca una opción como <strong>"más"</strong> (la que más te gusta) y otra como <strong>"menos"</strong> (la que menos te gusta).</li>
        <li>No dejes preguntas sin responder y evita repetir la misma opción como "más" y "menos" en el mismo bloque.</li>
        <li>No hay respuestas correctas o incorrectas: responde con sinceridad según tus preferencias personales.</li>
    </ol>

    <div class="example-box" aria-label="Ejemplo de respuesta">
        <h3>Ejemplo de cómo responder</h3>
        <p>Si en un bloque aparecen estas actividades:</p>
        <ul>
            <li><em>Reparar un aparato eléctrico</em></li>
            <li><em>Organizar una actividad escolar</em></li>
            <li><em>Leer sobre un descubrimiento científico</em></li>
        </ul>
        <p>
            Si lo que <strong>más</strong> te interesa es leer sobre ciencia, marca esa opción como <strong>"más"</strong>.
            Si lo que <strong>menos</strong> te interesa es organizar la actividad escolar, marca esa opción como <strong>"menos"</strong>.
        </p>
    </div>

    <p class="session-note">Tus datos del evaluado se recuperaron de sesión para asegurar continuidad y navegación controlada del proceso.</p>

    <form method="post" action="/instrucciones/iniciar">
        <button class="btn-primary" type="submit">Iniciar prueba</button>
    </form>
</section>

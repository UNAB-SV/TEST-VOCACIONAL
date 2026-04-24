<?php
/** @var array<string, string> $participant */
/** @var int $elSalvadorCountryId */

$fullName = trim(($participant['nombres'] ?? '') . ' ' . ($participant['apellido_paterno'] ?? '') . ' ' . ($participant['apellido_materno'] ?? ''));
$institutionName = trim((string) ($participant['colegio_nombre'] ?? ''));
if ($institutionName === '') {
    $institutionName = trim((string) ($participant['group_name'] ?? ''));
}
if ($institutionName === '') {
    $institutionName = trim((string) ($participant['grupo'] ?? ''));
}
$countryName = trim((string) ($participant['pais_nombre'] ?? ''));
$departmentName = trim((string) ($participant['departamento_nombre'] ?? ''));
$municipalityName = trim((string) ($participant['municipio_nombre'] ?? ''));
$isElSalvador = (int) ($participant['pais_id'] ?? 0) === (int) ($elSalvadorCountryId ?? 0)
    || strcasecmp($countryName, 'El Salvador') === 0;
?>
<section class="card">
    <h2>Instrucciones del test</h2>
    <p class="subtitle">Bienvenido(a), <strong><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>

    <div class="participant-summary" aria-label="Resumen del participante">
        <div class="participant-summary-main participant-summary-institution">
            <span class="meta-label participant-summary-label">Institución</span>
            <span class="meta-value institution-name"><?= htmlspecialchars($institutionName, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="participant-summary-details">
            <div class="meta-item">
                <span class="meta-label participant-summary-label">Edad</span>
                <span class="meta-value"><?= htmlspecialchars($participant['edad'] ?? '', ENT_QUOTES, 'UTF-8'); ?> años</span>
            </div>
            <div class="meta-item">
                <span class="meta-label participant-summary-label">Sexo</span>
                <span class="meta-value"><?= htmlspecialchars($participant['sexo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php if ($countryName !== ''): ?>
                <div class="meta-item">
                    <span class="meta-label participant-summary-label">País</span>
                    <span class="meta-value"><?= htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($isElSalvador && $departmentName !== ''): ?>
                <div class="meta-item">
                    <span class="meta-label participant-summary-label">Departamento</span>
                    <span class="meta-value"><?= htmlspecialchars($departmentName, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($isElSalvador && $municipalityName !== ''): ?>
                <div class="meta-item meta-item-long">
                    <span class="meta-label participant-summary-label">Municipio / Distrito</span>
                    <span class="meta-value"><?= htmlspecialchars($municipalityName, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <p>
        Lea atentamente cada una de las indicaciones. La prueba no se trata de un examen, no hay respuestas exactas o inexactas.
        Toda respuesta que refleje su modo de pensar es una buena respuesta.
    </p>

    <ol class="instructions-list">
        <li>Lee cada actividad y compara qué tanto te agrada frente a las demás opciones.</li>
        <li>En cada bloque marca una opción como <strong>"más"</strong> (la que más te gusta) y otra como <strong>"menos"</strong> (la que menos te gusta).</li>
        <li>No dejes preguntas sin responder y evita repetir la misma opción como "más" y "menos" en el mismo bloque.</li>
        <li>No hay respuestas correctas o incorrectas: responde con sinceridad según tus preferencias personales.</li>
        <li>Algunas actividades requieren previo estudio, aunque usted no los posea considere que tiene la preparación necesaria para ejercer dicha actividad.</li>
        <li>En algunos casos, las tres opciones le parecerán igualmente agradables o desagradables, sin embargo, es indispensable que usted escoja entre ellas la que le gustaría más y la que le gustaría menos.</li>
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

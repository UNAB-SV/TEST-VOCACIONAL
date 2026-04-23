<?php
$detail = is_array($detail ?? null) ? $detail : [];
$fullName = trim(sprintf(
    '%s %s %s',
    (string) ($detail['first_name'] ?? ''),
    (string) ($detail['last_name'] ?? ''),
    (string) ($detail['middle_name'] ?? '')
));
$scales = is_array($detail['scales'] ?? null) ? $detail['scales'] : [];
$answers = is_array($detail['answers'] ?? null) ? $detail['answers'] : [];
$appliedAtRaw = trim((string) ($detail['applied_at'] ?? ''));
$formattedAppliedAt = $appliedAtRaw;
if ($appliedAtRaw !== '') {
    $date = date_create($appliedAtRaw, new DateTimeZone('UTC'));
    if ($date !== false) {
        $formattedAppliedAt = $date->format('d/m/Y H:i') . ' UTC';
    }
}
?>
<section class="card admin-card">
    <h2>Detalle de evaluación #<?= (int) ($detail['id'] ?? 0); ?></h2>

    <div class="result-meta-grid">
        <p><strong>Nombre:</strong> <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Edad:</strong> <?= (int) ($detail['age'] ?? 0); ?></p>
        <p><strong>Sexo:</strong> <?= htmlspecialchars((string) ($detail['sex'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Institución:</strong> <?= htmlspecialchars((string) (($detail['colegio_nombre'] ?? '') !== '' ? $detail['colegio_nombre'] : ($detail['group_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>País:</strong> <?= htmlspecialchars((string) ($detail['pais_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Departamento:</strong> <?= htmlspecialchars((string) ($detail['departamento_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Municipio / Distrito:</strong> <?= htmlspecialchars((string) ($detail['municipio_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Fecha de aplicación:</strong> <?= htmlspecialchars($formattedAppliedAt, ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Validez:</strong> <?= htmlspecialchars((string) ($detail['validity_state'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> (<?= (int) ($detail['validity_score'] ?? 0); ?>)</p>
    </div>

    <h3>Escalas registradas</h3>
    <table class="results-table">
        <thead>
        <tr>
            <th>Escala</th>
            <th>Puntaje bruto</th>
            <th>Percentil</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($scales as $scale): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($scale['scale_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= (int) ($scale['raw_score'] ?? 0); ?></td>
                <td><?= (int) ($scale['percentile'] ?? 0); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Respuestas por bloque</h3>
    <table class="results-table">
        <thead>
        <tr>
            <th>Bloque</th>
            <th>Más</th>
            <th>Menos</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($answers as $answer): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($answer['block_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($answer['mas'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= htmlspecialchars((string) ($answer['menos'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="result-actions no-print">
        <a class="btn-secondary" href="/admin/evaluaciones">Volver al listado</a>
        <a class="btn-primary" href="/admin/evaluaciones/reimprimir?id=<?= (int) ($detail['id'] ?? 0); ?>">Reimprimir resultados</a>
    </div>
</section>

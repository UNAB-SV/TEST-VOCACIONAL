<?php
/** @var array<string, mixed> $report */
$report = $report ?? [];
$evaluado = is_array($report['evaluado'] ?? null) ? $report['evaluado'] : [];
$validezEstado = is_array($report['validez_estado'] ?? null) ? $report['validez_estado'] : [];
$escalas = is_array($report['escalas'] ?? null) ? $report['escalas'] : [];
$ranking = is_array($report['ranking'] ?? null) ? $report['ranking'] : [];
$alertasTecnicas = is_array($report['alertas_tecnicas'] ?? null) ? $report['alertas_tecnicas'] : [];
$escalasSinPercentil = is_array($alertasTecnicas['escalas_sin_percentil'] ?? null) ? $alertasTecnicas['escalas_sin_percentil'] : [];
$estadoCodigo = (string) ($validezEstado['codigo'] ?? 'invalido');
$estadoEtiqueta = (string) ($validezEstado['etiqueta'] ?? 'Prueba no válida');
$esValida = (bool) ($validezEstado['es_valida'] ?? false);
$chartData = json_encode($ranking, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<section class="card result-card" id="results-app"
         data-chart='<?= htmlspecialchars((string) $chartData, ENT_QUOTES, 'UTF-8'); ?>'>
    <h2>Resultados del test vocacional</h2>
    <p class="subtitle">Resumen final para impresión y análisis de orientación.</p>

    <?php if (!$esValida): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Advertencia:</strong> <?= htmlspecialchars($estadoEtiqueta, ENT_QUOTES, 'UTF-8'); ?>.
            Revisa la interpretación del reporte antes de tomar decisiones.
        </div>
    <?php endif; ?>

    <?php if ($escalasSinPercentil !== []): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Advertencia técnica:</strong> faltan percentiles para:
            <?= htmlspecialchars(implode(', ', array_map('strval', $escalasSinPercentil)), ENT_QUOTES, 'UTF-8'); ?>.
        </div>
    <?php endif; ?>

    <div class="result-meta-grid">
        <p><strong>Nombre completo:</strong> <?= htmlspecialchars((string) ($evaluado['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Edad:</strong> <?= htmlspecialchars((string) ($evaluado['edad'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Sexo:</strong> <?= htmlspecialchars((string) ($evaluado['sexo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Institución:</strong> <?= htmlspecialchars((string) ($evaluado['institucion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Puntaje de validez:</strong> <?= (int) ($report['validez_puntaje'] ?? 0); ?></p>
        <p><strong>Estado de validez:</strong> <span class="badge badge-<?= htmlspecialchars($estadoCodigo, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($estadoEtiqueta, ENT_QUOTES, 'UTF-8'); ?></span></p>
    </div>

    <h3>Escalas de interés (10)</h3>
    <table class="results-table">
        <thead>
        <tr>
            <th>Escala</th>
            <th>Puntaje bruto</th>
            <th>Percentil</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($escalas as $escala): ?>
            <tr>
                <td><?= htmlspecialchars((string) ($escala['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?= (int) ($escala['puntaje_bruto'] ?? 0); ?></td>
                <td><?= is_int($escala['percentil'] ?? null) ? (string) $escala['percentil'] : 'N/D'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Ranking de intereses (mayor a menor)</h3>
    <ol class="ranking-list">
        <?php foreach ($ranking as $escala): ?>
            <li>
                <?= htmlspecialchars((string) ($escala['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                <span>(bruto: <?= (int) ($escala['puntaje_bruto'] ?? 0); ?>, percentil: <?= is_int($escala['percentil'] ?? null) ? (string) $escala['percentil'] : 'N/D'; ?>)</span>
            </li>
        <?php endforeach; ?>
    </ol>

    <h3>Gráfica de barras (puntaje bruto)</h3>
    <div id="results-chart" class="results-chart" aria-label="Gráfica de barras del ranking de intereses"></div>

    <div class="result-actions no-print">
        <button type="button" class="btn-secondary" onclick="window.print()">Imprimir resultados</button>
        <a class="btn-primary" href="/">Volver al inicio</a>
    </div>
</section>

<script>
(function () {
    const app = document.getElementById('results-app');
    const chartContainer = document.getElementById('results-chart');
    if (!(app instanceof HTMLElement) || !(chartContainer instanceof HTMLElement)) {
        return;
    }

    let data = [];
    try {
        data = JSON.parse(app.dataset.chart || '[]');
    } catch (error) {
        data = [];
    }

    if (!Array.isArray(data) || data.length === 0) {
        chartContainer.textContent = 'No hay datos para graficar.';
        return;
    }

    const maxScore = data.reduce(function (max, item) {
        const score = Number(item.puntaje_bruto || 0);
        return score > max ? score : max;
    }, 0) || 1;

    const escapeHtml = function (value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    chartContainer.innerHTML = data.map(function (item) {
        const name = escapeHtml(item.nombre || '');
        const score = Number(item.puntaje_bruto || 0);
        const width = Math.max(8, Math.round((score / maxScore) * 100));

        return '<div class="chart-row">' +
            '<span class="chart-label">' + name + '</span>' +
            '<div class="chart-track"><span class="chart-bar" style="width:' + width + '%"></span></div>' +
            '<span class="chart-value">' + score + '</span>' +
            '</div>';
    }).join('');
})();
</script>

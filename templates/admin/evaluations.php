<?php
$filters = is_array($filters ?? null) ? $filters : [];
$groups = is_array($groups ?? null) ? $groups : [];
$items = is_array($items ?? null) ? $items : [];
$total = (int) ($total ?? 0);
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 10));
$totalPages = max(1, (int) ceil($total / $perPage));

$buildUrl = static function (int $targetPage) use ($filters): string {
    $query = [
        'nombre' => (string) ($filters['nombre'] ?? ''),
        'grupo' => (string) ($filters['grupo'] ?? ''),
        'fecha' => (string) ($filters['fecha'] ?? ''),
        'page' => $targetPage,
    ];

    return '/admin/evaluaciones?' . http_build_query(array_filter($query, static fn ($value): bool => $value !== ''));
};
?>
<section class="card admin-card">
    <h2>Módulo administrativo</h2>
    <p class="subtitle">Consulta de resultados guardados con filtros, búsqueda y paginación.</p>

    <form method="get" action="/admin/evaluaciones" class="admin-filters">
        <div class="field-grid admin-grid">
            <div class="field">
                <label for="nombre">Buscar por nombre</label>
                <input id="nombre" name="nombre" type="text" value="<?= htmlspecialchars((string) ($filters['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. Ana Pérez">
            </div>

            <div class="field">
                <label for="grupo">Filtrar por institución</label>
                <select id="grupo" name="grupo">
                    <option value="">Todas las instituciones</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= htmlspecialchars((string) $group, ENT_QUOTES, 'UTF-8'); ?>" <?= ((string) ($filters['grupo'] ?? '') === (string) $group) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars((string) $group, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="fecha">Filtrar por fecha</label>
                <input id="fecha" name="fecha" type="date" value="<?= htmlspecialchars((string) ($filters['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>

        <div class="test-actions">
            <button class="btn-primary" type="submit">Aplicar filtros</button>
            <a class="btn-secondary" href="/admin/evaluaciones">Limpiar</a>
        </div>
    </form>

    <p class="hint">Total de registros: <strong><?= $total; ?></strong></p>

    <table class="results-table admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Institución</th>
            <th>Ubicación</th>
            <th>Fecha</th>
            <th>Validez</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($items === []): ?>
            <tr>
                <td colspan="7">No hay evaluaciones con los filtros seleccionados.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php
                $fullName = trim(sprintf(
                    '%s %s %s',
                    (string) ($item['first_name'] ?? ''),
                    (string) ($item['last_name'] ?? ''),
                    (string) ($item['middle_name'] ?? '')
                ));
                ?>
                <tr>
                    <td>#<?= (int) ($item['id'] ?? 0); ?></td>
                    <td><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) (($item['colegio_nombre'] ?? '') !== '' ? $item['colegio_nombre'] : ($item['group_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars(trim(implode(' / ', array_filter([(string) ($item['pais_nombre'] ?? ''), (string) ($item['departamento_nombre'] ?? ''), (string) ($item['municipio_nombre'] ?? '')]))), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) ($item['applied_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) ($item['validity_state'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="admin-actions">
                        <a href="/admin/evaluaciones/detalle?id=<?= (int) ($item['id'] ?? 0); ?>">Ver detalle</a>
                        <a href="/admin/evaluaciones/reimprimir?id=<?= (int) ($item['id'] ?? 0); ?>">Reimprimir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <nav class="pagination" aria-label="Paginación de evaluaciones">
        <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($buildUrl($page - 1), ENT_QUOTES, 'UTF-8'); ?>">← Anterior</a>
        <?php endif; ?>

        <span>Página <?= $page; ?> de <?= $totalPages; ?></span>

        <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars($buildUrl($page + 1), ENT_QUOTES, 'UTF-8'); ?>">Siguiente →</a>
        <?php endif; ?>
    </nav>
</section>

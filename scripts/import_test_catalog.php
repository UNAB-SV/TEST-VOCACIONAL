<?php

declare(strict_types=1);

use App\Repositories\PdoConnectionFactory;

require_once dirname(__DIR__) . '/src/helpers/Autoloader.php';

App\Helpers\Autoloader::addNamespace('App', dirname(__DIR__) . '/src');
App\Helpers\Autoloader::register();

/**
 * @param array<int, string> $argv
 */
function optionValue(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }

    return $default;
}

/**
 * @param array<int, string> $argv
 */
function hasFlag(array $argv, string $name): bool
{
    return in_array('--' . $name, $argv, true);
}

/**
 * @return array<string, mixed>
 */
function loadConfig(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('No existe el archivo de configuración: %s', $path));
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException(sprintf('El archivo de configuración no devolvió un arreglo: %s', $path));
    }

    return $config;
}

/**
 * @return array<string, mixed>
 */
function loadJson(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('No existe el JSON requerido: %s', $path));
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException(sprintf('No se pudo leer el JSON requerido: %s', $path));
    }

    $data = json_decode($content, true);
    if (!is_array($data)) {
        throw new RuntimeException(sprintf('JSON inválido: %s', $path));
    }

    return $data;
}

/**
 * @return array<string, mixed>
 */
function databaseConfig(array $appConfig): array
{
    $database = $appConfig['database'] ?? [];
    if (!is_array($database)) {
        $database = [];
    }

    return [
        'host' => getenv('DB_HOST') !== false ? (string) getenv('DB_HOST') : ($database['host'] ?? '127.0.0.1'),
        'port' => getenv('DB_PORT') !== false ? (int) getenv('DB_PORT') : ($database['port'] ?? 3306),
        'database' => getenv('DB_DATABASE') !== false ? (string) getenv('DB_DATABASE') : ($database['database'] ?? 'test_vocacional'),
        'username' => getenv('DB_USERNAME') !== false ? (string) getenv('DB_USERNAME') : ($database['username'] ?? ''),
        'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : ($database['password'] ?? ''),
        'charset' => getenv('DB_CHARSET') !== false ? (string) getenv('DB_CHARSET') : ($database['charset'] ?? 'utf8mb4'),
    ];
}

function applySchema(PDO $pdo, string $schemaPath): void
{
    if (!is_file($schemaPath)) {
        throw new RuntimeException(sprintf('No existe el SQL de esquema: %s', $schemaPath));
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException(sprintf('No se pudo leer el SQL de esquema: %s', $schemaPath));
    }

    $sqlWithoutLineComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sqlWithoutLineComments) ?: []),
        static fn (string $statement): bool => $statement !== ''
    );

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

/**
 * @param array<string, mixed> $values
 * @param array<int, string> $updateColumns
 */
function upsertReturningId(PDO $pdo, string $table, array $values, array $updateColumns): int
{
    $columns = array_keys($values);
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
    $updates = ['id = LAST_INSERT_ID(id)'];

    foreach ($updateColumns as $column) {
        $updates[] = sprintf('%s = VALUES(%s)', $column, $column);
    }

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        $table,
        implode(', ', $columns),
        implode(', ', $placeholders),
        implode(', ', $updates)
    );

    $statement = $pdo->prepare($sql);
    foreach ($values as $column => $value) {
        $statement->bindValue(':' . $column, $value);
    }
    $statement->execute();

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string, mixed> $values
 * @param array<int, string> $updateColumns
 */
function upsert(PDO $pdo, string $table, array $values, array $updateColumns): void
{
    $columns = array_keys($values);
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
    $updates = [];

    foreach ($updateColumns as $column) {
        $updates[] = sprintf('%s = VALUES(%s)', $column, $column);
    }

    if ($updates === []) {
        $updates[] = sprintf('%s = %s', $columns[0], $columns[0]);
    }

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        $table,
        implode(', ', $columns),
        implode(', ', $placeholders),
        implode(', ', $updates)
    );

    $statement = $pdo->prepare($sql);
    foreach ($values as $column => $value) {
        $statement->bindValue(':' . $column, $value);
    }
    $statement->execute();
}

/**
 * @param array<int, int> $keepIds
 */
function deleteVersionRowsNotIn(PDO $pdo, string $table, int $catalogVersionId, array $keepIds): int
{
    if ($keepIds === []) {
        $statement = $pdo->prepare(sprintf('DELETE FROM %s WHERE catalog_version_id = :catalog_version_id', $table));
        $statement->execute(['catalog_version_id' => $catalogVersionId]);

        return $statement->rowCount();
    }

    $placeholders = [];
    $params = ['catalog_version_id' => $catalogVersionId];
    foreach (array_values(array_unique($keepIds)) as $index => $id) {
        $key = 'id_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $statement = $pdo->prepare(sprintf(
        'DELETE FROM %s WHERE catalog_version_id = :catalog_version_id AND id NOT IN (%s)',
        $table,
        implode(', ', $placeholders)
    ));
    $statement->execute($params);

    return $statement->rowCount();
}

function deleteValidityBaseIfMissing(PDO $pdo, int $catalogVersionId, bool $present): int
{
    if ($present) {
        return 0;
    }

    $statement = $pdo->prepare('DELETE FROM test_catalog_validity_base_rules WHERE catalog_version_id = :catalog_version_id');
    $statement->execute(['catalog_version_id' => $catalogVersionId]);

    return $statement->rowCount();
}

function deleteScoringMetadataIfMissing(PDO $pdo, int $catalogVersionId, bool $present): int
{
    if ($present) {
        return 0;
    }

    $statement = $pdo->prepare('DELETE FROM test_catalog_scoring_metadata WHERE catalog_version_id = :catalog_version_id');
    $statement->execute(['catalog_version_id' => $catalogVersionId]);

    return $statement->rowCount();
}

function countRows(PDO $pdo, string $sql, array $params): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetchRows(PDO $pdo, string $sql, array $params): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function failIfMissingArray(array $data, string $key, string $path): array
{
    $value = $data[$key] ?? null;
    if (!is_array($value)) {
        throw new RuntimeException(sprintf('El archivo %s no contiene la clave esperada "%s".', $path, $key));
    }

    return $value;
}

$root = dirname(__DIR__);
$argv = $_SERVER['argv'] ?? [];

if (hasFlag($argv, 'help')) {
    echo <<<HELP
Importa el catálogo JSON actual del Test Vocacional a MySQL.

Uso:
  php scripts/import_test_catalog.php [--apply-schema] [--activate] [--version-key=2026-04-24-json] [--name="Catálogo actual JSON"]

Opciones:
  --apply-schema   Ejecuta docs/sql/2026-04-24_test_catalog_schema.sql antes de importar.
  --activate       Marca esta versión como active y retira otras versiones active.
  --version-key    Clave estable de versión. Default: current-json.
  --name           Nombre descriptivo. Default: Catálogo actual JSON.
  --help           Muestra esta ayuda.

La conexión usa config/app.php y permite override por DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_CHARSET.

HELP;
    exit(0);
}

$versionKey = optionValue($argv, 'version-key', 'current-json');
$versionName = optionValue($argv, 'name', 'Catálogo actual JSON');
$applySchema = hasFlag($argv, 'apply-schema');
$activate = hasFlag($argv, 'activate');

try {
    $appConfig = loadConfig($root . '/config/app.php');
    $catalogConfig = loadConfig($root . '/config/test-vocacional/catalog.php');
    $files = $catalogConfig['files'] ?? null;
    if (!is_array($files)) {
        throw new RuntimeException('config/test-vocacional/catalog.php no contiene files válido.');
    }

    $paths = [
        'scales' => (string) ($files['scales'] ?? ''),
        'questions_blocks' => (string) ($files['questions_blocks'] ?? ''),
        'scoring_rules' => (string) ($files['scoring_rules'] ?? ''),
        'validity_rules' => (string) ($files['validity_rules'] ?? ''),
        'excel_mapping' => (string) ($files['excel_mapping'] ?? ''),
        'percentiles_male' => (string) ($files['percentiles']['M'] ?? ''),
        'percentiles_female' => (string) ($files['percentiles']['F'] ?? ''),
    ];

    $json = [];
    foreach ($paths as $logicalName => $path) {
        $json[$logicalName] = loadJson($path);
    }

    $pdo = PdoConnectionFactory::create(databaseConfig($appConfig));
    if ($applySchema) {
        applySchema($pdo, $root . '/docs/sql/2026-04-24_test_catalog_schema.sql');
    }

    $scales = failIfMissingArray($json['scales'], 'scales', $paths['scales']);
    $blocks = failIfMissingArray($json['questions_blocks'], 'blocks', $paths['questions_blocks']);
    $scoringRoot = failIfMissingArray($json['scoring_rules'], 'scoring_rules', $paths['scoring_rules']);
    $validityRoot = failIfMissingArray($json['validity_rules'], 'validity_rules', $paths['validity_rules']);

    $sourceHash = hash('sha256', implode('|', array_map(
        static fn (string $path): string => hash_file('sha256', $path) ?: '',
        $paths
    )));

    $pdo->beginTransaction();

    $catalogVersionId = upsertReturningId($pdo, 'test_catalog_versions', [
        'version_key' => $versionKey,
        'name' => $versionName,
        'source_name' => 'config/test-vocacional/*.json',
        'source_hash' => $sourceHash,
        'status' => 'draft',
        'notes' => 'Importado desde JSON actuales del repositorio.',
    ], ['name', 'source_name', 'source_hash', 'notes']);

    if ($activate) {
        $pdo->exec("UPDATE test_catalog_versions SET status = 'retired', retired_at = COALESCE(retired_at, CURRENT_TIMESTAMP) WHERE status = 'active'");
        $statement = $pdo->prepare("UPDATE test_catalog_versions SET status = 'active', activated_at = COALESCE(activated_at, CURRENT_TIMESTAMP), retired_at = NULL WHERE id = :id");
        $statement->execute(['id' => $catalogVersionId]);
    }

    $keptSourceFileIds = [];
    foreach ($paths as $logicalName => $path) {
        $keptSourceFileIds[] = upsertReturningId($pdo, 'test_catalog_source_files', [
            'catalog_version_id' => $catalogVersionId,
            'logical_name' => $logicalName,
            'source_path' => str_replace($root . '/', '', $path),
            'sha256_hash' => hash_file('sha256', $path),
        ], ['source_path', 'sha256_hash', 'imported_at']);
    }

    $excelColumnsByScale = [];
    foreach (($scoringRoot['escalas_columnas_excel'] ?? []) as $definition) {
        if (!is_array($definition)) {
            continue;
        }
        $scaleKey = (string) ($definition['escala_id'] ?? '');
        if ($scaleKey !== '') {
            $excelColumnsByScale[$scaleKey] = $definition;
        }
    }

    $scaleIdsByKey = [];
    $keptScaleIds = [];
    foreach (array_values($scales) as $index => $scale) {
        if (!is_array($scale)) {
            continue;
        }
        $scaleKey = (string) ($scale['id'] ?? '');
        if ($scaleKey === '') {
            throw new RuntimeException('Hay una escala sin id en scales.json.');
        }

        $excel = $excelColumnsByScale[$scaleKey] ?? [];
        $scaleId = upsertReturningId($pdo, 'test_catalog_scales', [
            'catalog_version_id' => $catalogVersionId,
            'scale_key' => $scaleKey,
            'name' => (string) ($scale['nombre'] ?? ''),
            'scale_group' => (string) ($scale['grupo'] ?? ''),
            'display_order' => $index + 1,
            'excel_code' => isset($excel['codigo_excel']) ? (string) $excel['codigo_excel'] : null,
            'excel_code_column' => isset($excel['columna_codigo']) ? (string) $excel['columna_codigo'] : null,
            'excel_mas_column' => isset($excel['columna_mas']) ? (string) $excel['columna_mas'] : null,
            'excel_menos_column' => isset($excel['columna_menos']) ? (string) $excel['columna_menos'] : null,
            'marker_weight' => (int) ($excel['peso_marcador'] ?? 1),
        ], ['name', 'scale_group', 'display_order', 'excel_code', 'excel_code_column', 'excel_mas_column', 'excel_menos_column', 'marker_weight']);

        $scaleIdsByKey[$scaleKey] = $scaleId;
        $keptScaleIds[] = $scaleId;
    }

    $blockIdsByKey = [];
    $keptBlockIds = [];
    $keptActivityIds = [];
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $blockKey = (string) ($block['id'] ?? '');
        if ($blockKey === '') {
            throw new RuntimeException('Hay un bloque sin id en questions_blocks.json.');
        }

        $blockId = upsertReturningId($pdo, 'test_catalog_blocks', [
            'catalog_version_id' => $catalogVersionId,
            'block_key' => $blockKey,
            'display_order' => (int) ($block['orden'] ?? 0),
        ], ['display_order']);

        $blockIdsByKey[$blockKey] = $blockId;
        $keptBlockIds[] = $blockId;

        $activities = $block['actividades'] ?? [];
        if (!is_array($activities)) {
            throw new RuntimeException(sprintf('El bloque %s no contiene actividades válidas.', $blockKey));
        }

        foreach ($activities as $activity) {
            if (!is_array($activity)) {
                continue;
            }
            $activityKey = (string) ($activity['id'] ?? '');
            if ($activityKey === '') {
                throw new RuntimeException(sprintf('El bloque %s contiene una actividad sin id.', $blockKey));
            }

            $keptActivityIds[] = upsertReturningId($pdo, 'test_catalog_activities', [
                'catalog_version_id' => $catalogVersionId,
                'block_id' => $blockId,
                'activity_key' => $activityKey,
                'activity_text' => (string) ($activity['texto'] ?? ''),
                'position_in_block' => (int) ($activity['indice_en_bloque'] ?? 0),
            ], ['block_id', 'activity_text', 'position_in_block']);
        }
    }

    $keptResponseRequirementIds = [];
    foreach (($scoringRoot['respuesta_por_bloque'] ?? []) as $side => $definition) {
        if (!is_array($definition)) {
            continue;
        }
        $keptResponseRequirementIds[] = upsertReturningId($pdo, 'test_catalog_response_requirements', [
            'catalog_version_id' => $catalogVersionId,
            'response_side' => (string) $side,
            'required_count' => (int) ($definition['requerido'] ?? 0),
        ], ['required_count']);
    }

    $hasScoringMetadata = isset($scoringRoot['modelo']) || isset($scoringRoot['fuente']) || isset($scoringRoot['formula']);
    if ($hasScoringMetadata) {
        upsert($pdo, 'test_catalog_scoring_metadata', [
            'catalog_version_id' => $catalogVersionId,
            'model' => (string) ($scoringRoot['modelo'] ?? ''),
            'source_name' => isset($scoringRoot['fuente']) ? (string) $scoringRoot['fuente'] : null,
            'formula' => isset($scoringRoot['formula']) ? (string) $scoringRoot['formula'] : null,
        ], ['model', 'source_name', 'formula']);
    }

    $keptScoringPositionIds = [];
    foreach (($scoringRoot['matriz_por_bloque'] ?? []) as $blockKey => $positions) {
        if (!isset($blockIdsByKey[(string) $blockKey]) || !is_array($positions)) {
            continue;
        }

        foreach ($positions as $position => $positionDefinition) {
            if (!is_array($positionDefinition)) {
                continue;
            }

            $masDefinition = is_array($positionDefinition['mas'] ?? null) ? $positionDefinition['mas'] : [];
            $menosDefinition = is_array($positionDefinition['menos'] ?? null) ? $positionDefinition['menos'] : [];

            $keptScoringPositionIds[] = upsertReturningId($pdo, 'test_catalog_scoring_positions', [
                'catalog_version_id' => $catalogVersionId,
                'block_id' => $blockIdsByKey[(string) $blockKey],
                'position_in_block' => (int) $position,
                'excel_row' => isset($positionDefinition['fila_excel']) ? (int) $positionDefinition['fila_excel'] : null,
                'mas_rule_type' => (string) ($masDefinition['rule'] ?? 'sumar_peso_directo'),
                'menos_rule_type' => (string) ($menosDefinition['rule'] ?? 'sumar_peso_directo'),
            ], ['excel_row', 'mas_rule_type', 'menos_rule_type']);
        }
    }

    $keptScoringRuleIds = [];
    foreach (($scoringRoot['matriz_por_bloque'] ?? []) as $blockKey => $positions) {
        if (!isset($blockIdsByKey[(string) $blockKey]) || !is_array($positions)) {
            continue;
        }
        foreach ($positions as $position => $positionDefinition) {
            if (!is_array($positionDefinition)) {
                continue;
            }
            foreach (['mas', 'menos'] as $side) {
                $sideDefinition = $positionDefinition[$side] ?? [];
                $sideScales = is_array($sideDefinition) ? ($sideDefinition['scales'] ?? []) : [];
                if (!is_array($sideScales)) {
                    continue;
                }
                foreach ($sideScales as $scaleKey => $weight) {
                    if (!isset($scaleIdsByKey[(string) $scaleKey])) {
                        throw new RuntimeException(sprintf('La regla de scoring referencia una escala inexistente: %s.', (string) $scaleKey));
                    }

                    $keptScoringRuleIds[] = upsertReturningId($pdo, 'test_catalog_scoring_rules', [
                        'catalog_version_id' => $catalogVersionId,
                        'block_id' => $blockIdsByKey[(string) $blockKey],
                        'position_in_block' => (int) $position,
                        'response_side' => $side,
                        'scale_id' => $scaleIdsByKey[(string) $scaleKey],
                        'weight' => (int) $weight,
                        'rule_type' => (string) ($sideDefinition['rule'] ?? 'sumar_peso_directo'),
                        'excel_row' => isset($positionDefinition['fila_excel']) ? (int) $positionDefinition['fila_excel'] : null,
                    ], ['weight', 'rule_type', 'excel_row']);
                }
            }
        }
    }

    $baseRules = $validityRoot['requerimientos_base'] ?? null;
    $hasValidityBase = is_array($baseRules);
    if ($hasValidityBase) {
        upsert($pdo, 'test_catalog_validity_base_rules', [
            'catalog_version_id' => $catalogVersionId,
            'mas_per_block' => (int) ($baseRules['mas_por_bloque'] ?? 1),
            'menos_per_block' => (int) ($baseRules['menos_por_bloque'] ?? 1),
            'allow_duplicate_in_block' => !empty($baseRules['permitir_duplicado_en_bloque']) ? 1 : 0,
            'status_note' => isset($validityRoot['estado_reglas']) ? (string) $validityRoot['estado_reglas'] : null,
            'notes' => isset($validityRoot['nota']) ? (string) $validityRoot['nota'] : null,
        ], ['mas_per_block', 'menos_per_block', 'allow_duplicate_in_block', 'status_note', 'notes']);
    }

    $keptValidityMetricIds = [];
    foreach (array_values(($validityRoot['metricas'] ?? [])) as $index => $metric) {
        if (!is_array($metric)) {
            continue;
        }
        $metricKey = (string) ($metric['id'] ?? '');
        if ($metricKey === '') {
            throw new RuntimeException('Hay una métrica de validez sin id.');
        }
        $keptValidityMetricIds[] = upsertReturningId($pdo, 'test_catalog_validity_metrics', [
            'catalog_version_id' => $catalogVersionId,
            'metric_key' => $metricKey,
            'formula' => (string) ($metric['formula'] ?? ''),
            'invalid_threshold' => array_key_exists('umbral_invalido', $metric) ? (int) $metric['umbral_invalido'] : null,
            'display_order' => $index + 1,
        ], ['formula', 'invalid_threshold', 'display_order']);
    }

    $keptValidityDecisionIds = [];
    foreach (array_values(($validityRoot['decision'] ?? [])) as $index => $rule) {
        if (!is_array($rule)) {
            continue;
        }
        $ruleKey = (string) ($rule['id'] ?? '');
        if ($ruleKey === '') {
            throw new RuntimeException('Hay una regla de decisión de validez sin id.');
        }
        $keptValidityDecisionIds[] = upsertReturningId($pdo, 'test_catalog_validity_decision_rules', [
            'catalog_version_id' => $catalogVersionId,
            'rule_key' => $ruleKey,
            'condition_expression' => (string) ($rule['si'] ?? ''),
            'resulting_state' => (string) ($rule['estado'] ?? 'desconocido'),
            'priority' => $index + 1,
            'notes' => null,
        ], ['condition_expression', 'resulting_state', 'priority', 'notes']);
    }

    $keptPercentileSetIds = [];
    $keptPercentileIds = [];
    foreach (['percentiles_male', 'percentiles_female'] as $logicalName) {
        $percentileJson = $json[$logicalName];
        $sex = strtoupper((string) ($percentileJson['sexo'] ?? ''));
        if ($sex === '') {
            throw new RuntimeException(sprintf('El archivo %s no contiene sexo.', $paths[$logicalName]));
        }

        $percentileSetId = upsertReturningId($pdo, 'test_catalog_percentile_sets', [
            'catalog_version_id' => $catalogVersionId,
            'sex' => $sex,
            'lookup_method' => (string) ($percentileJson['lookup_method'] ?? 'floor'),
            'source_name' => isset($percentileJson['fuente']) ? (string) $percentileJson['fuente'] : null,
        ], ['lookup_method', 'source_name']);
        $keptPercentileSetIds[] = $percentileSetId;

        $percentilesByScale = $percentileJson['percentiles'] ?? [];
        if (!is_array($percentilesByScale)) {
            throw new RuntimeException(sprintf('El archivo %s no contiene percentiles válidos.', $paths[$logicalName]));
        }

        foreach ($percentilesByScale as $scaleKey => $rows) {
            if (!isset($scaleIdsByKey[(string) $scaleKey]) || !is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $keptPercentileIds[] = upsertReturningId($pdo, 'test_catalog_percentiles', [
                    'catalog_version_id' => $catalogVersionId,
                    'percentile_set_id' => $percentileSetId,
                    'scale_id' => $scaleIdsByKey[(string) $scaleKey],
                    'raw_score' => (int) ($row['bruto'] ?? 0),
                    'percentile_value' => (int) ($row['percentil'] ?? 0),
                ], ['percentile_value']);
            }
        }
    }

    $deleted = [
        'source_files' => deleteVersionRowsNotIn($pdo, 'test_catalog_source_files', $catalogVersionId, $keptSourceFileIds),
        'response_requirements' => deleteVersionRowsNotIn($pdo, 'test_catalog_response_requirements', $catalogVersionId, $keptResponseRequirementIds),
        'scoring_metadata' => deleteScoringMetadataIfMissing($pdo, $catalogVersionId, $hasScoringMetadata),
        'scoring_rules' => deleteVersionRowsNotIn($pdo, 'test_catalog_scoring_rules', $catalogVersionId, $keptScoringRuleIds),
        'scoring_positions' => deleteVersionRowsNotIn($pdo, 'test_catalog_scoring_positions', $catalogVersionId, $keptScoringPositionIds),
        'validity_base_rules' => deleteValidityBaseIfMissing($pdo, $catalogVersionId, $hasValidityBase),
        'validity_metrics' => deleteVersionRowsNotIn($pdo, 'test_catalog_validity_metrics', $catalogVersionId, $keptValidityMetricIds),
        'validity_decision_rules' => deleteVersionRowsNotIn($pdo, 'test_catalog_validity_decision_rules', $catalogVersionId, $keptValidityDecisionIds),
        'percentiles' => deleteVersionRowsNotIn($pdo, 'test_catalog_percentiles', $catalogVersionId, $keptPercentileIds),
        'percentile_sets' => deleteVersionRowsNotIn($pdo, 'test_catalog_percentile_sets', $catalogVersionId, $keptPercentileSetIds),
        'activities' => deleteVersionRowsNotIn($pdo, 'test_catalog_activities', $catalogVersionId, $keptActivityIds),
        'blocks' => deleteVersionRowsNotIn($pdo, 'test_catalog_blocks', $catalogVersionId, $keptBlockIds),
        'scales' => deleteVersionRowsNotIn($pdo, 'test_catalog_scales', $catalogVersionId, $keptScaleIds),
    ];

    $report = [
        'catalog_version_id' => $catalogVersionId,
        'version_key' => $versionKey,
        'activated' => $activate,
        'source_hash' => $sourceHash,
        'counts' => [
            'scales' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_scales WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'blocks' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_blocks WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'activities' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_activities WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'response_requirements' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_response_requirements WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'scoring_metadata' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_scoring_metadata WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'scoring_positions' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_scoring_positions WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'scoring_rules' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_scoring_rules WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'validity_metrics' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_validity_metrics WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'validity_decision_rules' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_validity_decision_rules WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'percentile_sets' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_percentile_sets WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'percentiles_total' => countRows($pdo, 'SELECT COUNT(*) FROM test_catalog_percentiles WHERE catalog_version_id = :catalog_version_id', ['catalog_version_id' => $catalogVersionId]),
            'percentiles_male' => countRows($pdo, "SELECT COUNT(*) FROM test_catalog_percentiles p INNER JOIN test_catalog_percentile_sets s ON s.id = p.percentile_set_id WHERE p.catalog_version_id = :catalog_version_id AND s.sex = 'M'", ['catalog_version_id' => $catalogVersionId]),
            'percentiles_female' => countRows($pdo, "SELECT COUNT(*) FROM test_catalog_percentiles p INNER JOIN test_catalog_percentile_sets s ON s.id = p.percentile_set_id WHERE p.catalog_version_id = :catalog_version_id AND s.sex = 'F'", ['catalog_version_id' => $catalogVersionId]),
        ],
        'breakdowns' => [
            'scoring_rules_by_side' => fetchRows(
                $pdo,
                'SELECT response_side, COUNT(*) AS total FROM test_catalog_scoring_rules WHERE catalog_version_id = :catalog_version_id GROUP BY response_side ORDER BY response_side',
                ['catalog_version_id' => $catalogVersionId]
            ),
            'percentiles_by_sex' => fetchRows(
                $pdo,
                'SELECT s.sex, COUNT(*) AS total FROM test_catalog_percentile_sets s INNER JOIN test_catalog_percentiles p ON p.percentile_set_id = s.id WHERE s.catalog_version_id = :catalog_version_id GROUP BY s.sex ORDER BY s.sex',
                ['catalog_version_id' => $catalogVersionId]
            ),
        ],
        'integrity_checks' => [
            'blocks_with_activity_count_not_equal_3' => countRows(
                $pdo,
                'SELECT COUNT(*) FROM (SELECT b.id FROM test_catalog_blocks b LEFT JOIN test_catalog_activities a ON a.block_id = b.id WHERE b.catalog_version_id = :catalog_version_id GROUP BY b.id HAVING COUNT(a.id) <> 3) invalid_blocks',
                ['catalog_version_id' => $catalogVersionId]
            ),
        ],
        'deleted_stale_rows' => $deleted,
    ];

    $expected = [
        'scales' => count($scales),
        'blocks' => count($blocks),
        'activities' => array_sum(array_map(static fn (array $block): int => is_array($block['actividades'] ?? null) ? count($block['actividades']) : 0, $blocks)),
    ];

    foreach ($expected as $key => $value) {
        if (($report['counts'][$key] ?? null) !== $value) {
            throw new RuntimeException(sprintf('Validación fallida: %s esperado %d, obtenido %d.', $key, $value, (int) ($report['counts'][$key] ?? 0)));
        }
    }

    if ($report['integrity_checks']['blocks_with_activity_count_not_equal_3'] !== 0) {
        throw new RuntimeException('Validación fallida: existen bloques con cantidad distinta de 3 actividades.');
    }

    $reportPath = $root . '/storage/logs/catalog_import_report.json';
    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);

    $pdo->commit();

    echo "Importación completada.\n";
    echo sprintf("Versión: %s (id %d)\n", $versionKey, $catalogVersionId);
    foreach ($report['counts'] as $key => $value) {
        echo sprintf("- %s: %d\n", $key, $value);
    }
    echo sprintf("Reporte: %s\n", str_replace($root . '/', '', $reportPath));
} catch (Throwable $throwable) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Error importando catálogo: ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

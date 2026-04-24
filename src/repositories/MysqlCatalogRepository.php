<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class MysqlCatalogRepository implements CatalogRepository
{
    private ?int $catalogVersionId = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?string $versionKey = null
    ) {
    }

    public function questionsDefinition(): array
    {
        $blocks = $this->fetchAll(
            'SELECT id, block_key, display_order
             FROM test_catalog_blocks
             WHERE catalog_version_id = :catalog_version_id
             ORDER BY display_order ASC',
            ['catalog_version_id' => $this->catalogVersionId()]
        );

        $activities = $this->fetchAll(
            'SELECT block_id, activity_key, activity_text, position_in_block
             FROM test_catalog_activities
             WHERE catalog_version_id = :catalog_version_id
             ORDER BY block_id ASC, position_in_block ASC',
            ['catalog_version_id' => $this->catalogVersionId()]
        );

        $activitiesByBlock = [];
        foreach ($activities as $activity) {
            $activitiesByBlock[(int) $activity['block_id']][] = [
                'id' => (string) $activity['activity_key'],
                'texto' => (string) $activity['activity_text'],
                'bloque' => '',
                'indice_en_bloque' => (int) $activity['position_in_block'],
            ];
        }

        $result = [];
        foreach ($blocks as $block) {
            $blockId = (int) $block['id'];
            $blockKey = (string) $block['block_key'];
            $blockActivities = $activitiesByBlock[$blockId] ?? [];
            foreach ($blockActivities as &$activity) {
                $activity['bloque'] = $blockKey;
            }
            unset($activity);

            $result[] = [
                'id' => $blockKey,
                'orden' => (int) $block['display_order'],
                'actividades' => $blockActivities,
            ];
        }

        return ['blocks' => $result];
    }

    public function scalesConfig(): array
    {
        $rows = $this->fetchAll(
            'SELECT scale_key, name, scale_group
             FROM test_catalog_scales
             WHERE catalog_version_id = :catalog_version_id
             ORDER BY display_order ASC',
            ['catalog_version_id' => $this->catalogVersionId()]
        );

        return [
            'scales' => array_map(static fn (array $row): array => [
                'id' => (string) $row['scale_key'],
                'nombre' => (string) $row['name'],
                'grupo' => (string) $row['scale_group'],
            ], $rows),
        ];
    }

    public function scoringRules(): array
    {
        $versionId = $this->catalogVersionId();
        $metadata = $this->fetchOne(
            'SELECT model, source_name, formula
             FROM test_catalog_scoring_metadata
             WHERE catalog_version_id = :catalog_version_id',
            ['catalog_version_id' => $versionId]
        ) ?? [];

        $requirements = $this->fetchAll(
            'SELECT response_side, required_count
             FROM test_catalog_response_requirements
             WHERE catalog_version_id = :catalog_version_id
             ORDER BY response_side ASC',
            ['catalog_version_id' => $versionId]
        );

        $scaleDefinitions = $this->fetchAll(
            'SELECT scale_key, excel_code, excel_code_column, excel_mas_column, excel_menos_column, marker_weight
             FROM test_catalog_scales
             WHERE catalog_version_id = :catalog_version_id
             ORDER BY display_order ASC',
            ['catalog_version_id' => $versionId]
        );

        $positions = $this->fetchAll(
            'SELECT b.block_key, p.position_in_block, p.excel_row, p.mas_rule_type, p.menos_rule_type
             FROM test_catalog_scoring_positions p
             INNER JOIN test_catalog_blocks b ON b.id = p.block_id
             WHERE p.catalog_version_id = :catalog_version_id
             ORDER BY b.display_order ASC, p.position_in_block ASC',
            ['catalog_version_id' => $versionId]
        );

        $rules = $this->fetchAll(
            'SELECT b.block_key, r.position_in_block, r.response_side, r.weight, r.rule_type, r.excel_row, s.scale_key
             FROM test_catalog_scoring_rules r
             INNER JOIN test_catalog_blocks b ON b.id = r.block_id
             INNER JOIN test_catalog_scales s ON s.id = r.scale_id
             WHERE r.catalog_version_id = :catalog_version_id
             ORDER BY b.display_order ASC, r.position_in_block ASC, r.response_side ASC, s.display_order ASC',
            ['catalog_version_id' => $versionId]
        );

        $responseRequirements = [];
        foreach ($requirements as $row) {
            $responseRequirements[(string) $row['response_side']] = ['requerido' => (int) $row['required_count']];
        }

        $matrix = [];
        foreach ($positions as $row) {
            $blockKey = (string) $row['block_key'];
            $position = (string) ((int) $row['position_in_block']);
            $matrix[$blockKey][$position] = [
                'fila_excel' => (int) $row['excel_row'],
                'mas' => ['scales' => [], 'rule' => (string) $row['mas_rule_type']],
                'menos' => ['scales' => [], 'rule' => (string) $row['menos_rule_type']],
            ];
        }

        foreach ($rules as $row) {
            $blockKey = (string) $row['block_key'];
            $position = (string) ((int) $row['position_in_block']);
            $side = (string) $row['response_side'];

            if (!isset($matrix[$blockKey][$position])) {
                $matrix[$blockKey][$position] = [
                    'fila_excel' => (int) $row['excel_row'],
                    'mas' => ['scales' => [], 'rule' => 'sumar_peso_directo'],
                    'menos' => ['scales' => [], 'rule' => 'sumar_peso_directo'],
                ];
            }

            $matrix[$blockKey][$position][$side]['rule'] = (string) $row['rule_type'];
            $matrix[$blockKey][$position][$side]['scales'][(string) $row['scale_key']] = (int) $row['weight'];
        }

        return [
            'scoring_rules' => [
                'modelo' => (string) ($metadata['model'] ?? 'por_bloque_indice_respuesta'),
                'fuente' => (string) ($metadata['source_name'] ?? ''),
                'respuesta_por_bloque' => $responseRequirements,
                'formula' => (string) ($metadata['formula'] ?? ''),
                'escalas_columnas_excel' => array_map(static fn (array $row): array => [
                    'escala_id' => (string) $row['scale_key'],
                    'codigo_excel' => (string) ($row['excel_code'] ?? ''),
                    'columna_codigo' => (string) ($row['excel_code_column'] ?? ''),
                    'columna_mas' => (string) ($row['excel_mas_column'] ?? ''),
                    'columna_menos' => (string) ($row['excel_menos_column'] ?? ''),
                    'peso_marcador' => (int) $row['marker_weight'],
                ], $scaleDefinitions),
                'matriz_por_bloque' => $matrix,
            ],
        ];
    }

    public function validityRules(): array
    {
        $versionId = $this->catalogVersionId();
        $baseRules = $this->fetchOne(
            'SELECT mas_per_block, menos_per_block, allow_duplicate_in_block, status_note, notes
             FROM test_catalog_validity_base_rules
             WHERE catalog_version_id = :catalog_version_id',
            ['catalog_version_id' => $versionId]
        );

        $metrics = $this->fetchAll(
            'SELECT metric_key, formula, invalid_threshold
             FROM test_catalog_validity_metrics
             WHERE catalog_version_id = :catalog_version_id
             ORDER BY display_order ASC',
            ['catalog_version_id' => $versionId]
        );

        $decisions = $this->fetchAll(
            'SELECT rule_key, condition_expression, resulting_state
             FROM test_catalog_validity_decision_rules
             WHERE catalog_version_id = :catalog_version_id
             ORDER BY priority ASC',
            ['catalog_version_id' => $versionId]
        );

        return [
            'validity_rules' => [
                'requerimientos_base' => [
                    'mas_por_bloque' => (int) ($baseRules['mas_per_block'] ?? 1),
                    'menos_por_bloque' => (int) ($baseRules['menos_per_block'] ?? 1),
                    'permitir_duplicado_en_bloque' => (bool) ($baseRules['allow_duplicate_in_block'] ?? false),
                ],
                'metricas' => array_map(static function (array $row): array {
                    $metric = [
                        'id' => (string) $row['metric_key'],
                        'formula' => (string) $row['formula'],
                    ];
                    if ($row['invalid_threshold'] !== null) {
                        $metric['umbral_invalido'] = (int) $row['invalid_threshold'];
                    }

                    return $metric;
                }, $metrics),
                'decision' => array_map(static fn (array $row): array => [
                    'id' => (string) $row['rule_key'],
                    'si' => (string) $row['condition_expression'],
                    'estado' => (string) $row['resulting_state'],
                ], $decisions),
                'estado_reglas' => (string) ($baseRules['status_note'] ?? ''),
                'nota' => (string) ($baseRules['notes'] ?? ''),
            ],
        ];
    }

    public function percentilesBySex(string $sex): array
    {
        $normalizedSex = strtoupper(trim($sex));
        if ($normalizedSex === '') {
            throw new RuntimeException('El sexo es obligatorio para leer percentiles.');
        }

        $set = $this->fetchOne(
            'SELECT id, sex, lookup_method, source_name
             FROM test_catalog_percentile_sets
             WHERE catalog_version_id = :catalog_version_id AND sex = :sex',
            ['catalog_version_id' => $this->catalogVersionId(), 'sex' => $normalizedSex]
        );

        if ($set === null) {
            throw new RuntimeException(sprintf('No existe tabla de percentiles MySQL para el sexo "%s".', $normalizedSex));
        }

        $rows = $this->fetchAll(
            'SELECT s.scale_key, p.raw_score, p.percentile_value
             FROM test_catalog_percentiles p
             INNER JOIN test_catalog_scales s ON s.id = p.scale_id
             WHERE p.percentile_set_id = :percentile_set_id
             ORDER BY s.display_order ASC, p.raw_score ASC',
            ['percentile_set_id' => (int) $set['id']]
        );

        $percentiles = [];
        foreach ($rows as $row) {
            $percentiles[(string) $row['scale_key']][] = [
                'bruto' => (int) $row['raw_score'],
                'percentil' => (int) $row['percentile_value'],
            ];
        }

        return [
            'sexo' => (string) $set['sex'],
            'fuente' => (string) ($set['source_name'] ?? ''),
            'lookup_method' => (string) $set['lookup_method'],
            'percentiles' => $percentiles,
        ];
    }

    private function catalogVersionId(): int
    {
        if ($this->catalogVersionId !== null) {
            return $this->catalogVersionId;
        }

        if ($this->versionKey !== null && trim($this->versionKey) !== '') {
            $row = $this->fetchOne(
                'SELECT id FROM test_catalog_versions WHERE version_key = :version_key',
                ['version_key' => $this->versionKey]
            );
        } else {
            $row = $this->fetchOne(
                "SELECT id FROM test_catalog_versions WHERE status = 'active' ORDER BY activated_at DESC, id DESC LIMIT 1",
                []
            );
        }

        if ($row === null) {
            throw new RuntimeException('No existe una versión de catálogo MySQL disponible.');
        }

        $this->catalogVersionId = (int) $row['id'];

        return $this->catalogVersionId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $sql, array $params): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}

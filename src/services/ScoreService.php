<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class ScoreService
{
    /**
     * @param array<string, array{mas: string, menos: string}> $answers
     * @param array<string, mixed> $questionsDefinition
     * @param array<string, mixed> $scoringRules
     * @param array<string, mixed> $percentilesBySex
     * @param array<string, mixed> $validityRules
     * @return array<string, mixed>
     */
    public function calculate(
        array $answers,
        array $questionsDefinition,
        array $scoringRules,
        array $percentilesBySex,
        string $sex,
        array $validityRules
    ): array {
        $this->validateInputs($answers, $questionsDefinition, $scoringRules, $percentilesBySex, $sex, $validityRules);

        $blockIndex = $this->buildBlockIndex($questionsDefinition);
        $scaleIds = $this->collectScaleIds($questionsDefinition);
        $rawScores = $this->calculateRawScores($answers, $blockIndex, $scaleIds, $scoringRules);

        $validityScore = (int) ($rawScores['validez'] ?? 0);
        $validityMetrics = $this->calculateValidityMetrics($answers, $validityScore, $validityRules);
        $validityState = $this->resolveValidityState($validityMetrics, $validityRules);

        $percentiles = $this->calculatePercentiles($rawScores, $percentilesBySex);

        return [
            'puntajes_brutos' => $rawScores,
            'percentiles' => $percentiles,
            'validez_puntaje' => $validityScore,
            'validez_estado' => $validityState,
            'escalas_ordenadas_de_mayor_a_menor' => $this->sortScalesByRawScore($rawScores),
            'detalles_validez' => $validityMetrics,
            'sexo_evaluado' => strtoupper($sex),
        ];
    }

    /**
     * @param array<string, array{mas: string, menos: string}> $answers
     * @param array<string, mixed> $questionsDefinition
     * @param array<string, mixed> $scoringRules
     * @param array<string, mixed> $percentilesBySex
     * @param array<string, mixed> $validityRules
     */
    private function validateInputs(
        array $answers,
        array $questionsDefinition,
        array $scoringRules,
        array $percentilesBySex,
        string $sex,
        array $validityRules
    ): void {
        if ($answers === []) {
            throw new InvalidArgumentException('No se recibieron respuestas para calificar.');
        }

        if (!isset($questionsDefinition['blocks']) || !is_array($questionsDefinition['blocks'])) {
            throw new InvalidArgumentException('La definición de preguntas no contiene bloques válidos.');
        }

        if (!isset($scoringRules['scoring_rules']) || !is_array($scoringRules['scoring_rules'])) {
            throw new InvalidArgumentException('Las reglas de puntuación tienen un formato inválido.');
        }

        if (!isset($percentilesBySex['percentiles']) || !is_array($percentilesBySex['percentiles'])) {
            throw new InvalidArgumentException('La tabla de percentiles por sexo tiene un formato inválido.');
        }

        if (!isset($validityRules['validity_rules']) || !is_array($validityRules['validity_rules'])) {
            throw new InvalidArgumentException('Las reglas de validez tienen un formato inválido.');
        }

        $normalizedSex = strtoupper(trim($sex));
        if ($normalizedSex === '') {
            throw new InvalidArgumentException('El sexo del evaluado es obligatorio para calcular percentiles.');
        }
    }

    /**
     * @param array<string, mixed> $questionsDefinition
     * @return array<string, array<string, mixed>>
     */
    private function buildBlockIndex(array $questionsDefinition): array
    {
        $index = [];

        foreach ($questionsDefinition['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $blockId = (string) ($block['id'] ?? '');
            if ($blockId === '') {
                continue;
            }

            $activitiesById = [];
            $activities = $block['actividades'] ?? [];
            if (is_array($activities)) {
                foreach ($activities as $activity) {
                    if (!is_array($activity)) {
                        continue;
                    }

                    $activityId = (string) ($activity['id'] ?? '');
                    if ($activityId !== '') {
                        $activitiesById[$activityId] = $activity;
                    }
                }
            }

            $index[$blockId] = [
                'id' => $blockId,
                'actividades' => $activitiesById,
            ];
        }

        if ($index === []) {
            throw new RuntimeException('No se pudo construir el índice de bloques para calificar.');
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $questionsDefinition
     * @return array<int, string>
     */
    private function collectScaleIds(array $questionsDefinition): array
    {
        $scaleIds = [];

        foreach ($questionsDefinition['blocks'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $activities = $block['actividades'] ?? [];
            if (!is_array($activities)) {
                continue;
            }

            foreach ($activities as $activity) {
                if (!is_array($activity)) {
                    continue;
                }

                $keys = $activity['claves'] ?? [];
                foreach (['mas', 'menos'] as $side) {
                    $scales = $keys[$side] ?? [];
                    if (!is_array($scales)) {
                        continue;
                    }

                    foreach (array_keys($scales) as $scaleId) {
                        $scaleIds[] = (string) $scaleId;
                    }
                }
            }
        }

        $scaleIds[] = 'validez';

        return array_values(array_unique(array_filter($scaleIds, static fn (string $id): bool => $id !== '')));
    }

    /**
     * @param array<string, array{mas: string, menos: string}> $answers
     * @param array<string, array<string, mixed>> $blockIndex
     * @param array<int, string> $scaleIds
     * @param array<string, mixed> $scoringRules
     * @return array<string, int>
     */
    private function calculateRawScores(array $answers, array $blockIndex, array $scaleIds, array $scoringRules): array
    {
        $scores = [];
        foreach ($scaleIds as $scaleId) {
            $scores[$scaleId] = 0;
        }

        foreach ($answers as $blockId => $answer) {
            if (!isset($blockIndex[$blockId])) {
                throw new InvalidArgumentException(sprintf('No existe el bloque "%s" en la definición de preguntas.', $blockId));
            }

            $this->applySideScore((string) $answer['mas'], 'mas', $blockIndex[$blockId], $scores, $scoringRules);
            $this->applySideScore((string) $answer['menos'], 'menos', $blockIndex[$blockId], $scores, $scoringRules);
        }

        return $scores;
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, int> $scores
     * @param array<string, mixed> $scoringRules
     */
    private function applySideScore(
        string $activityId,
        string $side,
        array $block,
        array &$scores,
        array $scoringRules
    ): void {
        $activities = $block['actividades'] ?? [];
        if (!is_array($activities) || !isset($activities[$activityId]) || !is_array($activities[$activityId])) {
            throw new InvalidArgumentException(sprintf(
                'La actividad "%s" no existe en el bloque "%s".',
                $activityId,
                (string) ($block['id'] ?? '')
            ));
        }

        $activity = $activities[$activityId];
        $keys = $activity['claves'][$side] ?? null;
        if (!is_array($keys)) {
            return;
        }

        foreach ($keys as $scaleId => $keyWeight) {
            $weight = (int) $keyWeight;
            $multiplier = $this->resolveMultiplier((string) $scaleId, $side, $scoringRules);
            if (!array_key_exists((string) $scaleId, $scores)) {
                $scores[(string) $scaleId] = 0;
            }

            $scores[(string) $scaleId] += (int) round($weight * $multiplier);
        }
    }

    /**
     * @param array<string, mixed> $scoringRules
     */
    private function resolveMultiplier(string $scaleId, string $side, array $scoringRules): float
    {
        $rules = $scoringRules['scoring_rules'] ?? [];
        $defaultMultiplier = (float) (($rules['respuesta_por_bloque'][$side]['multiplicador'] ?? 0));

        $overrides = $rules['overrides'] ?? [];
        if (!is_array($overrides)) {
            return $defaultMultiplier;
        }

        foreach ($overrides as $override) {
            if (!is_array($override)) {
                continue;
            }

            if (($override['si_escala'] ?? null) !== $scaleId) {
                continue;
            }

            $overrideValue = $override['aplicar'][$side] ?? null;
            if ($overrideValue !== null) {
                return (float) $overrideValue;
            }
        }

        return $defaultMultiplier;
    }

    /**
     * @param array<string, array{mas: string, menos: string}> $answers
     * @param array<string, mixed> $validityRules
     * @return array<string, int>
     */
    private function calculateValidityMetrics(array $answers, int $validityScore, array $validityRules): array
    {
        $metrics = [];
        $rules = $validityRules['validity_rules'] ?? [];
        $metricDefinitions = $rules['metricas'] ?? [];

        if (!is_array($metricDefinitions)) {
            return $metrics;
        }

        foreach ($metricDefinitions as $metricDefinition) {
            if (!is_array($metricDefinition)) {
                continue;
            }

            $metricId = (string) ($metricDefinition['id'] ?? '');
            if ($metricId === '') {
                continue;
            }

            $metrics[$metricId] = $this->resolveMetricValue($metricId, $answers, $validityScore);
        }

        return $metrics;
    }

    /**
     * @param array<string, array{mas: string, menos: string}> $answers
     */
    private function resolveMetricValue(string $metricId, array $answers, int $validityScore): int
    {
        return match ($metricId) {
            'omisiones' => $this->countOmissions($answers),
            'colision_mas_menos' => $this->countCollisions($answers),
            'indice_validez' => $validityScore,
            default => 0,
        };
    }

    /**
     * @param array<string, array{mas: string, menos: string}> $answers
     */
    private function countOmissions(array $answers): int
    {
        $count = 0;
        foreach ($answers as $answer) {
            $mas = trim((string) ($answer['mas'] ?? ''));
            $menos = trim((string) ($answer['menos'] ?? ''));

            if ($mas === '' || $menos === '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, array{mas: string, menos: string}> $answers
     */
    private function countCollisions(array $answers): int
    {
        $count = 0;
        foreach ($answers as $answer) {
            $mas = trim((string) ($answer['mas'] ?? ''));
            $menos = trim((string) ($answer['menos'] ?? ''));

            if ($mas !== '' && $mas === $menos) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, int> $metrics
     * @param array<string, mixed> $validityRules
     */
    private function resolveValidityState(array $metrics, array $validityRules): string
    {
        $rules = $validityRules['validity_rules']['decision'] ?? [];
        if (!is_array($rules)) {
            return 'desconocido';
        }

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $condition = trim((string) ($rule['si'] ?? ''));
            $state = (string) ($rule['estado'] ?? 'desconocido');

            if ($condition === 'default') {
                return $state;
            }

            if ($this->evaluateCondition($condition, $metrics)) {
                return $state;
            }
        }

        return 'desconocido';
    }

    /**
     * @param array<string, int> $metrics
     */
    private function evaluateCondition(string $condition, array $metrics): bool
    {
        $orClauses = preg_split('/\s+or\s+/i', trim($condition)) ?: [];

        foreach ($orClauses as $orClause) {
            $andChecks = preg_split('/\s+and\s+/i', trim($orClause)) ?: [];
            $allAndTrue = true;

            foreach ($andChecks as $check) {
                if (!$this->evaluateCheck(trim($check), $metrics)) {
                    $allAndTrue = false;
                    break;
                }
            }

            if ($allAndTrue) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, int> $metrics
     */
    private function evaluateCheck(string $check, array $metrics): bool
    {
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*(>=|<=|>|<|==|!=)\s*(-?\d+)$/', $check, $matches)) {
            return false;
        }

        $leftOperand = $metrics[$matches[1]] ?? 0;
        $operator = $matches[2];
        $rightOperand = (int) $matches[3];

        return match ($operator) {
            '>=' => $leftOperand >= $rightOperand,
            '<=' => $leftOperand <= $rightOperand,
            '>' => $leftOperand > $rightOperand,
            '<' => $leftOperand < $rightOperand,
            '==' => $leftOperand === $rightOperand,
            '!=' => $leftOperand !== $rightOperand,
            default => false,
        };
    }

    /**
     * @param array<string, int> $rawScores
     * @param array<string, mixed> $percentilesBySex
     * @return array<string, int|null>
     */
    private function calculatePercentiles(array $rawScores, array $percentilesBySex): array
    {
        $tables = $percentilesBySex['percentiles'] ?? [];
        $result = [];

        foreach ($rawScores as $scaleId => $rawScore) {
            if (!isset($tables[$scaleId]) || !is_array($tables[$scaleId])) {
                $result[$scaleId] = null;
                continue;
            }

            $result[$scaleId] = $this->resolvePercentile((int) $rawScore, $tables[$scaleId]);
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $rows
     */
    private function resolvePercentile(int $rawScore, array $rows): ?int
    {
        $bestDistance = null;
        $bestPercentile = null;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!isset($row['bruto'], $row['percentil'])) {
                continue;
            }

            $distance = abs($rawScore - (int) $row['bruto']);
            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $bestPercentile = (int) $row['percentil'];
            }
        }

        return $bestPercentile;
    }

    /**
     * @param array<string, int> $rawScores
     * @return array<int, array{escala: string, puntaje_bruto: int}>
     */
    private function sortScalesByRawScore(array $rawScores): array
    {
        $items = [];
        foreach ($rawScores as $scaleId => $score) {
            $items[] = ['escala' => $scaleId, 'puntaje_bruto' => $score];
        }

        usort($items, static function (array $a, array $b): int {
            if ($a['puntaje_bruto'] === $b['puntaje_bruto']) {
                return strcmp($a['escala'], $b['escala']);
            }

            return $b['puntaje_bruto'] <=> $a['puntaje_bruto'];
        });

        return $items;
    }
}

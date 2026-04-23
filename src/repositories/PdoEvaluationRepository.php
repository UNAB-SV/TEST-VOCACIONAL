<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class PdoEvaluationRepository implements EvaluationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function saveEvaluation(array $participant, array $answers, array $result, string $appliedAt): int
    {
        try {
            $this->pdo->beginTransaction();

            $participantId = $this->upsertParticipant($participant);
            $evaluationId = $this->insertEvaluation($participantId, $participant, $result, $appliedAt);
            $this->insertAnswers($evaluationId, $answers);
            $this->insertScaleScores($evaluationId, $result);
            $this->insertPercentiles($evaluationId, $result);

            $this->pdo->commit();

            return $evaluationId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException('No se pudo guardar la evaluación en la base de datos.', 0, $exception);
        }
    }

    public function findPreviousEvaluationsByParticipant(array $participant, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $sql = <<<SQL
SELECT
    e.id,
    e.applied_at,
    e.validity_score,
    e.validity_state,
    e.sex,
    e.group_name,
    e.colegio_nombre,
    e.pais_id,
    e.pais_nombre,
    e.departamento_id,
    e.departamento_nombre,
    e.municipio_id,
    e.municipio_nombre,
    p.first_name,
    p.last_name,
    p.middle_name,
    p.age,
    (
        SELECT JSON_ARRAYAGG(
            JSON_OBJECT(
                'scale_id', ess.scale_id,
                'raw_score', ess.raw_score,
                'percentile', COALESCE(ep.percentile_value, 0)
            )
        )
        FROM evaluation_scale_scores ess
        LEFT JOIN evaluation_percentiles ep
            ON ep.evaluation_id = ess.evaluation_id
           AND ep.scale_id = ess.scale_id
        WHERE ess.evaluation_id = e.id
    ) AS scales_summary
FROM evaluations e
INNER JOIN participants p ON p.id = e.participant_id
WHERE p.first_name = :first_name
  AND p.last_name = :last_name
  AND p.middle_name = :middle_name
ORDER BY e.applied_at DESC, e.id DESC
LIMIT {$limit}
SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':first_name', (string) ($participant['nombres'] ?? ''));
        $statement->bindValue(':last_name', (string) ($participant['apellido_paterno'] ?? ''));
        $statement->bindValue(':middle_name', (string) ($participant['apellido_materno'] ?? ''));
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $summary = $row['scales_summary'] ?? null;
            if (is_string($summary) && $summary !== '') {
                $decoded = json_decode($summary, true);
                $row['scales_summary'] = is_array($decoded) ? $decoded : [];
                continue;
            }

            $row['scales_summary'] = [];
        }

        return $rows;
    }

    public function findEvaluations(array $filters, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $bindings = [];

        $search = trim((string) ($filters['nombre'] ?? ''));
        if ($search !== '') {
            $where[] = 'CONCAT_WS(\' \', p.first_name, p.last_name, p.middle_name) LIKE :nombre';
            $bindings[':nombre'] = '%' . $search . '%';
        }

        $school = trim((string) ($filters['grupo'] ?? ''));
        if ($school !== '') {
            $where[] = 'COALESCE(NULLIF(e.colegio_nombre, \'\'), e.group_name) = :grupo';
            $bindings[':grupo'] = $school;
        }

        $date = trim((string) ($filters['fecha'] ?? ''));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $where[] = 'DATE(e.applied_at) = :fecha';
            $bindings[':fecha'] = $date;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $countSql = <<<SQL
SELECT COUNT(*)
FROM evaluations e
INNER JOIN participants p ON p.id = e.participant_id
{$whereSql}
SQL;
        $countStatement = $this->pdo->prepare($countSql);
        foreach ($bindings as $name => $value) {
            $countStatement->bindValue($name, $value);
        }
        $countStatement->execute();
        $total = (int) $countStatement->fetchColumn();

        $listSql = <<<SQL
SELECT
    e.id,
    e.applied_at,
    e.group_name,
    e.colegio_nombre,
    e.pais_nombre,
    e.departamento_nombre,
    e.municipio_nombre,
    e.validity_state,
    p.first_name,
    p.last_name,
    p.middle_name
FROM evaluations e
INNER JOIN participants p ON p.id = e.participant_id
{$whereSql}
ORDER BY e.applied_at DESC, e.id DESC
LIMIT :limit OFFSET :offset
SQL;
        $listStatement = $this->pdo->prepare($listSql);
        foreach ($bindings as $name => $value) {
            $listStatement->bindValue($name, $value);
        }
        $listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStatement->execute();

        $items = $listStatement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($items)) {
            $items = [];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function listGroups(): array
    {
        $statement = $this->pdo->query(
            'SELECT DISTINCT COALESCE(NULLIF(colegio_nombre, \'\'), group_name) AS institution_name
             FROM evaluations
             WHERE COALESCE(NULLIF(colegio_nombre, \'\'), group_name) <> \'\'
             ORDER BY institution_name ASC'
        );
        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $rows), static fn (string $group): bool => $group !== ''));
    }

    public function findEvaluationDetailById(int $evaluationId): ?array
    {
        $sql = <<<SQL
SELECT
    e.id,
    e.applied_at,
    e.sex,
    e.group_name,
    e.colegio_id,
    e.colegio_nombre,
    e.pais_id,
    e.pais_nombre,
    e.departamento_id,
    e.departamento_nombre,
    e.municipio_id,
    e.municipio_nombre,
    e.validity_score,
    e.validity_state,
    e.raw_scores_json,
    p.first_name,
    p.last_name,
    p.middle_name,
    p.age
FROM evaluations e
INNER JOIN participants p ON p.id = e.participant_id
WHERE e.id = :id
LIMIT 1
SQL;
        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':id', $evaluationId, PDO::PARAM_INT);
        $statement->execute();

        $evaluation = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($evaluation)) {
            return null;
        }

        $scalesStatement = $this->pdo->prepare(
            'SELECT ess.scale_id, ess.raw_score, COALESCE(ep.percentile_value, 0) AS percentile
             FROM evaluation_scale_scores ess
             LEFT JOIN evaluation_percentiles ep
               ON ep.evaluation_id = ess.evaluation_id
              AND ep.scale_id = ess.scale_id
             WHERE ess.evaluation_id = :id'
        );
        $scalesStatement->bindValue(':id', $evaluationId, PDO::PARAM_INT);
        $scalesStatement->execute();
        $scales = $scalesStatement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($scales)) {
            $scales = [];
        }

        $answersStatement = $this->pdo->prepare(
            'SELECT block_id, selected_mas_activity_id AS mas, selected_menos_activity_id AS menos
             FROM evaluation_answers
             WHERE evaluation_id = :id
             ORDER BY block_id ASC'
        );
        $answersStatement->bindValue(':id', $evaluationId, PDO::PARAM_INT);
        $answersStatement->execute();
        $answers = $answersStatement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($answers)) {
            $answers = [];
        }

        $evaluation['scales'] = $scales;
        $evaluation['answers'] = $answers;

        return $evaluation;
    }

    /**
     * @param array<string, string> $participant
     */
    private function upsertParticipant(array $participant): int
    {
        $sql = <<<SQL
INSERT INTO participants (
    first_name,
    last_name,
    middle_name,
    age,
    sex,
    group_name,
    colegio_id,
    colegio_nombre,
    pais_id,
    pais_nombre,
    departamento_id,
    departamento_nombre,
    municipio_id,
    municipio_nombre,
    created_at,
    updated_at
) VALUES (
    :first_name,
    :last_name,
    :middle_name,
    :age,
    :sex,
    :group_name,
    :colegio_id,
    :colegio_nombre,
    :pais_id,
    :pais_nombre,
    :departamento_id,
    :departamento_nombre,
    :municipio_id,
    :municipio_nombre,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    age = VALUES(age),
    sex = VALUES(sex),
    group_name = VALUES(group_name),
    colegio_id = VALUES(colegio_id),
    colegio_nombre = VALUES(colegio_nombre),
    pais_id = VALUES(pais_id),
    pais_nombre = VALUES(pais_nombre),
    departamento_id = VALUES(departamento_id),
    departamento_nombre = VALUES(departamento_nombre),
    municipio_id = VALUES(municipio_id),
    municipio_nombre = VALUES(municipio_nombre),
    updated_at = NOW()
SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':first_name', (string) ($participant['nombres'] ?? ''));
        $statement->bindValue(':last_name', (string) ($participant['apellido_paterno'] ?? ''));
        $statement->bindValue(':middle_name', (string) ($participant['apellido_materno'] ?? ''));
        $statement->bindValue(':age', (int) ($participant['edad'] ?? 0), PDO::PARAM_INT);
        $statement->bindValue(':sex', strtoupper((string) ($participant['sexo'] ?? '')));
        $statement->bindValue(':group_name', $this->resolveGroupName($participant, []));
        $colegioId = (int) ($participant['colegio_id'] ?? 0);
        if ($colegioId > 0) {
            $statement->bindValue(':colegio_id', $colegioId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':colegio_id', null, PDO::PARAM_NULL);
        }
        $statement->bindValue(':colegio_nombre', (string) ($participant['colegio_nombre'] ?? ''));
        $paisId = (int) ($participant['pais_id'] ?? 0);
        if ($paisId > 0) {
            $statement->bindValue(':pais_id', $paisId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':pais_id', null, PDO::PARAM_NULL);
        }
        $this->bindNullableString($statement, ':pais_nombre', $participant['pais_nombre'] ?? null);
        $departamentoId = (int) ($participant['departamento_id'] ?? 0);
        if ($departamentoId > 0) {
            $statement->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':departamento_id', null, PDO::PARAM_NULL);
        }
        $this->bindNullableString($statement, ':departamento_nombre', $participant['departamento_nombre'] ?? null);
        $municipioId = (int) ($participant['municipio_id'] ?? 0);
        if ($municipioId > 0) {
            $statement->bindValue(':municipio_id', $municipioId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':municipio_id', null, PDO::PARAM_NULL);
        }
        $this->bindNullableString($statement, ':municipio_nombre', $participant['municipio_nombre'] ?? null);
        $statement->execute();

        $lastInsertId = (int) $this->pdo->lastInsertId();
        if ($lastInsertId > 0) {
            return $lastInsertId;
        }

        $select = $this->pdo->prepare(
            'SELECT id FROM participants WHERE first_name = :first_name AND last_name = :last_name AND middle_name = :middle_name LIMIT 1'
        );
        $select->bindValue(':first_name', (string) ($participant['nombres'] ?? ''));
        $select->bindValue(':last_name', (string) ($participant['apellido_paterno'] ?? ''));
        $select->bindValue(':middle_name', (string) ($participant['apellido_materno'] ?? ''));
        $select->execute();

        $id = $select->fetchColumn();
        if ($id === false) {
            throw new PDOException('No se pudo resolver el participant_id luego del upsert.');
        }

        return (int) $id;
    }

    /**
     * @param array<string, string> $participant
     * @param array<string, mixed> $result
     */
    private function insertEvaluation(int $participantId, array $participant, array $result, string $appliedAt): int
    {
        $sql = <<<SQL
INSERT INTO evaluations (
    participant_id,
    applied_at,
    sex,
    group_name,
    colegio_id,
    colegio_nombre,
    pais_id,
    pais_nombre,
    departamento_id,
    departamento_nombre,
    municipio_id,
    municipio_nombre,
    validity_score,
    validity_state,
    validity_details_json,
    raw_scores_json,
    created_at
) VALUES (
    :participant_id,
    :applied_at,
    :sex,
    :group_name,
    :colegio_id,
    :colegio_nombre,
    :pais_id,
    :pais_nombre,
    :departamento_id,
    :departamento_nombre,
    :municipio_id,
    :municipio_nombre,
    :validity_score,
    :validity_state,
    :validity_details_json,
    :raw_scores_json,
    NOW()
)
SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':participant_id', $participantId, PDO::PARAM_INT);
        $statement->bindValue(':applied_at', $appliedAt);
        $statement->bindValue(':sex', strtoupper((string) ($result['sexo_evaluado'] ?? '')));
        $statement->bindValue(':group_name', $this->resolveGroupName($participant, $result));
        $colegioId = (int) ($participant['colegio_id'] ?? 0);
        if ($colegioId > 0) {
            $statement->bindValue(':colegio_id', $colegioId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':colegio_id', null, PDO::PARAM_NULL);
        }
        $statement->bindValue(':colegio_nombre', $this->resolveSchoolName($participant, $result));
        $paisId = (int) ($participant['pais_id'] ?? 0);
        if ($paisId > 0) {
            $statement->bindValue(':pais_id', $paisId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':pais_id', null, PDO::PARAM_NULL);
        }
        $this->bindNullableString($statement, ':pais_nombre', $participant['pais_nombre'] ?? null);
        $departamentoId = (int) ($participant['departamento_id'] ?? 0);
        if ($departamentoId > 0) {
            $statement->bindValue(':departamento_id', $departamentoId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':departamento_id', null, PDO::PARAM_NULL);
        }
        $this->bindNullableString($statement, ':departamento_nombre', $participant['departamento_nombre'] ?? null);
        $municipioId = (int) ($participant['municipio_id'] ?? 0);
        if ($municipioId > 0) {
            $statement->bindValue(':municipio_id', $municipioId, PDO::PARAM_INT);
        } else {
            $statement->bindValue(':municipio_id', null, PDO::PARAM_NULL);
        }
        $this->bindNullableString($statement, ':municipio_nombre', $participant['municipio_nombre'] ?? null);
        $statement->bindValue(':validity_score', (int) ($result['validez_puntaje'] ?? 0), PDO::PARAM_INT);
        $statement->bindValue(':validity_state', (string) ($result['validez_estado'] ?? 'invalido'));
        $statement->bindValue(':validity_details_json', json_encode($result['detalles_validez'] ?? [], JSON_UNESCAPED_UNICODE));
        $statement->bindValue(':raw_scores_json', json_encode($result['puntajes_brutos'] ?? [], JSON_UNESCAPED_UNICODE));
        $statement->execute();

        return (int) $this->pdo->lastInsertId();
    }


    /**
     * @param array<string, string> $participant
     * @param array<string, mixed> $result
     */
    private function resolveGroupName(array $participant, array $result): string
    {
        $participantGroup = trim((string) ($participant['grupo'] ?? ''));
        if ($participantGroup !== '') {
            return $participantGroup;
        }

        $schoolName = trim((string) ($participant['colegio_nombre'] ?? ''));
        if ($schoolName !== '') {
            return $schoolName;
        }

        return trim((string) ($result['grupo'] ?? ''));
    }

    /**
     * @param array<string, string> $participant
     * @param array<string, mixed> $result
     */
    private function resolveSchoolName(array $participant, array $result): string
    {
        $participantSchool = trim((string) ($participant['colegio_nombre'] ?? ''));
        if ($participantSchool !== '') {
            return $participantSchool;
        }

        return trim((string) ($result['colegio_nombre'] ?? ''));
    }

    private function bindNullableString(\PDOStatement $statement, string $param, mixed $value): void
    {
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            $statement->bindValue($param, null, PDO::PARAM_NULL);
            return;
        }

        $statement->bindValue($param, $stringValue);
    }

    /**
     * @param array<string, array{mas: string, menos: string}> $answers
     */
    private function insertAnswers(int $evaluationId, array $answers): void
    {
        $sql = 'INSERT INTO evaluation_answers (evaluation_id, block_id, selected_mas_activity_id, selected_menos_activity_id) VALUES (:evaluation_id, :block_id, :mas, :menos)';
        $statement = $this->pdo->prepare($sql);

        foreach ($answers as $blockId => $answer) {
            $statement->bindValue(':evaluation_id', $evaluationId, PDO::PARAM_INT);
            $statement->bindValue(':block_id', (string) $blockId);
            $statement->bindValue(':mas', (string) ($answer['mas'] ?? ''));
            $statement->bindValue(':menos', (string) ($answer['menos'] ?? ''));
            $statement->execute();
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function insertScaleScores(int $evaluationId, array $result): void
    {
        $scores = $result['puntajes_brutos'] ?? [];
        if (!is_array($scores)) {
            return;
        }

        $sql = 'INSERT INTO evaluation_scale_scores (evaluation_id, scale_id, raw_score) VALUES (:evaluation_id, :scale_id, :raw_score)';
        $statement = $this->pdo->prepare($sql);

        foreach ($scores as $scaleId => $rawScore) {
            $statement->bindValue(':evaluation_id', $evaluationId, PDO::PARAM_INT);
            $statement->bindValue(':scale_id', (string) $scaleId);
            $statement->bindValue(':raw_score', (int) $rawScore, PDO::PARAM_INT);
            $statement->execute();
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function insertPercentiles(int $evaluationId, array $result): void
    {
        $percentiles = $result['percentiles'] ?? [];
        if (!is_array($percentiles)) {
            return;
        }

        $sql = 'INSERT INTO evaluation_percentiles (evaluation_id, scale_id, percentile_value) VALUES (:evaluation_id, :scale_id, :percentile_value)';
        $statement = $this->pdo->prepare($sql);

        foreach ($percentiles as $scaleId => $percentileValue) {
            $statement->bindValue(':evaluation_id', $evaluationId, PDO::PARAM_INT);
            $statement->bindValue(':scale_id', (string) $scaleId);
            $statement->bindValue(':percentile_value', (int) $percentileValue, PDO::PARAM_INT);
            $statement->execute();
        }
    }
}

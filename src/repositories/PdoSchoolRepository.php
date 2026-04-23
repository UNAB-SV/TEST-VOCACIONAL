<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PdoSchoolRepository implements SchoolRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function searchByName(string $query, int $limit = 15): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $limit = max(1, min($limit, 30));
        $sql = <<<SQL
SELECT id, nombre, tipo_institucion
FROM colegios
WHERE nombre IS NOT NULL
  AND nombre <> ''
  AND nombre LIKE :term
ORDER BY nombre ASC
LIMIT {$limit}
SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':term', '%' . $term . '%');
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'tipo_institucion' => isset($row['tipo_institucion']) ? (int) $row['tipo_institucion'] : null,
        ], $rows);
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT id, nombre, tipo_institucion FROM colegios WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'tipo_institucion' => isset($row['tipo_institucion']) ? (int) $row['tipo_institucion'] : null,
        ];
    }

    public function existsById(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare('SELECT 1 FROM colegios WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }
}

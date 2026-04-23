<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PdoGeoCatalogRepository implements GeoCatalogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listCountries(): array
    {
        $statement = $this->pdo->query(
            "SELECT id, nombre
             FROM pais
             WHERE id > 0
               AND TRIM(COALESCE(nombre, '')) <> ''
             ORDER BY nombre ASC"
        );
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
        ], $rows);
    }

    public function findCountryById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            "SELECT id, nombre
             FROM pais
             WHERE id = :id
               AND id > 0
               AND TRIM(COALESCE(nombre, '')) <> ''
             LIMIT 1"
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
        ];
    }

    public function listDepartments(): array
    {
        $statement = $this->pdo->query(
            "SELECT id, nombre
             FROM departamento
             WHERE id > 0
               AND TRIM(COALESCE(nombre, '')) <> ''
             ORDER BY nombre ASC"
        );
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
        ], $rows);
    }

    public function findDepartmentById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            "SELECT id, nombre
             FROM departamento
             WHERE id = :id
               AND id > 0
               AND TRIM(COALESCE(nombre, '')) <> ''
             LIMIT 1"
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
        ];
    }

    public function listMunicipalitiesByDepartment(int $departmentId): array
    {
        if ($departmentId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            "SELECT id_munic, nombre
             FROM munic
             WHERE id_depto = :department_id
               AND id_depto > 0
               AND id_munic > 0
               AND TRIM(COALESCE(nombre, '')) <> ''
             ORDER BY nombre ASC"
        );
        $statement->bindValue(':department_id', $departmentId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) ($row['id_munic'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
        ], $rows);
    }

    public function findMunicipalityByDepartmentAndId(int $departmentId, int $municipalityId): ?array
    {
        if ($departmentId <= 0 || $municipalityId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            "SELECT id_munic, nombre
             FROM munic
             WHERE id_depto = :department_id
               AND id_depto > 0
               AND id_munic = :municipality_id
               AND id_munic > 0
               AND TRIM(COALESCE(nombre, '')) <> ''
             LIMIT 1"
        );
        $statement->bindValue(':department_id', $departmentId, PDO::PARAM_INT);
        $statement->bindValue(':municipality_id', $municipalityId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) ($row['id_munic'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
        ];
    }
}

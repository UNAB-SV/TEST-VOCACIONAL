<?php

declare(strict_types=1);

namespace App\Repositories;

final class NullGeoCatalogRepository implements GeoCatalogRepository
{
    public function listCountries(): array
    {
        return [];
    }

    public function findCountryById(int $id): ?array
    {
        return null;
    }

    public function listDepartments(): array
    {
        return [];
    }

    public function findDepartmentById(int $id): ?array
    {
        return null;
    }

    public function listMunicipalitiesByDepartment(int $departmentId): array
    {
        return [];
    }

    public function findMunicipalityByDepartmentAndId(int $departmentId, int $municipalityId): ?array
    {
        return null;
    }
}

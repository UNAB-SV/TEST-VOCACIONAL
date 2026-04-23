<?php

declare(strict_types=1);

namespace App\Repositories;

interface GeoCatalogRepository
{
    /**
     * @return array<int, array{id: int, nombre: string}>
     */
    public function listCountries(): array;

    /**
     * @return array{id: int, nombre: string}|null
     */
    public function findCountryById(int $id): ?array;

    /**
     * @return array<int, array{id: int, nombre: string}>
     */
    public function listDepartments(): array;

    /**
     * @return array{id: int, nombre: string}|null
     */
    public function findDepartmentById(int $id): ?array;

    /**
     * @return array<int, array{id: int, nombre: string}>
     */
    public function listMunicipalitiesByDepartment(int $departmentId): array;

    /**
     * @return array{id: int, nombre: string}|null
     */
    public function findMunicipalityByDepartmentAndId(int $departmentId, int $municipalityId): ?array;
}

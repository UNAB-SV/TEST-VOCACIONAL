<?php

declare(strict_types=1);

namespace App\Repositories;

interface SchoolRepository
{
    /**
     * @return array<int, array{id: int, nombre: string, tipo_institucion: int|null}>
     */
    public function searchByName(string $query, int $limit = 15): array;

    /**
     * @return array{id: int, nombre: string, tipo_institucion: int|null}|null
     */
    public function findById(int $id): ?array;

    public function existsById(int $id): bool;
}

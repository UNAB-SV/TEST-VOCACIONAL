<?php

declare(strict_types=1);

namespace App\Repositories;

final class NullSchoolRepository implements SchoolRepository
{
    public function searchByName(string $query, int $limit = 15): array
    {
        return [];
    }

    public function findById(int $id): ?array
    {
        return null;
    }

    public function existsById(int $id): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

interface CatalogRepository
{
    /**
     * @return array<string, mixed>
     */
    public function questionsDefinition(): array;

    /**
     * @return array<string, mixed>
     */
    public function scalesConfig(): array;

    /**
     * @return array<string, mixed>
     */
    public function scoringRules(): array;

    /**
     * @return array<string, mixed>
     */
    public function validityRules(): array;

    /**
     * @return array<string, mixed>
     */
    public function percentilesBySex(string $sex): array;
}


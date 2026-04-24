<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class FallbackCatalogRepository implements CatalogRepository
{
    public function __construct(
        private readonly CatalogRepository $primary,
        private readonly CatalogRepository $fallback
    ) {
    }

    public function questionsDefinition(): array
    {
        return $this->withFallback(fn (CatalogRepository $repository): array => $repository->questionsDefinition());
    }

    public function scalesConfig(): array
    {
        return $this->withFallback(fn (CatalogRepository $repository): array => $repository->scalesConfig());
    }

    public function scoringRules(): array
    {
        return $this->withFallback(fn (CatalogRepository $repository): array => $repository->scoringRules());
    }

    public function validityRules(): array
    {
        return $this->withFallback(fn (CatalogRepository $repository): array => $repository->validityRules());
    }

    public function percentilesBySex(string $sex): array
    {
        return $this->withFallback(fn (CatalogRepository $repository): array => $repository->percentilesBySex($sex));
    }

    /**
     * @param callable(CatalogRepository): array<string, mixed> $reader
     * @return array<string, mixed>
     */
    private function withFallback(callable $reader): array
    {
        try {
            return $reader($this->primary);
        } catch (Throwable) {
            return $reader($this->fallback);
        }
    }
}

